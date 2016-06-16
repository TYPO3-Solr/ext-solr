<?php
namespace ApacheSolrForTypo3\Solr\Query\FilterEncoder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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

/**
 * Parser to build Solr range queries from tx_solr[filter]
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class Range implements FilterEncoder, FacetBuilder
{

    /**
     * Delimiter for ranges in the URL.
     *
     * @var string
     */
    const DELIMITER = '-';

    /**
     * Takes a filter value and encodes it to a human readable format to be
     * used in an URL GET parameter.
     *
     * @param string $filterValue the filter value
     * @param array $configuration Facet configuration
     * @return string Value to be used in a URL GET parameter
     */
    public function encodeFilter($filterValue, array $configuration = array())
    {
        return $filterValue;
    }

    /**
     * Parses the given range from a GET parameter and returns a Solr range
     * filter.
     *
     * @param string $range The range filter from the URL.
     * @param array $configuration Facet configuration
     * @return string Lucene query language filter to be used for querying Solr
     * @throws \InvalidArgumentException
     */
    public function decodeFilter($range, array $configuration = array())
    {
        preg_match('/(-?\d*?)' . self::DELIMITER . '(-?\d*)/', $range, $filterParts);
        if ($filterParts[1] == '' || $filterParts[2] == '') {
            throw new \InvalidArgumentException(
                'Invalid numeric range given',
                1466062730
            );
        }

        return '[' . (int) $filterParts[1] . ' TO ' . (int)  $filterParts[2] . ']';
    }

    /**
     * Builds the facet parameters depending on a facet's configuration.
     *
     * Currently only covers numeric ranges.
     *
     * @param string $facetName Facet name
     * @param array $facetConfiguration The facet's configuration
     * @return array
     */
    public function buildFacetParameters($facetName, array $facetConfiguration)
    {
        $facetParameters = array();

        $tag = '';
        if ($facetConfiguration['keepAllOptionsOnSelection'] == 1) {
            $tag = '{!ex=' . $facetConfiguration['field'] . '}';
        }
        $facetParameters['facet.range'][] = $tag . $facetConfiguration['field'];

        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.start'] = $facetConfiguration['numericRange.']['start'];
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.end'] = $facetConfiguration['numericRange.']['end'];
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.gap'] = $facetConfiguration['numericRange.']['gap'];

        return $facetParameters;
    }
}
