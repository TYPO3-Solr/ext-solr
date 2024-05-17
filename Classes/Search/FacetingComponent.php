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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting as FacetingBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\InvalidFacetPackageException;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\InvalidQueryBuilderException;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\InvalidUrlDecoderException;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Modifies a query to add faceting parameters
 */
class FacetingComponent implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected FacetRegistry $facetRegistry;

    public function __construct(FacetRegistry $facetRegistry)
    {
        $this->facetRegistry = $facetRegistry;
    }

    /**
     * Modifies the given query and adds the parameters necessary for faceted
     * search.
     *
     * @param AfterSearchQueryHasBeenPreparedEvent $event
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        if (!($event->getTypoScriptConfiguration()->getSearchConfiguration()['faceting'] ?? false)) {
            return;
        }

        $typoScriptConfiguration = $event->getSearchRequest()->getContextTypoScriptConfiguration();

        $faceting = FacetingBuilder::fromTypoScriptConfiguration($typoScriptConfiguration);

        $allFacets = $typoScriptConfiguration->getSearchFacetingFacets();
        $facetParameters = $this->buildFacetingParameters($allFacets, $typoScriptConfiguration);
        foreach ($facetParameters as $facetParameter => $value) {
            if (strtolower($facetParameter) === 'facet.field') {
                $faceting->setFields($value);
            } else {
                $faceting->addAdditionalParameter($facetParameter, $value);
            }
        }

        $keepAllFacetsOnSelection = $typoScriptConfiguration->getSearchFacetingKeepAllFacetsOnSelection();
        $facetFilters = $this->addFacetQueryFilters($event->getSearchRequest(), $keepAllFacetsOnSelection, $allFacets);

        $queryBuilder = new QueryBuilder($typoScriptConfiguration);
        $queryBuilder->startFrom($event->getQuery())->useFaceting($faceting)->useFilterArray($facetFilters);
    }

    /**
     * Modifies the given query and adds the parameters necessary for faceted
     * search.
     *
     * @param Query $query The query to modify
     *
     * @return Query The modified query with faceting parameters
     *
     * @throws InvalidFacetPackageException
     * @throws InvalidQueryBuilderException
     * @throws InvalidUrlDecoderException
     */
    /**
     * Delegates the parameter building to specialized functions depending on
     * the type of facet to add.
     *
     * @throws InvalidFacetPackageException
     * @throws InvalidQueryBuilderException
     */
    protected function buildFacetingParameters(array $allFacets, TypoScriptConfiguration $typoScriptConfiguration): array
    {
        $facetParameters = [];
        foreach ($allFacets as $facetName => $facetConfiguration) {
            $facetName = substr($facetName, 0, -1);
            $type = $facetConfiguration['type'] ?? 'options';
            $facetParameterBuilder = $this->facetRegistry->getPackage($type)->getQueryBuilder();

            $facetParameters[] = $facetParameterBuilder->build($facetName, $typoScriptConfiguration);
        }

        return array_merge_recursive(...$facetParameters);
    }

    /**
     * Adds filters specified through HTTP GET as filter query parameters to
     * the Solr query.
     *
     * @throws InvalidFacetPackageException
     * @throws InvalidUrlDecoderException
     */
    protected function addFacetQueryFilters(SearchRequest $searchRequest, bool $keepAllFacetsOnSelection, ?array $allFacets): array
    {
        $resultParameters = $searchRequest->getArguments();
        $facetFilters = [];

        if (!is_array($resultParameters['filter'] ?? null)) {
            return $facetFilters;
        }

        $filtersByFacetName = $this->getFiltersByFacetName($searchRequest, $allFacets);

        foreach ($filtersByFacetName as $facetName => $filterValues) {
            $facetConfiguration = $allFacets[$facetName . '.'];
            $tag = $this->getFilterTag($facetConfiguration, $keepAllFacetsOnSelection);
            $filterParts = $this->getFilterParts($facetConfiguration, $facetName, $filterValues);

            if (!empty($filterParts)) {
                $operator = (($facetConfiguration['operator'] ?? null) === 'OR') ? ' OR ' : ' AND ';
                $facetFilters[$facetName] = $tag . '(' . implode($operator, $filterParts) . ')';
            }
        }

        return $facetFilters;
    }

    /**
     * Builds the tag part of the query depending on the keepAllOptionsOnSelection configuration or the global configuration
     * keepAllFacetsOnSelection.
     */
    protected function getFilterTag(array $facetConfiguration, bool $keepAllFacetsOnSelection): string
    {
        $tag = '';
        if (
            (int)($facetConfiguration['keepAllOptionsOnSelection'] ?? 0) === 1
            || (int)($facetConfiguration['addFieldAsTag'] ?? 0) === 1
            || $keepAllFacetsOnSelection
        ) {
            $tag = '{!tag=' . addslashes($facetConfiguration['field']) . '}';
        }

        return $tag;
    }

    /**
     * This method is used to build the filter parts of the query.
     *
     * @throws InvalidFacetPackageException
     * @throws InvalidUrlDecoderException
     */
    protected function getFilterParts(array $facetConfiguration, string $facetName, array $filterValues): array
    {
        $filterParts = [];

        $type = $facetConfiguration['type'] ?? 'options';
        $filterEncoder = $this->facetRegistry->getPackage($type)->getUrlDecoder();

        foreach ($filterValues as $filterValue) {
            $filterOptions = isset($facetConfiguration['type']) ? ($facetConfiguration[$facetConfiguration['type'] . '.'] ?? null) : null;
            if (empty($filterOptions)) {
                $filterOptions = [];
            }

            $filterValue = $filterEncoder->decode($filterValue, $filterOptions);
            if (($facetConfiguration['field'] ?? '') !== '' && $filterValue !== '') {
                $filterParts[] = $facetConfiguration['field'] . ':' . $filterValue;
            } else {
                $this->logger->warning('Invalid filter options found, skipping.', ['facet' => $facetName, 'configuration' => $facetConfiguration]);
            }
        }

        return $filterParts;
    }

    /**
     * Groups facet values by facet name.
     */
    protected function getFiltersByFacetName(SearchRequest $searchRequest, array $allFacets): array
    {
        $resultParameters = $searchRequest->getArguments();
        // format for filter URL parameter:
        // tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
        $filters = array_map('rawurldecode', $resultParameters['filter']);
        // $filters look like ['name:value1','name:value2','fieldname2:foo']
        $configuredFacets = $this->getFacetNamesWithConfiguredField($allFacets);
        // first group the filters by facetName - so that we can
        // decide later whether we need to do AND or OR for multiple
        // filters for a certain facet/field
        // $filtersByFacetName look like ['name' =>  ['value1', 'value2'], 'fieldname2' => ['foo']]
        $filtersByFacetName = [];
        if (
            ($typoScriptConfiguration = $searchRequest->getContextTypoScriptConfiguration())
            && $typoScriptConfiguration instanceof TypoScriptConfiguration
            && $typoScriptConfiguration->getSearchFacetingUrlParameterStyle() === UrlFacetContainer::PARAMETER_STYLE_ASSOC
        ) {
            $filters = array_keys($filters);
        }

        foreach ($filters as $filter) {
            if (!str_contains($filter, ':')) {
                continue;
            }

            // only split by the first colon to allow using colons in the filter value itself
            [$filterFacetName, $filterValue] = explode(':', $filter, 2);
            if (in_array($filterFacetName, $configuredFacets, true)) {
                $filtersByFacetName[$filterFacetName][] = $filterValue;
            }
        }

        return $filtersByFacetName;
    }

    /**
     * Gets the facets as configured through TypoScript
     *
     * @return array An array of facet names as specified in TypoScript
     */
    protected function getFacetNamesWithConfiguredField(array $allFacets): array
    {
        $facets = [];

        foreach ($allFacets as $facetName => $facetConfiguration) {
            $facetName = substr($facetName, 0, -1);

            if (empty($facetConfiguration['field'])) {
                // TODO later check for query and date, too
                continue;
            }

            $facets[] = $facetName;
        }

        return $facets;
    }
}
