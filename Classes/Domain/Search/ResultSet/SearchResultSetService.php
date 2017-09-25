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
use ApacheSolrForTypo3\Solr\Domain\Variants\VariantsProcessor;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Query\Modifier\Modifier;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\QueryAware;
use ApacheSolrForTypo3\Solr\Search\SearchAware;
use ApacheSolrForTypo3\Solr\Search\SearchComponentManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrIncompleteResponseException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrInternalServerErrorException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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
     * @var Search
     */
    protected $search;

    /**
     * @var SearchResultSet
     */
    protected $lastResultSet = null;

    /**
     * @var boolean
     */
    protected $isSolrAvailable = false;

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var SolrLogManager;
     */
    protected $logger = null;

    /**
     * @var SearchResultBuilder
     */
    protected $searchResultBuilder;

    /**
     * @param TypoScriptConfiguration $configuration
     * @param Search $search
     * @param SolrLogManager $solrLogManager
     * @param SearchResultBuilder $resultBuilder
     */
    public function __construct(TypoScriptConfiguration $configuration, Search $search, SolrLogManager $solrLogManager = null, SearchResultBuilder $resultBuilder = null)
    {
        $this->search = $search;
        $this->typoScriptConfiguration = $configuration;
        $this->logger = is_null($solrLogManager) ? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__) : $solrLogManager;
        $this->searchResultBuilder = is_null($resultBuilder) ? GeneralUtility::makeInstance(SearchResultBuilder::class) : $resultBuilder;
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
     * Does post processing of the response.
     *
     * @param \Apache_Solr_Response $response The search's response.
     */
    protected function processResponse(\Apache_Solr_Response $response)
    {
        $this->wrapResultDocumentInResultObject($response);
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
        $parsedData = $response->getParsedData();

        if (!is_array($parsedData->response->docs)) {
            return;
        }

        $documents = $parsedData->response->docs;
        foreach ($documents as $key => $originalDocument) {
            $result = $this->searchResultBuilder->fromApacheSolrDocument($originalDocument);
            $documents[$key] = $result;
        }

        $parsedData->response->docs = $documents;
        $response->setParsedData($parsedData);
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
        $resultsPerPage = (int)$searchRequest->getResultsPerPage();
        $query = $this->getPreparedQuery($rawQuery, $resultsPerPage);
        $this->initializeRegisteredSearchComponents($query, $searchRequest);
        $resultSet->setUsedQuery($query);

        // the offset mulitplier is page - 1 but not less then zero
        $offsetMultiplier = max(0, $searchRequest->getPage() - 1);
        $offSet = $offsetMultiplier * $resultsPerPage;

        // performing the actual search, sending the query to the Solr server
        $query = $this->modifyQuery($query, $searchRequest, $this->search);
        $response = $this->doASearch($query, $offSet);

        if ($resultsPerPage === 0) {
            // when resultPerPage was forced to 0 we also set the numFound to 0 to hide results, e.g.
            // when results for the initial search should not be shown.
            $response->response->numFound = 0;
        }

        $this->processResponse($response);
        $this->addSearchResultsToResultSet($response, $resultSet);

        $resultSet->setResponse($response);
        $resultSet->setUsedPage((int)$searchRequest->getPage());
        $resultSet->setUsedResultsPerPage($resultsPerPage);
        $resultSet->setUsedAdditionalFilters($this->getAdditionalFilters());
        $resultSet->setUsedSearch($this->search);

        /** @var $variantsProcessor VariantsProcessor */
        $variantsProcessor = GeneralUtility::makeInstance(VariantsProcessor::class, $this->typoScriptConfiguration, $this->searchResultBuilder);
        $variantsProcessor->process($resultSet);

        /** @var $searchResultReconstitutionProcessor ResultSetReconstitutionProcessor */
        $searchResultReconstitutionProcessor = GeneralUtility::makeInstance(ResultSetReconstitutionProcessor::class);
        $searchResultReconstitutionProcessor->process($resultSet);

        $resultSet = $this->getAutoCorrection($resultSet);

        return $this->handleSearchHook('afterSearch', $resultSet);
    }

    /**
     * Executes the search and builds a fake response for a current bug in Apache Solr 6.3
     *
     * @param Query $query
     * @param int $offSet
     * @throws SolrCommunicationException
     * @return \Apache_Solr_Response
     */
    protected function doASearch($query, $offSet)
    {
        try {
            $response = $this->search->search($query, $offSet, null);
        } catch (SolrInternalServerErrorException $e) {
            // when variants are enable and the index is empty, we get a parse exception, because of a
            // Apache Solr Bug.
            // see: https://github.com/TYPO3-Solr/ext-solr/issues/668
            // @todo this try/catch block can be removed after upgrading to Apache Solr 6.4
            if (!$this->typoScriptConfiguration->getSearchVariants()) {
                throw $e;
            }

            $response = $e->getSolrResponse();

            $parsedData = new \stdClass();
            $parsedData->response = new \stdClass();
            $parsedData->response->docs = [];
            $parsedData->spellcheck = [];
            $parsedData->debug = [];
            $parsedData->responseHeader = [];
            $parsedData->facet_counts = [];
            $parsedData->facets = [];
            $response->setParsedData($parsedData);

        }

        if($response === null) {
            throw new SolrIncompleteResponseException('The response retrieved from solr was incomplete', 1505989678);
        }

        return $response;
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

        $parsedData = $response->getParsedData();
        $resultDocument = isset($parsedData->response->docs[0]) ? $parsedData->response->docs[0] : null;
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
        $parsedData = $response->getParsedData();
        if (!is_array($parsedData->response->docs)) {
            return;
        }

        foreach ($parsedData->response->docs as $searchResult) {
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
