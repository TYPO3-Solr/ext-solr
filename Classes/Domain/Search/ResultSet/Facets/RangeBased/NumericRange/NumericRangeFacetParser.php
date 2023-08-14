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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Class NumericRangeFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class NumericRangeFacetParser extends AbstractRangeFacetParser
{
    protected string $facetClass = NumericRangeFacet::class;

    protected string $facetItemClass = NumericRange::class;

    protected string $facetRangeCountClass = NumericRangeCount::class;

    /**
     * Parses result-set to desired facet by its name
     */
    public function parse(SearchResultSet $resultSet, string $facetName, array $facetConfiguration): ?AbstractFacet
    {
        return $this->getParsedFacet(
            $resultSet,
            $facetName,
            $facetConfiguration,
            $this->facetClass,
            $this->facetItemClass,
            $this->facetRangeCountClass
        );
    }

    /**
     * Parses request value
     */
    protected function parseRequestValue(float|int|string|null $rawRequestValue): float
    {
        return is_numeric($rawRequestValue) ? (float)$rawRequestValue : 0.0;
    }

    /**
     * Parses response value
     */
    protected function parseResponseValue(float|int|string|null $rawResponseValue): float
    {
        return is_numeric($rawResponseValue) ? (float)$rawResponseValue : 0.0;
    }
}
