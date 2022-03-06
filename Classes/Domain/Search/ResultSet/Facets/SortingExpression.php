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
     * @param string|int|bool $sorting
     * @return string
     */
    public function getForFacet($sorting): string
    {
        $noSortingSet = empty($sorting) && (int)$sorting !== 0 && (bool)$sorting !== false;
        $sortingIsCount = $sorting === 'count' || $sorting === 1 || $sorting === '1' || $sorting === true;
        if ($noSortingSet) {
            return '';
        }
        if ($sortingIsCount) {
            return 'count';
        }
        return 'index';
    }

    /**
     * Return expression for facet sorting combined with direction
     *
     * @param string $sorting
     * @param string $direction
     * @return string
     */
    public function getForJsonFacet(string $sorting, string $direction): string
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
