<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Query\Modifier\Modifier;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\QueryAware;
use ApacheSolrForTypo3\Solr\Search\SearchAware;
use ApacheSolrForTypo3\Solr\Search\SearchComponentManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use Apache_Solr_ParserException;

/**
 * The SearchResultSetService is responsible to build a SearchResultSet from a SearchRequest.
 * It encapsulates the logic to trigger a search in order to be able to reuse it in multiple places.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResultSetService
{
    /**
     * Additional filters, which will be added to the query, as well as to
     * suggest queries.
     *
     * @var array
     */
    protected $additionalFilters = [];

    /**
     * Track, if the number of results per page has been changed by the current request
     *
     * @var bool
     */
    protected $resultsPerPageChanged = false;

    /**
     * @var \ApacheSolrForTypo3\Solr\Search
     */
    protected $search;

    /**
     * @var SearchResultSet
     */
    protected $lastResultSet = null;

    /**
     * @var
     */
    protected $isSolrAvailable = false;

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
     */
    protected $logger = null;

    /**
     * @param TypoScriptConfiguration $configuration
     * @param Search $search
     * @param SolrLogManager $solrLogManager
     */
    public function __construct(TypoScriptConfiguration $configuration, Search $search, SolrLogManager $solrLogManager = null)
    {
        $this->search = $search;
        $this->typoScriptConfiguration = $configuration;
        $this->logger = is_null($solrLogManager) ? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__) : $solrLogManager;
    }

    /**
     * @param bool $useCache
     * @return bool
     */
    public function getIsSolrAvailable($useCache = true)
    {
        $this->isSolrAvailable = $this->search->ping($useCache);
        return $this->isSolrAvailable;
    }

    /**
     * @return bool
     */
    public function getHasSearched()
    {
        return $this->search->hasSearched();
    }

    /**
     * Retrieves the used search instance.
     *
     * @return Search
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * Initializes the Query object and SearchComponents and returns
     * the initialized query object, when a search should be executed.
     *
     * @param string|null $rawQuery
     * @param int $resultsPerPage
     * @return Query
     */
    protected function getPreparedQuery($rawQuery, $resultsPerPage)
    {
        /* @var $query Query */
        $query = $this->getQueryInstance($rawQuery);

        $this->applyPageSectionsRootLineFilter($query);

        if ($this->typoScriptConfiguration->getLoggingQuerySearchWords()) {
            $this->logger->log(
                SolrLogManager::INFO,
                'Received search query',
                [
                    $rawQuery
                ]
            );
        }

        $query->setResultsPerPage($resultsPerPage);

        if ($this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchQueryAllowEmptyQuery()) {
            // empty main query, but using a "return everything"
            // alternative query in q.alt
            $query->setAlternativeQuery('*:*');
        }

        if ($this->typoScriptConfiguration->getSearchInitializeWithQuery()) {
            $query->setAlternativeQuery($this->typoScriptConfiguration->getSearchInitializeWithQuery());
        }

        foreach ($this->getAdditionalFilters() as $additionalFilter) {
            $query->getFilters()->add($additionalFilter);
        }

        return $query;
    }

    /**
     * @param Query $query
     * @param SearchRequest $searchRequest
     */
    protected function initializeRegisteredSearchComponents(Query $query, SearchRequest $searchRequest)
    {
        $searchComponents = $this->getRegisteredSearchComponents();

        foreach ($searchComponents as $searchComponent) {
            /** @var Search\SearchComponent $searchComponent */
            $searchComponent->setSearchConfiguration($this->typoScriptConfiguration->getSearchConfiguration());

            if ($searchComponent instanceof QueryAware) {
                $searchComponent->setQuery($query);
            }

            if ($searchComponent instanceof SearchRequestAware) {
                $searchComponent->setSearchRequest($searchRequest);
            }

            $searchComponent->initializeSearchComponent();
        }
    }

    /**
     * Returns the number of results per Page.
     *
     * Also influences how many result documents are returned by the Solr
     * server as the return value is used in the Solr "rows" GET parameter.
     *
     * @param SearchRequest $searchRequest
     * @return int number of results to show per page
     */
    protected function getNumberOfResultsPerPage(SearchRequest $searchRequest)
    {
        $requestedPerPage = $searchRequest->getResultsPerPage();
        $perPageSwitchOptions = $this->typoScriptConfiguration->getSearchResultsPerPageSwitchOptionsAsArray();
        if (isset($requestedPerPage) && in_array($requestedPerPage, $perPageSwitchOptions)) {
            $this->setPerPageInSession($requestedPerPage);
            $this->resultsPerPageChanged = true;
        }

        $defaultResultsPerPage = $this->typoScriptConfiguration->getSearchResultsPerPage();
        $sessionResultPerPage = $this->getPerPageFromSession();

        $currentNumberOfResultsShown = $defaultResultsPerPage;
        if (!is_null($sessionResultPerPage) && in_array($sessionResultPerPage, $perPageSwitchOptions)) {
            $currentNumberOfResultsShown = (int)$sessionResultPerPage;
        }

        if ($this->shouldHideResultsFromInitialSearch($searchRequest)) {
            // initialize search with an empty query, which would by default return all documents
            // anyway, tell Solr to not return any result documents
            // Solr will still return facets though
            $currentNumberOfResultsShown = 0;
        }

        return $currentNumberOfResultsShown;
    }

    /**
     * Does post processing of the response.
     *
     * @param \Apache_Solr_Response $response The search's response.
     */
    protected function processResponse(\Apache_Solr_Response $response)
    {
        $this->wrapResultDocumentInResultObject($response);
        $this->addExpandedDocumentsFromVariants($response);
    }

    /**
     * This method is used to add documents to the expanded documents of the SearchResult
     * when collapsing is configured.
     *
     * @param \Apache_Solr_Response $response
     */
    protected function addExpandedDocumentsFromVariants(\Apache_Solr_Response $response)
    {
        if (!is_array($response->response->docs)) {
            return;
        }

        if (!$this->typoScriptConfiguration->getSearchVariants()) {
            return;
        }

        $variantsField = $this->typoScriptConfiguration->getSearchVariantsField();
        foreach ($response->response->docs as $key => $resultDocument) {
            /** @var $resultDocument SearchResult */
            $variantField = $resultDocument->getField($variantsField);
            $variantId = isset($variantField['value']) ? $variantField['value'] : null;

                // when there is no value in the collapsing field, we can return
            if ($variantId === null) {
                continue;
            }

            $variantAccessKey = mb_strtolower($variantId);
            if (!isset($response->{'expanded'}) || !isset($response->{'expanded'}->{$variantAccessKey})) {
                continue;
            }

            foreach ($response->{'expanded'}->{$variantAccessKey}->{'docs'} as $variantDocumentArray) {
                $variantDocument = new \Apache_Solr_Document();
                foreach (get_object_vars($variantDocumentArray) as $propertyName => $propertyValue) {
                    $variantDocument->{$propertyName} = $propertyValue;
                }
                $variantSearchResult = $this->wrapApacheSolrDocumentInResultObject($variantDocument);
                $variantSearchResult->setIsVariant(true);
                $variantSearchResult->setVariantParent($resultDocument);

                $resultDocument->addVariant($variantSearchResult);
            }
        }
    }

    /**
     * Wrap all results document it a custom EXT:solr SearchResult object.
     *
     * Can be overwritten:
     *
     * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = ''
     *
     * to use a custom result object.
     *
     * @param \Apache_Solr_Response $response
     * @throws \Apache_Solr_ParserException
     */
    protected function wrapResultDocumentInResultObject(\Apache_Solr_Response $response)
    {
        try {
            $documents = $response->response->docs;
        } catch (Apache_Solr_ParserException $e) {
            // when variant are enable and the index is empty, we get a parse exception, because of a
            // Apache Solr Bug.
            // see: https://github.com/TYPO3-Solr/ext-solr/issues/668
            // @todo this try/catch block can be removed after upgrading to Apache Solr 6.4
            if (!$this->typoScriptConfiguration->getSearchVariants()) {
                throw $e;
            }

            $response->response = new \stdClass();
            $response->spellcheck = [];
            $response->debug = [];
            $response->responseHeader = [];
            $response->facet_counts = [];

            $documents = [];
        }

        if (!is_array($documents)) {
            return;
        }

        foreach ($documents as $key => $originalDocument) {
            $result = $this->wrapApacheSolrDocumentInResultObject($originalDocument);
            $documents[$key] = $result;
        }

        $response->response->docs = $documents;
    }

    /**
     * This method is used to wrap the \Apache_Solr_Document instance in an instance of the configured SearchResult
     * class.
     *
     * @param \Apache_Solr_Document $originalDocument
     * @throws \InvalidArgumentException
     * @return SearchResult
     */
    protected function wrapApacheSolrDocumentInResultObject(\Apache_Solr_Document $originalDocument)
    {
        $searchResultClassName = $this->getResultClassName();
        $result = GeneralUtility::makeInstance($searchResultClassName, $originalDocument);
        if (!$result instanceof SearchResult) {
            throw new \InvalidArgumentException('Could not create result object with class: ' . (string)$searchResultClassName, 1470037679);
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getResultClassName()
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName ']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] : SearchResult::class;
    }

    /**
     * @return string
     */
    protected function getResultSetClassName()
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName ']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] : SearchResultSet::class;
    }

    /**
     * Checks it the results should be hidden in the response.
     *
     * @param SearchRequest $searchRequest
     * @return bool
     */
    protected function shouldHideResultsFromInitialSearch(SearchRequest $searchRequest)
    {
        return ($this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchInitializeWithQuery()) && !$this->typoScriptConfiguration->getSearchShowResultsOfInitialEmptyQuery() && !$this->typoScriptConfiguration->getSearchShowResultsOfInitialQuery() && $searchRequest->getRawUserQueryIsNull();
    }

    /**
     * Initializes additional filters configured through TypoScript and
     * Flexforms for use in regular queries and suggest queries.
     *
     * @param Query $query
     * @return void
     */
    protected function applyPageSectionsRootLineFilter(Query $query)
    {
        $searchQueryFilters = $this->typoScriptConfiguration->getSearchQueryFilterConfiguration();
        if (count($searchQueryFilters) <= 0) {
            return;
        }

        // special filter to limit search to specific page tree branches
        if (array_key_exists('__pageSections', $searchQueryFilters)) {
            $query->setRootlineFilter($searchQueryFilters['__pageSections']);
            $this->typoScriptConfiguration->removeSearchQueryFilterForPageSections();
        }
    }

    /**
     * Retrieves the configuration filters from the TypoScript configuration, except the __pageSections filter.
     *
     * @return array
     */
    public function getAdditionalFilters()
    {
        // when we've build the additionalFilter once, we could return them
        if (count($this->additionalFilters) > 0) {
            return $this->additionalFilters;
        }

        $searchQueryFilters = $this->typoScriptConfiguration->getSearchQueryFilterConfiguration();
        if (count($searchQueryFilters) <= 0) {
            return [];
        }

        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        // all other regular filters
        foreach ($searchQueryFilters as $filterKey => $filter) {
            // the __pageSections filter should not be handled as additional filter
            if ($filterKey === '__pageSections') {
                continue;
            }

            $filterIsArray = is_array($searchQueryFilters[$filterKey]);
            if ($filterIsArray) {
                continue;
            }

            $hasSubConfiguration = is_array($searchQueryFilters[$filterKey . '.']);
            if ($hasSubConfiguration) {
                $filter = $cObj->stdWrap($searchQueryFilters[$filterKey], $searchQueryFilters[$filterKey . '.']);
            }

            $this->additionalFilters[$filterKey] = $filter;
        }

        return $this->additionalFilters;
    }

    /**
     * Performs a search and returns a SearchResultSet.
     *
     * @param SearchRequest $searchRequest
     * @return SearchResultSet
     */
    public function search(SearchRequest $searchRequest)
    {
        /** @var $resultSet SearchResultSet */
        $resultSetClass = $this->getResultSetClassName();
        $resultSet = GeneralUtility::makeInstance($resultSetClass);
        $resultSet->setUsedSearchRequest($searchRequest);
        $this->lastResultSet = $resultSet;

        $resultSet = $this->handleSearchHook('beforeSearch', $resultSet);

        if ($searchRequest->getRawUserQueryIsNull() && !$this->getInitialSearchIsConfigured()) {
            // when no rawQuery was passed or no initialSearch is configured, we pass an empty result set
            return $resultSet;
        }

        if ($searchRequest->getRawUserQueryIsEmptyString() && !$this->typoScriptConfiguration->getSearchQueryAllowEmptyQuery()) {
            // the user entered an empty query string "" or "  " and empty querystring is not allowed
            return $resultSet;
        }

        $rawQuery = $searchRequest->getRawUserQuery();
        $resultsPerPage = $this->getNumberOfResultsPerPage($searchRequest);
        $query = $this->getPreparedQuery($rawQuery, $resultsPerPage);
        $this->initializeRegisteredSearchComponents($query, $searchRequest);
        $resultSet->setUsedQuery($query);

        $currentPage = max(0, $searchRequest->getPage());
        // if the number of results per page has been changed by the current request, reset the pagebrowser
        if ($this->resultsPerPageChanged) {
            $currentPage = 0;
        }

        $offSet = $currentPage * $resultsPerPage;

        // performing the actual search, sending the query to the Solr server
        $query = $this->modifyQuery($query, $searchRequest, $this->search);
        $response = $this->search->search($query, $offSet, null);

        if ($resultsPerPage === 0) {
            // when resultPerPage was forced to 0 we also set the numFound to 0 to hide results, e.g.
            // when results for the initial search should not be shown.
            $response->response->numFound = 0;
        }

        $this->processResponse($response);

        $this->addSearchResultsToResultSet($response, $resultSet);

        $resultSet->setResponse($response);
        $resultSet->setUsedPage($currentPage);
        $resultSet->setUsedResultsPerPage($resultsPerPage);
        $resultSet->setUsedAdditionalFilters($this->getAdditionalFilters());
        $resultSet->setUsedSearch($this->search);

        /** @var $searchResultReconstitutionProcessor ResultSetReconstitutionProcessor */
        $searchResultReconstitutionProcessor = GeneralUtility::makeInstance(ResultSetReconstitutionProcessor::class);
        $searchResultReconstitutionProcessor->process($resultSet);

        $resultSet = $this->getAutoCorrection($resultSet);

        return $this->handleSearchHook('afterSearch', $resultSet);
    }

    /**
     * @param SearchResultSet $searchResultSet
     * @return SearchResultSet
     */
    protected function getAutoCorrection(SearchResultSet $searchResultSet)
    {
        // no secondary search configured
        if (!$this->typoScriptConfiguration->getSearchSpellcheckingSearchUsingSpellCheckerSuggestion()) {
            return $searchResultSet;
        }

        // more then zero results
        if ($searchResultSet->getAllResultCount() > 0) {
            return $searchResultSet;
        }

        // no corrections present
        if (!$searchResultSet->getHasSpellCheckingSuggestions()) {
            return $searchResultSet;
        }

        $searchResultSet = $this->peformAutoCorrection($searchResultSet);

        return $searchResultSet;
    }

    /**
     * @param SearchResultSet $searchResultSet
     * @return SearchResultSet
     */
    protected function peformAutoCorrection(SearchResultSet $searchResultSet)
    {
        $searchRequest = $searchResultSet->getUsedSearchRequest();
        $suggestions = $searchResultSet->getSpellCheckingSuggestions();

        $maximumRuns = $this->typoScriptConfiguration->getSearchSpellcheckingNumberOfSuggestionsToTry(1);
        $runs = 0;

        foreach ($suggestions as $suggestion) {
            $runs++;

            $correction = $suggestion->getSuggestion();
            $initialQuery = $searchRequest->getRawUserQuery();

            $searchRequest->setRawQueryString($correction);
            $searchResultSet = $this->search($searchRequest);
            if ($searchResultSet->getAllResultCount() > 0) {
                $searchResultSet->setIsAutoCorrected(true);
                $searchResultSet->setCorrectedQueryString($correction);
                $searchResultSet->setInitialQueryString($initialQuery);
                break;
            }

            if ($runs > $maximumRuns) {
                break;
            }
        }
        return $searchResultSet;
    }

    /**
     * Allows to modify a query before eventually handing it over to Solr.
     *
     * @param Query $query The current query before it's being handed over to Solr.
     * @param SearchRequest $searchRequest The searchRequest, relevant in the current context
     * @param Search $search The search, relevant in the current context
     * @throws \UnexpectedValueException
     * @return Query The modified query that is actually going to be given to Solr.
     */
    protected function modifyQuery(Query $query, SearchRequest $searchRequest, Search $search)
    {
        // hook to modify the search query
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'] as $classReference) {
                $queryModifier = GeneralUtility::getUserObj($classReference);

                if ($queryModifier instanceof Modifier) {
                    if ($queryModifier instanceof SearchAware) {
                        $queryModifier->setSearch($search);
                    }

                    if ($queryModifier instanceof SearchRequestAware) {
                        $queryModifier->setSearchRequest($searchRequest);
                    }

                    $query = $queryModifier->modifyQuery($query);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($queryModifier) . ' must implement interface ' . Modifier::class,
                        1310387414
                    );
                }
            }
        }

        return $query;
    }

    /**
     * Retrieves a single document from solr by document id.
     *
     * @param string $documentId
     * @return SearchResult
     */
    public function getDocumentById($documentId)
    {
        /* @var $query Query */
        $query = GeneralUtility::makeInstance(Query::class, $documentId);
        $query->setQueryFields(QueryFields::fromString('id'));

        $response = $this->search->search($query, 0, 1);
        $this->processResponse($response);

        $resultDocument = isset($response->response->docs[0]) ? $response->response->docs[0] : null;
        return $resultDocument;
    }

    /**
     * This method is used to call the registered hooks during the search execution.
     *
     * @param string $eventName
     * @param SearchResultSet $resultSet
     * @return SearchResultSet
     */
    private function handleSearchHook($eventName, SearchResultSet $resultSet)
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$eventName])) {
            return $resultSet;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$eventName] as $classReference) {
            $afterSearchProcessor = GeneralUtility::getUserObj($classReference);
            if ($afterSearchProcessor instanceof SearchResultSetProcessor) {
                $afterSearchProcessor->process($resultSet);
            }
        }

        return $resultSet;
    }

    /**
     * @return SearchResultSet
     */
    public function getLastResultSet()
    {
        return $this->lastResultSet;
    }

    /**
     * This method returns true when the last search was executed with an empty query
     * string or whitespaces only. When no search was triggered it will return false.
     *
     * @return bool
     */
    public function getLastSearchWasExecutedWithEmptyQueryString()
    {
        $wasEmptyQueryString = false;
        if ($this->lastResultSet != null) {
            $wasEmptyQueryString = $this->lastResultSet->getUsedSearchRequest()->getRawUserQueryIsEmptyString();
        }

        return $wasEmptyQueryString;
    }

    /**
     * @param int $requestedPerPage
     */
    protected function setPerPageInSession($requestedPerPage)
    {
        $GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_solr_resultsPerPage', intval($requestedPerPage));
    }

    /**
     * @return mixed
     */
    protected function getPerPageFromSession()
    {
        return $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_solr_resultsPerPage');
    }

    /**
     * @return bool
     */
    protected function getInitialSearchIsConfigured()
    {
        return $this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchShowResultsOfInitialEmptyQuery() || $this->typoScriptConfiguration->getSearchInitializeWithQuery() || $this->typoScriptConfiguration->getSearchShowResultsOfInitialQuery();
    }

    /**
     * @return mixed
     */
    protected function getRegisteredSearchComponents()
    {
        return GeneralUtility::makeInstance(SearchComponentManager::class)->getSearchComponents();
    }

    /**
     * This method is used to reference the SearchResult object from the response in the SearchResultSet object.
     *
     * @param \Apache_Solr_Response $response
     * @param SearchResultSet $resultSet
     */
    protected function addSearchResultsToResultSet($response, $resultSet)
    {
        if (!is_array($response->response->docs)) {
            return;
        }

        foreach ($response->response->docs as $searchResult) {
            $resultSet->addSearchResult($searchResult);
        }
    }

    /**
     * @param string $rawQuery
     * @return Query|object
     */
    protected function getQueryInstance($rawQuery)
    {
        $query = GeneralUtility::makeInstance(Query::class, $rawQuery, $this->typoScriptConfiguration);
        return $query;
    }
}
