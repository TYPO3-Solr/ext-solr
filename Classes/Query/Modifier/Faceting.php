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

use ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Modifies a query to add faceting parameters
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author Sebastian Kurfuerst <sebastian@typo3.org>
 */
class Faceting implements Modifier
{
    /**
     * @var \ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration|array
     */
    protected $configuration;

    protected $facetParameters = [];

    protected $facetFilters = [];

    protected $facetRendererFactory = null;

    /**
     * @var array
     */
    protected $allConfiguredFacets = [];

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     */
    public function __construct($solrConfiguration = null)
    {
        if (!is_null($solrConfiguration)) {
            $this->configuration = $solrConfiguration;
        } else {
            $this->configuration = Util::getSolrConfiguration();
        }

        $this->allConfiguredFacets = $this->configuration->getSearchFacetingFacets();
        $this->facetRendererFactory = GeneralUtility::makeInstance(FacetRendererFactory::class,
            $this->allConfiguredFacets);
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
        $query->setFaceting();
        $this->buildFacetingParameters();
        $this->addFacetQueryFilters();

        foreach ($this->facetParameters as $facetParameter => $value) {
            $query->addQueryParameter($facetParameter, $value);
        }

        foreach ($this->facetFilters as $filter) {
            $query->addFilter($filter);
        }

        return $query;
    }

    /**
     * Delegates the parameter building to specialized functions depending on
     * the type of facet to add.
     *
     */
    protected function buildFacetingParameters()
    {
        foreach ($this->allConfiguredFacets as $facetName => $facetConfiguration) {
            $facetName = substr($facetName, 0, -1);

            $facetParameterBuilder = $this->facetRendererFactory->getQueryFacetBuilderByFacetName($facetName);
            if (!is_null($facetParameterBuilder)) {
                $facetParameters = $facetParameterBuilder->buildFacetParameters($facetName,
                    $facetConfiguration);
            } else {
                $facetParameters = $this->buildFacetParameters($facetConfiguration);
            }

            $this->facetParameters = array_merge_recursive($this->facetParameters,
                $facetParameters);
        }
    }

    /**
     * Builds facet parameters for field facets
     *
     * @param array $facetConfiguration The facet's configuration
     * @return array
     */
    protected function buildFacetParameters(array $facetConfiguration)
    {
        $facetParameters = [];

        // simple for now, may add overrides f.<field_name>.facet.* later
        if ($this->configuration->getSearchFacetingKeepAllFacetsOnSelection()) {
            $facets = [];
            foreach ($this->allConfiguredFacets as $facet) {
                $facets[] = $facet['field'];
            }

            $facetParameters['facet.field'][] =
                '{!ex=' . implode(',', $facets) . '}'
                . $facetConfiguration['field'];
        } elseif ($facetConfiguration['keepAllOptionsOnSelection'] == 1) {
            $facetParameters['facet.field'][] =
                '{!ex=' . $facetConfiguration['field'] . '}'
                . $facetConfiguration['field'];
        } else {
            $facetParameters['facet.field'][] = $facetConfiguration['field'];
        }

        if (in_array($facetConfiguration['sortBy'],
            ['alpha', 'index', 'lex'])) {
            $facetParameters['f.' . $facetConfiguration['field'] . '.facet.sort'] = 'lex';
        }

        return $facetParameters;
    }

    /**
     * Adds filters specified through HTTP GET as filter query parameters to
     * the Solr query.
     *
     */
    protected function addFacetQueryFilters()
    {
        // todo refactor to use a request object
        $resultParameters = GeneralUtility::_GET('tx_solr');

        // format for filter URL parameter:
        // tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
        if (is_array($resultParameters['filter'])) {
            $filters = array_map('rawurldecode', $resultParameters['filter']);
            // $filters look like ['name:value1','name:value2','fieldname2:foo']
            $configuredFacets = $this->getConfiguredFacets();
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

            foreach ($filtersByFacetName as $facetName => $filterValues) {
                $facetConfiguration = $this->allConfiguredFacets[$facetName . '.'];
                $filterEncoder = $this->facetRendererFactory->getFacetFilterEncoderByFacetName($facetName);

                $tag = '';
                if ($facetConfiguration['keepAllOptionsOnSelection'] == 1
                   || $this->configuration->getSearchFacetingKeepAllFacetsOnSelection()
                ) {
                    $tag = '{!tag=' . addslashes($facetConfiguration['field']) . '}';
                }

                $filterParts = [];
                foreach ($filterValues as $filterValue) {
                    if (!is_null($filterEncoder)) {
                        $filterOptions = $facetConfiguration[$facetConfiguration['type'] . '.'];
                        if (empty($filterOptions)) {
                            $filterOptions = [];
                        }

                        $filterValue = $filterEncoder->decodeFilter($filterValue,
                            $filterOptions);
                        $filterParts[] = $facetConfiguration['field'] . ':' . $filterValue;
                    } else {
                        $filterParts[] = $facetConfiguration['field'] . ':"' . addslashes($filterValue) . '"';
                    }
                }

                $operator = ($facetConfiguration['operator'] == 'OR') ? ' OR ' : ' AND ';
                $this->facetFilters[] = $tag . '(' . implode($operator,
                        $filterParts) . ')';
            }
        }
    }

    /**
     * Gets the facets as configured through TypoScript
     *
     * @return array An array of facet names as specified in TypoScript
     */
    protected function getConfiguredFacets()
    {
        $facets = [];

        foreach ($this->allConfiguredFacets as $facetName => $facetConfiguration) {
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
