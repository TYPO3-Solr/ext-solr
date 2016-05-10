<?php
namespace ApacheSolrForTypo3\Solr\Query\FilterEncoder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Facet\FacetBuilder;
use ApacheSolrForTypo3\Solr\Util;

/**
 * Filter encoder to build facet query parameters
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class QueryGroup implements FilterEncoder, FacetBuilder
{

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Takes a filter value and encodes it to a human readable format to be
     * used in an URL GET parameter.
     *
     * @param string $filterValue the filter value
     * @param array $configuration options set in a facet's configuration
     * @return string Value to be used in a URL GET parameter
     */
    public function encodeFilter($filterValue, array $configuration = array())
    {
        $field = $configuration['field'];
        $queries = $configuration['queryGroup.'];

        foreach ($queries as $queryName => $queryConfiguration) {
            if ($filterValue == $field . ':' . $queryConfiguration['query']) {
                $filterValue = substr($queryName, 0, -1);
                break;
            }
        }

        return $filterValue;
    }

    /**
     * Parses the query filter from GET parameters in the URL and translates it
     * to a Lucene filter value.
     *
     * @param string $filterValue the filter query from plugin
     * @param array $configuration options set in a facet's configuration
     * @return string Value to be used in a Lucene filter
     */
    public function decodeFilter($filterValue, array $configuration = array())
    {
        return $configuration[$filterValue . '.']['query'];
    }

    /**
     * Builds the facet parameters depending on a facet's configuration.
     *
     * Currently only covers numeric ranges.
     *
     * @param string $facetName Facet name
     * @param array $facetConfiguration The facet's configuration
     * @return array facet queries
     */
    public function buildFacetParameters($facetName, array $facetConfiguration)
    {
        $facetParameters = array();

        foreach ($facetConfiguration['queryGroup.'] as $queryName => $queryConfiguration) {
            $tag = '';
            if ($this->configuration->getSearchFacetingKeepAllFacetsOnSelection()) {
                // TODO This code is duplicated from "Query/Modifier/Faceting.php"
                // Eventually the "exclude fields" should get passed to this method beforehand instead
                // of generating them in each different "buildFacetParameters" implementation
                $facets = array();
                foreach ($this->configuration->getSearchFacetingFacets() as $facet) {
                    $facets[] = $facet['field'];
                }
                $tag = '{!ex=' . implode(',', $facets) . '}';
            } elseif ($facetConfiguration['keepAllOptionsOnSelection'] == 1) {
                $tag = '{!ex=' . $facetConfiguration['field'] . '}';
            }

            $facetParameters['facet.query'][] = $tag . $facetConfiguration['field'] . ':' . $queryConfiguration['query'];
        }

        return $facetParameters;
    }
}
