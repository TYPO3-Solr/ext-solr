<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

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
    protected $facetClass = NumericRangeFacet::class;

    /**
     * @var string
     */
    protected $facetItemClass = NumericRange::class;

    /**
     * @var string
     */
    protected $facetRangeCountClass = NumericRangeCount::class;

    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return NumericRangeFacet|null
     */
    public function parse(SearchResultSet $resultSet, $facetName, array $facetConfiguration)
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
     * @param mixed $rawValue
     * @return mixed (numeric value)
     */
    protected function parseRequestValue($rawValue)
    {
        return is_numeric($rawValue) ? $rawValue : 0;
    }

    /**
     * @param $rawValue
     * @return mixed (numeric value)
     */
    protected function parseResponseValue($rawValue)
    {
        return is_numeric($rawValue) ? $rawValue : 0;
    }
}
