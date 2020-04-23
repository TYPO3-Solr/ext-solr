<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

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
use ApacheSolrForTypo3\Solr\System\Data\DateTime;

/**
 * Class DateRangeFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRangeFacetParser extends AbstractRangeFacetParser
{
    /**
     * @var string
     */
    protected $facetClass = DateRangeFacet::class;

    /**
     * @var string
     */
    protected $facetItemClass = DateRange::class;

    /**
     * @var string
     */
    protected $facetRangeCountClass = DateRangeCount::class;

    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return DateRangeFacet|null
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
     * @param string $rawDate
     * @return DateTime|null
     */
    protected function parseRequestValue($rawDate)
    {
        $rawDate = \DateTime::createFromFormat('Ymd', substr($rawDate, 0, 8));
        if ($rawDate === false) {
            return null;
        }
        $date = new DateTime($rawDate->format(DateTime::ISO8601));
        return $date;
    }

    /**
     * @param string $isoDateString
     * @return DateTime
     */
    protected function parseResponseValue($isoDateString)
    {
        $rawDate = \DateTime::createFromFormat(\DateTime::ISO8601, $isoDateString);
        return new DateTime($rawDate->format(DateTime::ISO8601));
    }
}
