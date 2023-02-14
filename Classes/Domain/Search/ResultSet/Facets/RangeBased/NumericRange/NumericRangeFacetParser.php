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
    /**
     * @var string
     */
    protected string $facetClass = NumericRangeFacet::class;

    /**
     * @var string
     */
    protected string $facetItemClass = NumericRange::class;

    /**
     * @var string
     */
    protected string $facetRangeCountClass = NumericRangeCount::class;

    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return AbstractFacet|null
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
     * @param float|int|string|null $rawRequestValue
     * @return float (numeric value)
     */
    protected function parseRequestValue($rawRequestValue): float
    {
        return is_numeric($rawRequestValue) ? (float)$rawRequestValue : 0.0;
    }

    /**
     * @param float|int|string|null $rawResponseValue
     * @return float (numeric value)
     */
    protected function parseResponseValue($rawResponseValue): float
    {
        return is_numeric($rawResponseValue) ? (float)$rawResponseValue : 0.0;
    }
}
