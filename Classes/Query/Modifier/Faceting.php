<?php
namespace ApacheSolrForTypo3\Solr\Query\Modifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Faceting as FacetingBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Modifies a query to add faceting parameters
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author Sebastian Kurfuerst <sebastian@typo3.org>
 */
class Faceting implements Modifier, SearchRequestAware
{

    /**
     * @var FacetRegistry
     */
    protected $facetRegistry = null;

    /**
     * @var SearchRequest
     */
    protected $searchRequest;

    /**
     * @param FacetRegistry $facetRegistry
     */
    public function __construct(FacetRegistry $facetRegistry = null)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->facetRegistry = $facetRegistry ?? $objectManager->get(FacetRegistry::class);
    }

    /**
     * @param SearchRequest $searchRequest
     */
    public function setSearchRequest(SearchRequest $searchRequest)
    {
        $this->searchRequest = $searchRequest;
    }

    /**
     * Modifies the given query and adds the parameters necessary for faceted
     * search.
     *
     * @param Query $query The query to modify
     * @return Query The modified query with faceting parameters
     */
    public function modifyQuery(Query $query)
    {
        $typoScriptConfiguration = $this->searchRequest->getContextTypoScriptConfiguration();
        $faceting = FacetingBuilder::fromTypoScriptConfiguration($typoScriptConfiguration);

        $allFacets = $typoScriptConfiguration->getSearchFacetingFacets();
        $facetParameters = $this->buildFacetingParameters($allFacets, $typoScriptConfiguration);
        foreach ($facetParameters as $facetParameter => $value) {
            if(strtolower($facetParameter) === 'facet.field') {
                $faceting->setFields($value);
            } else {
                $faceting->addAdditionalParameter($facetParameter, $value);
            }
        }

        $searchArguments = $this->searchRequest->getArguments();
        if (!is_array($searchArguments)) {
            return $query;
        }

        $keepAllFacetsOnSelection = $typoScriptConfiguration->getSearchFacetingKeepAllFacetsOnSelection();
        $facetFilters = $this->addFacetQueryFilters($searchArguments, $allFacets, $keepAllFacetsOnSelection);

        $queryBuilder = new QueryBuilder($typoScriptConfiguration);
        $queryBuilder->startFrom($query)->useFaceting($faceting)->useFilterArray($facetFilters);
        return $query;
    }

    /**
     * Delegates the parameter building to specialized functions depending on
     * the type of facet to add.
     *
     */
    protected function buildFacetingParameters($allFacets, TypoScriptConfiguration $typoScriptConfiguration)
    {
        $facetParameters = [];

        foreach ($allFacets as $facetName => $facetConfiguration) {
            $facetName = substr($facetName, 0, -1);
            $type = isset($facetConfiguration['type']) ? $facetConfiguration['type'] : 'options';
            $facetParameterBuilder = $this->facetRegistry->getPackage($type)->getQueryBuilder();

            if (is_null($facetParameterBuilder)) {
                throw new \InvalidArgumentException('No query build configured for facet ' . htmlspecialchars($facetName));
            }

            $facetParameters = array_merge_recursive($facetParameters, $facetParameterBuilder->build($facetName, $typoScriptConfiguration));
        }

        return $facetParameters;
    }

    /**
     * Adds filters specified through HTTP GET as filter query parameters to
     * the Solr query.
     *
     * @param array $resultParameters
     * @param array $allFacets
     * @param bool $keepAllFacetsOnSelection
     * @return array
     */
    protected function addFacetQueryFilters($resultParameters, $allFacets, $keepAllFacetsOnSelection)
    {
        $facetFilters = [];

        if (!is_array($resultParameters['filter'])) {
            return $facetFilters;
        }

        $filtersByFacetName = $this->getFiltersByFacetName($resultParameters, $allFacets);

        foreach ($filtersByFacetName as $facetName => $filterValues) {
            $facetConfiguration = $allFacets[$facetName . '.'];
            $tag = $this->getFilterTag($facetConfiguration, $keepAllFacetsOnSelection);
            $filterParts = $this->getFilterParts($facetConfiguration, $facetName, $filterValues);
            $operator = ($facetConfiguration['operator'] === 'OR') ? ' OR ' : ' AND ';
            $facetFilters[$facetName] = $tag . '(' . implode($operator, $filterParts) . ')';
        }

        return $facetFilters;
    }

    /**
     * Builds the tag part of the query depending on the keepAllOptionsOnSelection configuration or the global configuration
     * keepAllFacetsOnSelection.
     *
     * @param array $facetConfiguration
     * @param boolean $keepAllFacetsOnSelection
     * @return string
     */
    protected function getFilterTag($facetConfiguration, $keepAllFacetsOnSelection)
    {
        $tag = '';
        if ($facetConfiguration['keepAllOptionsOnSelection'] == 1 || $facetConfiguration['addFieldAsTag'] == 1 || $keepAllFacetsOnSelection) {
            $tag = '{!tag=' . addslashes($facetConfiguration['field']) . '}';
        }

        return $tag;
    }

    /**
     * This method is used to build the filter parts of the query.
     *
     * @param array $facetConfiguration
     * @param string $facetName
     * @param array $filterValues
     * @return array
     */
    protected function getFilterParts($facetConfiguration, $facetName, $filterValues)
    {
        $filterParts = [];

        $type = isset($facetConfiguration['type']) ? $facetConfiguration['type'] : 'options';
        $filterEncoder = $this->facetRegistry->getPackage($type)->getUrlDecoder();

        if (is_null($filterEncoder)) {
            throw new \InvalidArgumentException('No encoder configured for facet ' . htmlspecialchars($facetName));
        }

        foreach ($filterValues as $filterValue) {
            $filterOptions = $facetConfiguration[$facetConfiguration['type'] . '.'];
            if (empty($filterOptions)) {
                $filterOptions = [];
            }

            $filterValue = $filterEncoder->decode($filterValue, $filterOptions);
            $filterParts[] = $facetConfiguration['field'] . ':' . $filterValue;
        }

        return $filterParts;
    }

    /**
     * Groups facet values by facet name.
     *
     * @param array $resultParameters
     * @param array $allFacets
     * @return array
     */
    protected function getFiltersByFacetName($resultParameters, $allFacets)
    {
        // format for filter URL parameter:
        // tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
        $filters = array_map('urldecode', $resultParameters['filter']);
        // $filters look like ['name:value1','name:value2','fieldname2:foo']
        $configuredFacets = $this->getFacetNamesWithConfiguredField($allFacets);
        // first group the filters by facetName - so that we can
        // decide later whether we need to do AND or OR for multiple
        // filters for a certain facet/field
        // $filtersByFacetName look like ['name' =>  ['value1', 'value2'], 'fieldname2' => ['foo']]
        $filtersByFacetName = [];
        foreach ($filters as $filter) {
            // only split by the first colon to allow using colons in the filter value itself
            list($filterFacetName, $filterValue) = explode(':', $filter, 2);
            if (in_array($filterFacetName, $configuredFacets)) {
                $filtersByFacetName[$filterFacetName][] = $filterValue;
            }
        }

        return $filtersByFacetName;
    }

    /**
     * Gets the facets as configured through TypoScript
     *
     * @param array $allFacets
     * @return array An array of facet names as specified in TypoScript
     */
    protected function getFacetNamesWithConfiguredField(array $allFacets)
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
