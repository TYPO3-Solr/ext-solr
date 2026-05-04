<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

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
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * The concrete QueryBuilder contains all TYPO3 specific initialization logic of solr queries, for TYPO3.
 */
class QueryBuilder extends AbstractQueryBuilder
{
    /**
     * Additional filters, which will be added to the query, as well as to suggest queries.
     */
    protected array $additionalFilters = [];

    protected TypoScriptConfiguration $typoScriptConfiguration;

    protected SolrLogManager $logger;

    protected SiteHashService $siteHashService;

    public function __construct(
        ?TypoScriptConfiguration $configuration = null,
        ?SolrLogManager $solrLogManager = null,
        ?SiteHashService $siteHashService = null,
    ) {
        $this->typoScriptConfiguration = $configuration ?? Util::getSolrConfiguration();
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->siteHashService = $siteHashService ?? GeneralUtility::makeInstance(SiteHashService::class);
    }

    public function useTypoScriptConfiguration(TypoScriptConfiguration $typoScriptConfiguration): self
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration;
        return $this;
    }

    public function newSearchQuery(string $queryString): QueryBuilder
    {
        $this->queryToBuild = $this->getSearchQueryInstance($queryString);
        return $this;
    }

    public function newSuggestQuery(string $queryString): QueryBuilder
    {
        $this->queryToBuild = $this->getSuggestQueryInstance($queryString);
        return $this;
    }

    /**
     * Initializes the Query object and SearchComponents and returns
     * the initialized query object, when a search should be executed.
     */
    public function buildSearchQuery(
        string $rawQuery = '',
        int $resultsPerPage = 10,
        array $additionalFiltersFromRequest = [],
    ): Query {
        if ($this->typoScriptConfiguration->getLoggingQuerySearchWords()) {
            $this->logger->info('Received search query', [$rawQuery]);
        }

        if ($this->typoScriptConfiguration->isPureVectorSearchEnabled()) {
            $this->preparePureVectorSearch($rawQuery);
        } else {
            $this->newSearchQuery($rawQuery)
                ->useReturnFieldsFromTypoScript()
                ->useQueryFieldsFromTypoScript()
                ->useInitialQueryFromTypoScript()
                ->useFiltersFromTypoScript()
                ->useHighlightingFromTypoScript()
                ->usePhraseFieldsFromTypoScript()
                ->useBigramPhraseFieldsFromTypoScript()
                ->useTrigramPhraseFieldsFromTypoScript();
        }

        return $this
                ->setRawQueryTerm($rawQuery)
                ->useResultsPerPage($resultsPerPage)
                ->useFilterArray($additionalFiltersFromRequest)
                ->useFacetingFromTypoScript()
                ->useVariantsFromTypoScript()
                ->useGroupingFromTypoScript()
                ->useOmitHeader(false)
                ->getQuery();
    }

    protected function preparePureVectorSearch(string $rawQuery): self
    {
        $minSimiliarity = $this->typoScriptConfiguration->getMinimumVectorSimilarity();
        $topK = $this->typoScriptConfiguration->getTopKClosestVectorLimit();

        $this->newSearchQuery('*:*')
            ->useFiltersFromTypoScript()
            ->useFilter('{!frange l=' . $minSimiliarity . '}$q_vector', 'vectorRange')
            ->useSorting(new Sorting(true, '$q_vector', Sorting::SORT_DESC));

        $returnFieldsArray = $this->typoScriptConfiguration->getSearchQueryReturnFieldsAsArray(['*', 'score']);
        $returnFieldsArray[] = '$q_vector';
        $this->useReturnFields(ReturnFields::fromArray($returnFieldsArray));

        $this->queryToBuild->addParam(
            'q_vector',
            '{!knn_text_to_vector model=llm f=vector topK=' . $topK . '}' . $rawQuery,
        );

        return $this;
    }

    /**
     * Builds a SuggestQuery with all applied filters.
     */
    public function buildSuggestQuery(
        string $queryString,
        array $additionalFilters,
        int $requestedPageId,
        array $frontendUserGroupIds,
    ): SuggestQuery {
        $this->newSuggestQuery($queryString)
            ->useFiltersFromTypoScript()
            ->useSiteHashFromTypoScript($requestedPageId)
            ->useUserAccessGroups($frontendUserGroupIds)
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
     * @throws DBALException
     */
    public function buildPageQuery(int $pageId): Query
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
     * @throws DBALException
     */
    public function buildRecordQuery(string $type, int $uid, int $pageId): Query
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

    public function useSlopsFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useSlops(Slops::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Uses the configured boost queries from typoscript
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
     */
    public function useMinimumMatchFromTypoScript(): QueryBuilder
    {
        $searchConfiguration = $this->typoScriptConfiguration->getSearchConfiguration();
        if (!empty($searchConfiguration['query.']['minimumMatch'])) {
            return $this->useMinimumMatch($searchConfiguration['query.']['minimumMatch']);
        }

        return $this;
    }

    public function useTieParameterFromTypoScript(): QueryBuilder
    {
        $searchConfiguration = $this->typoScriptConfiguration->getSearchConfiguration();
        if (empty($searchConfiguration['query.']['tieParameter'])) {
            return $this;
        }

        return $this->useTieParameter((float)$searchConfiguration['query.']['tieParameter']);
    }

    /**
     * Applies the configured query fields from the typoscript configuration.
     */
    public function useQueryFieldsFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useQueryFields(QueryFields::fromString($this->typoScriptConfiguration->getSearchQueryQueryFields()));
    }

    /**
     * Applies the configured return fields from the typoscript configuration.
     */
    public function useReturnFieldsFromTypoScript(): AbstractQueryBuilder
    {
        $returnFieldsArray = $this->typoScriptConfiguration->getSearchQueryReturnFieldsAsArray(['*', 'score']);
        return $this->useReturnFields(ReturnFields::fromArray($returnFieldsArray));
    }

    /**
     * Can be used to apply the allowed sites from plugin.tx_solr.search.query.allowedSites to the query.
     */
    public function useSiteHashFromTypoScript(int $requestedPageId): QueryBuilder
    {
        $queryConfiguration = $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_solr.search.query.');
        $allowedSites = $this->siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration($requestedPageId, $queryConfiguration['allowedSites'] ?? '');
        return $this->useSiteHashFromAllowedSites($allowedSites);
    }

    /**
     * Can be used to apply a list of allowed sites to the query.
     */
    public function useSiteHashFromAllowedSites(string $allowedSites): QueryBuilder
    {
        $isAnySiteAllowed = trim($allowedSites) === '*';
        if ($isAnySiteAllowed) {
            // no filter required
            return $this;
        }

        $allowedSites = GeneralUtility::trimExplode(',', $allowedSites);
        $filters = [];
        foreach ($allowedSites as $site) {
            $siteHash = $this->siteHashService->getSiteHashForSiteIdentifier($site);
            $filters[] = 'siteHash:"' . $siteHash . '"';
        }

        $siteHashFilterString = implode(' OR ', $filters);
        return $this->useFilter($siteHashFilterString, 'siteHash');
    }

    /**
     * Can be used to filter the result on an applied list of user groups.
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
     */
    public function useFacetingFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useFaceting(Faceting::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured variants from the typoscript configuration on the query.
     */
    public function useVariantsFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useFieldCollapsing(FieldCollapsing::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured groupings from the typoscript configuration to the query.
     */
    public function useGroupingFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useGrouping(Grouping::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured highlighting from the typoscript configuration to the query.
     */
    public function useHighlightingFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useHighlighting(Highlighting::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured filters (page section and other from typoscript).
     * @todo: Method is widely used but {@link Filters::fromTypoScriptConfiguration()} does not take TypoScript into account
     */
    public function useFiltersFromTypoScript(): QueryBuilder
    {
        $filters = Filters::fromTypoScriptConfiguration($this->typoScriptConfiguration);
        $this->queryToBuild->setFilterQueries($filters->getValues());

        $this->useFilterArray($this->getAdditionalFilters());

        $searchQueryFilters = $this->typoScriptConfiguration->getSearchQueryFilterConfiguration();

        if (count($searchQueryFilters) <= 0) {
            return $this;
        }

        // special filter to limit search to specific page tree branches
        if (array_key_exists('__pageSections', $searchQueryFilters)) {
            if ($searchQueryFilters['__pageSections.'] ?? false) {
                $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $searchQueryFilters['__pageSections'] = $cObj->stdWrap(
                    $searchQueryFilters['__pageSections'],
                    $searchQueryFilters['__pageSections.'],
                );
            }
            $pageIds = GeneralUtility::trimExplode(',', (string)$searchQueryFilters['__pageSections']);
            $this->usePageSectionsFromPageIds($pageIds);
            $this->typoScriptConfiguration->removeSearchQueryFilterForPageSections();
        }

        return $this;
    }

    /**
     * Applies the configured elevation from the typoscript configuration.
     */
    public function useElevationFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useElevation(Elevation::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured spellchecking from the typoscript configuration.
     */
    public function useSpellcheckingFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useSpellchecking(Spellchecking::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the passed pageIds as __pageSection filter.
     */
    public function usePageSectionsFromPageIds(array $pageIds = []): QueryBuilder
    {
        $filters = [];

        /** @var PageUidToHierarchy $processor */
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
     */
    public function usePhraseFieldsFromTypoScript(): AbstractQueryBuilder
    {
        return $this->usePhraseFields(PhraseFields::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured bigram phrase fields from the typoscript configuration to the query.
     */
    public function useBigramPhraseFieldsFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useBigramPhraseFields(BigramPhraseFields::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Applies the configured trigram phrase fields from the typoscript configuration to the query.
     */
    public function useTrigramPhraseFieldsFromTypoScript(): AbstractQueryBuilder
    {
        return $this->useTrigramPhraseFields(TrigramPhraseFields::fromTypoScriptConfiguration($this->typoScriptConfiguration));
    }

    /**
     * Retrieves the configuration filters from the TypoScript configuration, except the __pageSections filter.
     */
    public function getAdditionalFilters(): array
    {
        // when we've built the additionalFilter once, we could return them
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

            $filterIsArray = isset($searchQueryFilters[$filterKey]) && is_array($searchQueryFilters[$filterKey]);
            if ($filterIsArray) {
                continue;
            }

            $hasSubConfiguration = isset($searchQueryFilters[$filterKey . '.']) && is_array($searchQueryFilters[$filterKey . '.']);
            if ($hasSubConfiguration) {
                $filter = $cObj->stdWrap($searchQueryFilters[$filterKey], $searchQueryFilters[$filterKey . '.']);
            }

            $this->additionalFilters[$filterKey] = $filter;
        }

        return $this->additionalFilters;
    }

    protected function setRawQueryTerm(string $rawSearchTerm): self
    {
        if (!$this->queryToBuild instanceof SearchQuery) {
            return $this;
        }

        $this->queryToBuild->setRawSearchTerm($rawSearchTerm);
        return $this;
    }

    protected function getSearchQueryInstance(string $rawQuery): SearchQuery
    {
        $query = GeneralUtility::makeInstance(SearchQuery::class);
        $query->setQuery($rawQuery);
        return $query;
    }

    protected function getSuggestQueryInstance(string $rawQuery): SuggestQuery
    {
        return GeneralUtility::makeInstance(SuggestQuery::class, $rawQuery, $this->typoScriptConfiguration);
    }
}
