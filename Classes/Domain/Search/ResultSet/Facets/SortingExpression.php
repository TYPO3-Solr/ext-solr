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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

/**
 * Expression for facet sorting
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Jens Jacobsen <jens.jacobsen@ueberbit.de>
 */
class SortingExpression
{
    /**
     * Return expression for facet sorting
     *
     * @param string $sorting
     * @return string
     */
    public function getForFacet($sorting)
    {
        $noSortingSet = $sorting !== 0 && $sorting !== false && empty($sorting);
        $sortingIsCount = $sorting === 'count' || $sorting === 1 || $sorting === '1' || $sorting === TRUE;
        if ($noSortingSet) {
            return '';
        } elseif ($sortingIsCount) {
            return 'count';
        } else {
            return 'index';
        }
    }

    /**
     * Return expression for facet sorting combined with direction
     *
     * @param string $sorting
     * @param string $direction
     * @return string
     */
    public function getForJsonFacet($sorting, $direction)
    {
        $isMetricSorting = strpos($sorting, 'metrics_') === 0;
        $expression = $isMetricSorting ? $sorting : $this->getForFacet($sorting);
        $direction = strtolower($direction ?? '');
        if (!empty($direction) && in_array($direction, ['asc', 'desc'])) {
            $expression .= ' ' . $direction;
        }
        return $expression;
    }
}
