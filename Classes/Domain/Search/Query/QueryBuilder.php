<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\SuggestQuery;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * The QueryBuilder is responsible to build solr queries, that are used in the extension to query the solr server.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query
 */
class QueryBuilder {

    /**
     * Additional filters, which will be added to the query, as well as to
     * suggest queries.
     *
     * @var array
     */
    protected $additionalFilters = [];

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var SolrLogManager;
     */
    protected $logger = null;

    /**
     * QueryBuilder constructor.
     */
    public function __construct(TypoScriptConfiguration $configuration, SolrLogManager $solrLogManager = null)
    {
        $this->typoScriptConfiguration = $configuration;
        $this->logger = is_null($solrLogManager) ? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__) : $solrLogManager;
    }

    /**
     * Initializes the Query object and SearchComponents and returns
     * the initialized query object, when a search should be executed.
     *
     * @param string|null $rawQuery
     * @param int $resultsPerPage
     * @return Query
     */
    public function buildSearchQuery($rawQuery, $resultsPerPage) : Query
    {
        /* @var $query Query */
        $query = $this->getQueryInstance($rawQuery);

        $this->applyPageSectionsRootLineFilter($query);

        if ($this->typoScriptConfiguration->getLoggingQuerySearchWords()) {
            $this->logger->log(SolrLogManager::INFO, 'Received search query', [$rawQuery]);
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
     * Builds a SuggestQuery with all applied filters.
     *
     * @param string $queryString
     * @param string $additionalFilters
     * @param integer $requestId
     * @param string $groupList
     * @return SuggestQuery
     */
    public function buildSuggestQuery(string $queryString, string $additionalFilters, int $requestId, string $groupList) : SuggestQuery
    {
        $suggestQuery = GeneralUtility::makeInstance(SuggestQuery::class, $queryString);

        $allowedSitesConfig = $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_solr.search.query.', []);
        $siteService = GeneralUtility::makeInstance(SiteHashService::class);
        $allowedSites = $siteService->getAllowedSitesForPageIdAndAllowedSitesConfiguration($requestId, $allowedSitesConfig['allowedSites']);
        $suggestQuery->setUserAccessGroups(explode(',', $groupList));
        $suggestQuery->setSiteHashFilter($allowedSites);
        $suggestQuery->setOmitHeader();

        if (!empty($allowedSitesConfig['filter.'])) {
            foreach ($allowedSitesConfig['filter.'] as $additionalFilter) {
                $suggestQuery->addFilter($additionalFilter);
            }
        }

        if (!empty($additionalFilters)) {
            $additionalFilters = json_decode($additionalFilters);
            foreach ($additionalFilters as $additionalFilter) {
                $suggestQuery->addFilter($additionalFilter);
            }
        }

        return $suggestQuery;
    }

    /**
     * Returns Query for Search which finds document for given page.
     * Note: The Connection is per language as recommended in ext-solr docs.
     *
     * @return Query
     */
    public function buildPageQuery($pageId)
    {
        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($pageId);
        /* @var Query $query */
        $query = GeneralUtility::makeInstance(Query::class, '');
        $query->setQueryType('standard');
        $query->useRawQueryString(true);
        $query->setQueryString('*:*');
        $query->getFilters()->add('(type:pages AND uid:' . $pageId . ') OR (*:* AND pid:' . $pageId . ' NOT type:pages)');
        $query->getFilters()->add('siteHash:' . $site->getSiteHash());
        $query->getReturnFields()->add('*');
        $query->setSorting('type asc, title asc');

        return $query;
    }


    /**
     * Returns a query for single record
     *
     * @return Query
     */
    public function buildRecordQuery($type, $uid, $pageId): Query
    {
        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($pageId);
        /* @var Query $query */
        $query = GeneralUtility::makeInstance(Query::class, '');
        $query->setQueryType('standard');
        $query->useRawQueryString(true);
        $query->setQueryString('*:*');
        $query->getFilters()->add('type:' . $type . ' AND uid:' . $uid);
        $query->getFilters()->add('siteHash:' . $site->getSiteHash());
        $query->getReturnFields()->add('*');
        $query->setSorting('type asc, title asc');

        return $query;
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
    public function getAdditionalFilters() : array
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
     * @param string $rawQuery
     * @return Query|object
     */
    protected function getQueryInstance($rawQuery)
    {
        $query = GeneralUtility::makeInstance(Query::class, $rawQuery, $this->typoScriptConfiguration);
        return $query;
    }
}