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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetQueryBuilderInterface;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class NumericRangeFacetQueryBuilder implements FacetQueryBuilderInterface
{
    /**
     * Builds query parts for numeric range facet
     */
    public function build(string $facetName, TypoScriptConfiguration $configuration): array
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);

        $tag = '';
        if ((bool)($facetConfiguration['keepAllOptionsOnSelection'] ?? null) === true) {
            $tag = '{!ex=' . $facetConfiguration['field'] . '}';
        }
        $facetParameters['facet.range'][] = $tag . $facetConfiguration['field'];

        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        $start = $facetConfiguration['numericRange.']['start'];
        if ($facetConfiguration['numericRange.']['start.'] ?? false) {
            $start = $cObj->stdWrap(
                $facetConfiguration['numericRange.']['start'],
                $facetConfiguration['numericRange.']['start.']
            );
        }

        $end = $facetConfiguration['numericRange.']['end'];
        if ($facetConfiguration['numericRange.']['end.'] ?? false) {
            $end = $cObj->stdWrap(
                $facetConfiguration['numericRange.']['end'],
                $facetConfiguration['numericRange.']['end.']
            );
        }

        $gap = $facetConfiguration['numericRange.']['gap'];
        if ($facetConfiguration['numericRange.']['gap.'] ?? false) {
            $gap = $cObj->stdWrap(
                $facetConfiguration['numericRange.']['gap'],
                $facetConfiguration['numericRange.']['gap.']
            );
        }

        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.start'] = $start;
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.end'] = $end;
        $facetParameters['f.' . $facetConfiguration['field'] . '.facet.range.gap'] = $gap;

        return $facetParameters;
    }
}
