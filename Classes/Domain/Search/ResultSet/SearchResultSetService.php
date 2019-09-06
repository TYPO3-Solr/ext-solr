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
 *  the Free Software Foundation; either version 3 of the License, or
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
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\ResultParserRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use ApacheSolrForTypo3\Solr\Domain\Variants\VariantsProcessor;
use ApacheSolrForTypo3\Solr\Query\Modifier\Modifier;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\QueryAware;
use ApacheSolrForTypo3\Solr\Search\SearchAware;
use ApacheSolrForTypo3\Solr\Search\SearchComponentManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrIncompleteResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;

/**
 * The SearchResultSetService is responsible to build a SearchResultSet from a SearchRequest.
 * It encapsulates the logic to trigger a search in order to be able to reuse it in multiple places.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResultSetService
{

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
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @param TypoScriptConfiguration $configuration
     * @param Search $search
     * @param SolrLogManager $solrLogManager
     * @param SearchResultBuilder $resultBuilder
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(TypoScriptConfiguration $configuration, Search $search, SolrLogManager $solrLogManager = null, SearchResultBuilder $resultBuilder = null, QueryBuilder $queryBuilder = null)
    {
        $this->search = $search;
        $this->typoScriptConfiguration = $configuration;
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $this->searchResultBuilder = $resultBuilder ?? GeneralUtility::makeInstance(SearchResultBuilder::class);
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, /** @scrutinizer ignore-type */ $configuration, /** @scrutinizer ignore-type */ $solrLogManager);
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
     * Retrieves the used search instance.
     *
     * @return Search
     */
    public function getSearch()
    {
        return $this->search;
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
     * @return string
     */
    protected function getResultSetClassName()
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName ']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] : SearchResultSet::class;
    }

    /**
     * Performs a search and returns a SearchResultSet.
     *
     * @param SearchRequest $searchRequest
     * @return SearchResultSet
     */
    public function search(SearchRequest $searchRequest)
    {
        $resultSet = $this->getInitializedSearchResultSet($searchRequest);
        $this->lastResultSet = $resultSet;

        $resultSet = $this->handleSearchHook('beforeSearch', $resultSet);
        if ($this->shouldReturnEmptyResultSetWithoutExecutedSearch($searchRequest)) {
            $resultSet->setHasSearched(false);
            return $resultSet;
        }

        $query = $this->queryBuilder->buildSearchQuery($searchRequest->getRawUserQuery(), (int)$searchRequest->getResultsPerPage(), $searchRequest->getAdditionalFilters());
        $this->initializeRegisteredSearchComponents($query, $searchRequest);
        $resultSet->setUsedQuery($query);

        // performing the actual search, sending the query to the Solr server
        $query = $this->modifyQuery($query, $searchRequest, $this->search);
        $response = $this->doASearch($query, $searchRequest);

        if ((int)$searchRequest->getResultsPerPage() === 0) {
            // when resultPerPage was forced to 0 we also set the numFound to 0 to hide results, e.g.
            // when results for the initial search should not be shown.
            // @extensionScannerIgnoreLine
            $response->response->numFound = 0;
        }

        $resultSet->setHasSearched(true);
        $resultSet->setResponse($response);

        $this->getParsedSearchResults($resultSet);

        $resultSet->setUsedAdditionalFilters($this->queryBuilder->getAdditionalFilters());

        /** @var $variantsProcessor VariantsProcessor */
        $variantsProcessor = GeneralUtility::makeInstance(
            VariantsProcessor::class,
            /** @scrutinizer ignore-type */ $this->typoScriptConfiguration,
            /** @scrutinizer ignore-type */ $this->searchResultBuilder
        );
        $variantsProcessor->process($resultSet);

        /** @var $searchResultReconstitutionProcessor ResultSetReconstitutionProcessor */
        $searchResultReconstitutionProcessor = GeneralUtility::makeInstance(ResultSetReconstitutionProcessor::class);
        $searchResultReconstitutionProcessor->process($resultSet);

        $resultSet = $this->getAutoCorrection($resultSet);

        return $this->handleSearchHook('afterSearch', $resultSet);
    }

    /**
     * Uses the configured parser and retrieves the parsed search resutls.
     *
     * @param SearchResultSet $resultSet
     */
    protected function getParsedSearchResults($resultSet)
    {
        /** @var ResultParserRegistry $parserRegistry */
        $parserRegistry = GeneralUtility::makeInstance(ResultParserRegistry::class, /** @scrutinizer ignore-type */ $this->typoScriptConfiguration);
        $useRawDocuments = (bool)$this->typoScriptConfiguration->getValueByPathOrDefaultValue('plugin.tx_solr.features.useRawDocuments', false);
        $parserRegistry->getParser($resultSet)->parse($resultSet, $useRawDocuments);
    }

    /**
     * Evaluates conditions on the request and configuration and returns true if no search should be triggered and an empty
     * SearchResultSet should be returned.
     *
     * @param SearchRequest $searchRequest
     * @return bool
     */
    protected function shouldReturnEmptyResultSetWithoutExecutedSearch(SearchRequest $searchRequest)
    {
        if ($searchRequest->getRawUserQueryIsNull() && !$this->getInitialSearchIsConfigured()) {
            // when no rawQuery was passed or no initialSearch is configured, we pass an empty result set
            return true;
        }

        if ($searchRequest->getRawUserQueryIsEmptyString() && !$this->typoScriptConfiguration->getSearchQueryAllowEmptyQuery()) {
            // the user entered an empty query string "" or "  " and empty querystring is not allowed
            return true;
        }

        return false;
    }

    /**
     * Initializes the SearchResultSet from the SearchRequest
     *
     * @param SearchRequest $searchRequest
     * @return SearchResultSet
     */
    protected function getInitializedSearchResultSet(SearchRequest $searchRequest):SearchResultSet
    {
        /** @var $resultSet SearchResultSet */
        $resultSetClass = $this->getResultSetClassName();
        $resultSet = GeneralUtility::makeInstance($resultSetClass);

        $resultSet->setUsedSearchRequest($searchRequest);
        $resultSet->setUsedPage((int)$searchRequest->getPage());
        $resultSet->setUsedResultsPerPage((int)$searchRequest->getResultsPerPage());
        $resultSet->setUsedSearch($this->search);
        return $resultSet;
    }

    /**
     * Executes the search and builds a fake response for a current bug in Apache Solr 6.3
     *
     * @param Query $query
     * @param SearchRequest $searchRequest
     * @return ResponseAdapter
     */
    protected function doASearch($query, $searchRequest): ResponseAdapter
    {
        // the offset mulitplier is page - 1 but not less then zero
        $offsetMultiplier = max(0, $searchRequest->getPage() - 1);
        $offSet = $offsetMultiplier * (int)$searchRequest->getResultsPerPage();

        $response = $this->search->search($query, $offSet, null);
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
                $queryModifier = GeneralUtility::makeInstance($classReference);

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
        /* @var $query SearchQuery */
        $query = $this->queryBuilder->newSearchQuery($documentId)->useQueryFields(QueryFields::fromString('id'))->getQuery();
        $response = $this->search->search($query, 0, 1);
        $parsedData = $response->getParsedData();
        // @extensionScannerIgnoreLine
        $resultDocument = isset($parsedData->response->docs[0]) ? $parsedData->response->docs[0] : null;

        if (!$resultDocument instanceof Document) {
            throw new \UnexpectedValueException("Response did not contain a valid Document object");
        }

        return $this->searchResultBuilder->fromApacheSolrDocument($resultDocument);
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
            $afterSearchProcessor = GeneralUtility::makeInstance($classReference);
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
}
