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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Plugin\PluginAware;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Response\Processor\ResponseProcessor;
use ApacheSolrForTypo3\Solr\Search\QueryAware;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * The SearchResultSetService is responsible to build a SearchResultSet from a SearchRequest.
 * It encapsulates the logic to trigger a search in order to be able to reuse it in multiple places.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class SearchResultSetService
{
    /**
     * Additional filters, which will be added to the query, as well as to
     * suggest queries.
     *
     * @var array
     */
    protected $additionalFilters = array();

    /**
     * Track, if the number of results per page has been changed by the current request
     *
     * @var boolean
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
     * @var AbstractPlugin
     */
    protected $parentPlugin;

    /**
     * @var bool
     */
    protected $useQueryAwareComponents = true;

    /**
     * @var bool
     */
    protected $usePluginAwareComponents = true;

    /**
     * @var
     */
    protected $isSolrAvailable = false;

    /**
     * @param TypoScriptConfiguration $configuration
     * @param Search $search
     * @param AbstractPlugin $parentPlugin (optional parent plugin, needed for plugin aware components)
     */
    public function __construct(TypoScriptConfiguration $configuration, Search $search, AbstractPlugin $parentPlugin = null)
    {
        $this->search = $search;
        $this->typoScriptConfiguration = $configuration;
        $this->parentPlugin = $parentPlugin;
    }

    /**
     * @return AbstractPlugin
     */
    public function getParentPlugin()
    {
        return $this->parentPlugin;
    }

    /**
     * @param bool $useCache
     * @return bool
     */
    public function getIsSolrAvailable($useCache = true)
    {
        if (!$useCache) {
            return $this->search->ping();
        }

        if ($this->isSolrAvailable === true) {
            return true;
        }

        $this->isSolrAvailable = $this->search->ping();
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
     * @param boolean $usePluginAwareComponents
     */
    public function setUsePluginAwareComponents($usePluginAwareComponents)
    {
        $this->usePluginAwareComponents = $usePluginAwareComponents;
    }

    /**
     * @return boolean
     */
    public function getUsePluginAwareComponents()
    {
        return $this->usePluginAwareComponents;
    }

    /**
     * @param boolean $useQueryAwareComponents
     */
    public function setUseQueryAwareComponents($useQueryAwareComponents)
    {
        $this->useQueryAwareComponents = $useQueryAwareComponents;
    }

    /**
     * @return boolean
     */
    public function getUseQueryAwareComponents()
    {
        return $this->useQueryAwareComponents;
    }

    /**
     * Initializes the Query object and SearchComponents and returns
     * the initialized query object, when a search should be executed.
     *
     * @param string $rawQuery
     * @param integer $resultsPerPage
     * @return Query
     */
    protected function getPreparedQuery($rawQuery, $resultsPerPage)
    {
        /* @var $query Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', $rawQuery);

        $this->applyPageSectionsRootLineFilter($query);

        if ($this->typoScriptConfiguration->getLoggingQuerySearchWords()) {
            GeneralUtility::devLog('received search query', 'solr', 0, array($rawQuery));
        }

        $query->setResultsPerPage($resultsPerPage);

        $this->initializeRegisteredSearchComponents($query);

        if ($this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchQueryAllowEmptyQuery()) {
            // empty main query, but using a "return everything"
            // alternative query in q.alt
            $query->setAlternativeQuery('*:*');
        }

        if ($this->typoScriptConfiguration->getSearchInitializeWithQuery()) {
            $query->setAlternativeQuery($this->typoScriptConfiguration->getSearchInitializeWithQuery());
        }

        foreach ($this->getAdditionalFilters() as $additionalFilter) {
            $query->addFilter($additionalFilter);
        }

        return $query;
    }

    /**
     * @param Query $query
     */
    protected function initializeRegisteredSearchComponents(Query $query)
    {
        $searchComponents = $this->getRegisteredSearchComponents();
        foreach ($searchComponents as $searchComponent) {
            $searchComponent->setSearchConfiguration($this->typoScriptConfiguration->getSearchConfiguration());

            if ($searchComponent instanceof QueryAware && $this->useQueryAwareComponents) {
                $searchComponent->setQuery($query);
            }

            if ($searchComponent instanceof PluginAware && $this->usePluginAwareComponents) {
                $searchComponent->setParentPlugin($this->parentPlugin);
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
     * @param string $rawQuery
     * @param integer|null $requestedPerPage
     * @return int number of results to show per page
     */
    protected function getNumberOfResultsPerPage($rawQuery, $requestedPerPage = null)
    {
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

        if ($this->shouldHideResultsFromInitialSearch($rawQuery)) {
            // initialize search with an empty query, which would by default return all documents
            // anyway, tell Solr to not return any result documents
            // Solr will still return facets though
            $currentNumberOfResultsShown = 0;
        }

        return $currentNumberOfResultsShown;
    }

    /**
     * Provides a hook for other classes to process the search's response.
     *
     * @param string $rawQuery
     * @param Query $query The query that has been searched for.
     * @param \Apache_Solr_Response $response The search's response.
     */
    protected function processResponse($rawQuery, Query $query, \Apache_Solr_Response &$response)
    {
        if ($this->shouldHideResultsFromInitialSearch($rawQuery)) {
            // explicitly set number of results to 0 as we just wanted
            // facets and the like according to configuration
            // @see getNumberOfResultsPerPage()
            $response->response->numFound = 0;
        }

        $this->wrapResultDocumentInResultObject($response);

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'] as $classReference) {
                $responseProcessor = GeneralUtility::getUserObj($classReference);
                if ($responseProcessor instanceof ResponseProcessor) {
                    $responseProcessor->processResponse($query, $response);
                }
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
     * @throws \InvalidArgumentException
     */
    protected function wrapResultDocumentInResultObject(\Apache_Solr_Response &$response)
    {
        if (!is_array($response->response->docs)) {
            return;
        }

        $searchResultClassName = $this->getResultClassName();
        foreach ($response->response->docs as $key => $originalDocument) {
            $result = GeneralUtility::makeInstance($searchResultClassName, $originalDocument);
            if (!$result instanceof SearchResult) {
                throw new \InvalidArgumentException("Could not create result object with class: ".(string) $searchResultClassName);
            }

            $response->response->docs[$key] = $result;
        }
    }

    /**
     * @return string
     */
    protected function getResultClassName()
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName ']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] :
            'ApacheSolrForTypo3\\Solr\\Domain\\Search\\ResultSet\\SearchResult';
    }

    /**
     * @return string
     */
    protected function getResultSetClassName()
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName ']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] :
            'ApacheSolrForTypo3\\Solr\\Domain\\Search\\ResultSet\\SearchResultSet';
    }

    /**
     * Checks it the results should be hidden in the response.
     *
     * @param $rawQuery
     * @return bool
     */
    protected function shouldHideResultsFromInitialSearch($rawQuery)
    {
        return ($this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchInitializeWithQuery()) && !$this->typoScriptConfiguration->getSearchShowResultsOfInitialEmptyQuery() && !$this->typoScriptConfiguration->getSearchShowResultsOfInitialQuery() && empty($rawQuery);
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
            return array();
        }

        $cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');

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
        $resultsPerPage = $this->getNumberOfResultsPerPage($rawQuery, $searchRequest->getResultsPerPage());
        $query = $this->getPreparedQuery($rawQuery, $resultsPerPage);

        $resultSet->setUsedQuery($query);

        $currentPage = max(0, $searchRequest->getPage());
        // if the number of results per page has been changed by the current request, reset the pagebrowser
        if ($this->resultsPerPageChanged) {
            $currentPage = 0;
        }

        $offSet = $currentPage * $resultsPerPage;
        // performing the actual search, sending the query to the Solr server
        $response = $this->search->search($query, $offSet, null);
        $this->processResponse($rawQuery, $query, $response);

        $resultSet->setResponse($response);
        $resultSet->setUsedPage($currentPage);
        $resultSet->setUsedResultsPerPage($resultsPerPage);
        $resultSet->setUsedAdditionalFilters($this->getAdditionalFilters());
        $resultSet->setUsedSearch($this->search);

        return $this->handleSearchHook('afterSearch', $resultSet);
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
     * @return \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet
     */
    public function getLastResultSet()
    {
        return $this->lastResultSet;
    }

    /**
     * This method returns true when the last search was exectued with an empty query
     * string or whitspaces only. When no search was triggered it will return false.
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
     * @param $requestedPerPage
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
        return GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search\\SearchComponentManager')->getSearchComponents();
    }
}
