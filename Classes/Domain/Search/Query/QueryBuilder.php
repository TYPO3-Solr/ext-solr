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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\BigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Elevation;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\FieldCollapsing;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Filters;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Grouping;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\PhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\QueryFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\ReturnFields;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Slops;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sortings;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Spellchecking;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\TrigramPhraseFields;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\FieldProcessor\PageUidToHierarchy;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * The concrete QueryBuilder contains all TYPO3 specific initialization logic of solr queries, for TYPO3.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query
 */
class QueryBuilder extends AbstractQueryBuilder {

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
    protected $typoScriptConfiguration = null;

    /**
     * @var SolrLogManager;
     */
    protected $logger = null;

    /**
     * @var SiteHashService
     */
    protected $siteHashService = null;

    /**
     * QueryBuilder constructor.
     * @param TypoScriptConfiguration|null $configuration
     * @param SolrLogManager|null $solrLogManager
     * @param SiteHashService|null $siteHashService
     */
    public function __construct(TypoScriptConfiguration $configuration = null, SolrLogManager $solrLogManager = null, SiteHashService $siteHashService = null)
    {
        $this->typoScriptConfiguration = $configuration ?? Util::getSolrConfiguration();
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
        $this->siteHashService = $siteHashService ?? GeneralUtility::makeInstance(SiteHashService::class);
    }

    /**
     * @param string $queryString
     * @return QueryBuilder
     */
    public function newSearchQuery($queryString): QueryBuilder
    {
        $this->queryToBuild = $this->getSearchQueryInstance($queryString);
        return $this;
    }

    /**
     * @param string $queryString
     * @return QueryBuilder
     */
    public function newSuggestQuery($queryString): QueryBuilder
    {
        $this->queryToBuild = $this->getSuggestQueryInstance($queryString);
        return $this;
    }

    /**
     * Initializes the Query object and SearchComponents and returns
     * the initialized query object, when a search should be executed.
     *
     * @param string|null $rawQuery
     * @param int $resultsPerPage
     * @param array $additionalFiltersFromRequest
     * @return SearchQuery
     */
    public function buildSearchQuery($rawQuery, $resultsPerPage = 10, array $additionalFiltersFromRequest = []) : SearchQuery
    {
        if ($this->typoScriptConfiguration->getLoggingQuerySearchWords()) {
            $this->logger->log(SolrLogManager::INFO, 'Received search query', [$rawQuery]);
        }

        /* @var $query SearchQuery */
        return $this->newSearchQuery($rawQuery)
                ->useResultsPerPage($resultsPerPage)
                ->useReturnFieldsFromTypoScript()
                ->useQueryFieldsFromTypoScript()
                ->useInitialQueryFromTypoScript()
                ->useFiltersFromTypoScript()
                ->useFilterArray($additionalFiltersFromRequest)
                ->useFacetingFromTypoScript()
                ->useVariantsFromTypoScript()
                ->useGroupingFromTypoScript()
                ->useHighlightingFromTypoScript()
                ->usePhraseFieldsFromTypoScript()
                ->useBigramPhraseFieldsFromTypoScript()
                ->useTrigramPhraseFieldsFromTypoScript()
                ->useOmitHeader(false)
                ->getQuery();
    }

    /**
     * Builds a SuggestQuery with all applied filters.
     *
     * @param string $queryString
     * @param array $additionalFilters
     * @param integer $requestedPageId
     * @param string $groupList
     * @return SuggestQuery
     */
    public function buildSuggestQuery(string $queryString, array $additionalFilters, int $requestedPageId, string $groupList) : SuggestQuery
    {
        $this->newSuggestQuery($queryString)
            ->useFiltersFromTypoScript()
            ->useSiteHashFromTypoScript($requestedPageId)
            ->useUserAccessGroups(explode(',', $groupList))
            ->useOmitHeader();


        if (!empty($additionalFilters)) {
            $this->useFilterArray($additionalFilters);
        }

        return $this->queryToBuild;
    }

    /**
     * Returns Query for Search which finds document for given page.
     * Note: The Connection is per language as recommended in ext-solr docs.
     *
     * @return Query
     */
    public function buildPageQuery($pageId)
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($pageId);

        return $this->newSearchQuery('')
            ->useQueryString('*:*')
            ->useFilter('(type:pages AND uid:' . $pageId . ') OR (*:* AND pid:' . $pageId . ' NOT type:pages)', 'type')
            ->useFilter('siteHash:' . $site->getSiteHash(), 'siteHash')
            ->useReturnFields(ReturnFields::fromString('*'))
            ->useSortings(Sortings::fromString('type asc, title asc'))
            ->useQueryType('standard')
            ->getQuery();
    }

    /**
     * Returns a query for single record
     *
     * @return Query
     */
    public function buildRecordQuery($type, $uid, $pageId): Query
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($pageId);

        return $this->newSearchQuery('')
            ->useQueryString('*:*')
            ->useFilter('type:' . $type . ' AND uid:' . $uid, 'type')
            ->useFilter('siteHash:' . $site->getSiteHash(), 'siteHash')
            ->useReturnFields(ReturnFields::fromString('*'))
            ->useSortings(Sortings::fromString('type asc, title asc'))
            ->useQueryType('standard')
            ->getQuery();
    }

    /**
     * @return QueryBuilder
     */
    public function useSlopsFromTypoScript(): QueryBuilder
    {
        return $this->useSlops(Slops::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Uses the configured boost queries from typoscript
     *
     * @return QueryBuilder
     */
    public function useBoostQueriesFromTypoScript(): QueryBuilder
    {
        $searchConfiguration = $this->typoScriptConfiguration->getSearchConfiguration();

        if (!empty($searchConfiguration['query.']['boostQuery'])) {
            return $this->useBoostQueries($searchConfiguration['query.']['boostQuery']);
        }

        if (!empty($searchConfiguration['query.']['boostQuery.'])) {
            $boostQueries = $searchConfiguration['query.']['boostQuery.'];
            return $this->useBoostQueries(array_values($boostQueries));
        }

        return $this;
    }

    /**
     * Uses the configured boostFunction from the typoscript configuration.
     *
     * @return QueryBuilder
     */
    public function useBoostFunctionFromTypoScript(): QueryBuilder
    {
        $searchConfiguration = $this->typoScriptConfiguration->getSearchConfiguration();
        if (!empty($searchConfiguration['query.']['boostFunction'])) {
            return $this->useBoostFunction($searchConfiguration['query.']['boostFunction']);
        }

        return $this;
    }

    /**
     * Uses the configured minimumMatch from the typoscript configuration.
     *
     * @return QueryBuilder
     */
    public function useMinimumMatchFromTypoScript(): QueryBuilder
    {
        $searchConfiguration = $this->typoScriptConfiguration->getSearchConfiguration();
        if (!empty($searchConfiguration['query.']['minimumMatch'])) {
            return $this->useMinimumMatch($searchConfiguration['query.']['minimumMatch']);
        }

        return $this;
    }

    /**
     * @return QueryBuilder
     */
    public function useTieParameterFromTypoScript(): QueryBuilder
    {
        $searchConfiguration = $this->typoScriptConfiguration->getSearchConfiguration();
        if (empty($searchConfiguration['query.']['tieParameter'])) {
            return $this;
        }

        return $this->useTieParameter($searchConfiguration['query.']['tieParameter']);
    }

    /**
     * Applies the configured query fields from the typoscript configuration.
     *
     * @return QueryBuilder
     */
    public function useQueryFieldsFromTypoScript(): QueryBuilder
    {
        return $this->useQueryFields(QueryFields::fromString($this->typoScriptConfiguration->getSearchQueryQueryFields()));
    }

    /**
     * Applies the configured return fields from the typoscript configuration.
     *
     * @return QueryBuilder
     */
    public function useReturnFieldsFromTypoScript(): QueryBuilder
    {
        $returnFieldsArray = (array)$this->typoScriptConfiguration->getSearchQueryReturnFieldsAsArray(['*', 'score']);
        return $this->useReturnFields(ReturnFields::fromArray($returnFieldsArray));
    }



    /**
     * Can be used to apply the allowed sites from plugin.tx_solr.search.query.allowedSites to the query.
     *
     * @param int $requestedPageId
     * @return QueryBuilder
     */
    public function useSiteHashFromTypoScript(int $requestedPageId): QueryBuilder
    {
        $queryConfiguration = $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_solr.search.query.', []);
        $allowedSites = $this->siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration($requestedPageId, $queryConfiguration['allowedSites']);
        return $this->useSiteHashFromAllowedSites($allowedSites);
    }

    /**
     * Can be used to apply a list of allowed sites to the query.
     *
     * @param string $allowedSites
     * @return QueryBuilder
     */
    public function useSiteHashFromAllowedSites($allowedSites): QueryBuilder
    {
        $isAnySiteAllowed = trim($allowedSites) === '*';
        if ($isAnySiteAllowed) {
            // no filter required
            return $this;
        }

        $allowedSites = GeneralUtility::trimExplode(',', $allowedSites);
        $filters = [];
        foreach ($allowedSites as $site) {
            $siteHash = $this->siteHashService->getSiteHashForDomain($site);
            $filters[] = 'siteHash:"' . $siteHash . '"';
        }

        $siteHashFilterString = implode(' OR ', $filters);
        return $this->useFilter($siteHashFilterString, 'siteHash');
    }

    /**
     * Can be used to filter the result on an applied list of user groups.
     *
     * @param array $groups
     * @return QueryBuilder
     */
    public function useUserAccessGroups(array $groups): QueryBuilder
    {
        $groups = array_map('intval', $groups);
        $groups[] = 0; // always grant access to public documents
        $groups = array_unique($groups);
        sort($groups, SORT_NUMERIC);

        $accessFilter = '{!typo3access}' . implode(',', $groups);
        $this->queryToBuild->removeFilterQuery('access');
        return $this->useFilter($accessFilter, 'access');
    }

    /**
     * Applies the configured initial query settings to set the alternative query for solr as required.
     *
     * @return QueryBuilder
     */
    public function useInitialQueryFromTypoScript(): QueryBuilder
    {
        if ($this->typoScriptConfiguration->getSearchInitializeWithEmptyQuery() || $this->typoScriptConfiguration->getSearchQueryAllowEmptyQuery()) {
            // empty main query, but using a "return everything"
            // alternative query in q.alt
            $this->useAlternativeQuery('*:*');
        }

        if ($this->typoScriptConfiguration->getSearchInitializeWithQuery()) {
            $this->useAlternativeQuery($this->typoScriptConfiguration->getSearchInitializeWithQuery());
        }

        return $this;
    }

    /**
     * Applies the configured facets from the typoscript configuration on the query.
     *
     * @return QueryBuilder
     */
    public function useFacetingFromTypoScript(): QueryBuilder
    {
        return $this->useFaceting(Faceting::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured variants from the typoscript configuration on the query.
     *
     * @return QueryBuilder
     */
    public function useVariantsFromTypoScript(): QueryBuilder
    {
        return $this->useFieldCollapsing(FieldCollapsing::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured groupings from the typoscript configuration to the query.
     *
     * @return QueryBuilder
     */
    public function useGroupingFromTypoScript(): QueryBuilder
    {
        return $this->useGrouping(Grouping::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured highlighting from the typoscript configuration to the query.
     *
     * @return QueryBuilder
     */
    public function useHighlightingFromTypoScript(): QueryBuilder
    {
        return $this->useHighlighting(Highlighting::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured filters (page section and other from typoscript).
     *
     * @return QueryBuilder
     */
    public function useFiltersFromTypoScript(): QueryBuilder
    {
        $filters = Filters::fromTypoScriptConfiguration($this->typoScriptConfiguration);
        $this->queryToBuild->setFilterQueries($filters->getValues());

        $this->useFilterArray($this->getAdditionalFilters());

        $searchQueryFilters = $this->typoScriptConfiguration->getSearchQueryFilterConfiguration();

        if (!is_array($searchQueryFilters) || count($searchQueryFilters) <= 0) {
            return $this;
        }

        // special filter to limit search to specific page tree branches
        if (array_key_exists('__pageSections', $searchQueryFilters)) {
            $pageIds = GeneralUtility::trimExplode(',', $searchQueryFilters['__pageSections']);
            $this->usePageSectionsFromPageIds($pageIds);
            $this->typoScriptConfiguration->removeSearchQueryFilterForPageSections();
        }

        return $this;
    }

    /**
     * Applies the configured elevation from the typoscript configuration.
     *
     * @return QueryBuilder
     */
    public function useElevationFromTypoScript(): QueryBuilder
    {
        return $this->useElevation(Elevation::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured spellchecking from the typoscript configuration.
     *
     * @return QueryBuilder
     */
    public function useSpellcheckingFromTypoScript(): QueryBuilder
    {
        return $this->useSpellchecking(Spellchecking::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the passed pageIds as __pageSection filter.
     *
     * @param array $pageIds
     * @return QueryBuilder
     */
    public function usePageSectionsFromPageIds(array $pageIds = []): QueryBuilder
    {
        $filters = [];

        /** @var $processor PageUidToHierarchy */
        $processor = GeneralUtility::makeInstance(PageUidToHierarchy::class);
        $hierarchies = $processor->process($pageIds);

        foreach ($hierarchies as $hierarchy) {
            $lastLevel = array_pop($hierarchy);
            $filters[] = 'rootline:"' . $lastLevel . '"';
        }

        $pageSectionsFilterString = implode(' OR ', $filters);
        return $this->useFilter($pageSectionsFilterString, 'pageSections');
    }

    /**
     * Applies the configured phrase fields from the typoscript configuration to the query.
     *
     * @return QueryBuilder
     */
    public function usePhraseFieldsFromTypoScript(): QueryBuilder
    {
        return $this->usePhraseFields(PhraseFields::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured bigram phrase fields from the typoscript configuration to the query.
     *
     * @return QueryBuilder
     */
    public function useBigramPhraseFieldsFromTypoScript(): QueryBuilder
    {
        return $this->useBigramPhraseFields(BigramPhraseFields::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured trigram phrase fields from the typoscript configuration to the query.
     *
     * @return QueryBuilder
     */
    public function useTrigramPhraseFieldsFromTypoScript(): QueryBuilder
    {
        return $this->useTrigramPhraseFields(TrigramPhraseFields::fromTypoScriptConfiguration($this->typoScriptConfiguration));
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
        if (!is_array($searchQueryFilters) || count($searchQueryFilters) <= 0) {
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
     * @return SearchQuery
     */
    protected function getSearchQueryInstance($rawQuery): SearchQuery
    {
        $query = GeneralUtility::makeInstance(SearchQuery::class);
        $query->setQuery($rawQuery);
        return $query;
    }

    /**
     * @param string $rawQuery
     * @return SuggestQuery
     */
    protected function getSuggestQueryInstance($rawQuery): SuggestQuery
    {
        $query = GeneralUtility::makeInstance(SuggestQuery::class, /** @scrutinizer ignore-type */ $rawQuery, /** @scrutinizer ignore-type */ $this->typoScriptConfiguration);

        return $query;
    }
}
