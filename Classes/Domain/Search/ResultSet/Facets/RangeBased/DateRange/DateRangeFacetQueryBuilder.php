<?php

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetQueryBuilderInterface;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

class DateRangeFacetQueryBuilder implements FacetQueryBuilderInterface
{
    /**
     * @param string $facetName
     * @param TypoScriptConfiguration $configuration
     * @return array
     */
    public function build($facetName, TypoScriptConfiguration $configuration)
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);

        $tag = '';
        if (($facetConfiguration['keepAllOptionsOnSelection'] ?? 0) == 1) {
            $tag = '{!ex=' . $facetConfiguration['field'] . '}';
        }
        $facetParameters['facet.range'][] = $tag . $facetConfiguration['field'];

        $start = 'NOW/DAY-1YEAR';
        if (!empty($facetConfiguration['dateRange.']['start'])) {
            $start = $facetConfiguration['dateRange.']['start'];
        }
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.start'] = $start;

        $end = 'NOW/DAY+1YEAR';
        if (!empty($facetConfiguration['dateRange.']['end'])) {
            $end = $facetConfiguration['dateRange.']['end'];
        }
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.end'] = $end;

        $gap = '+1DAY';
        if (!empty($facetConfiguration['dateRange.']['gap'])) {
            $gap = $facetConfiguration['dateRange.']['gap'];
        }
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.gap'] = $gap;

        return $facetParameters;
    }
}
