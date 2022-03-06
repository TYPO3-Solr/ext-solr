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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Data\DateTime;
use DateTime as PhpDateTime;
use Exception;

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
    protected string $facetClass = DateRangeFacet::class;

    /**
     * @var string
     */
    protected string $facetItemClass = DateRange::class;

    /**
     * @var string
     */
    protected string $facetRangeCountClass = DateRangeCount::class;

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
     * @return DateTime|null
     * @throws Exception
     */
    protected function parseRequestValue($rawRequestValue): ?DateTime
    {
        $rawRequestValue = PhpDateTime::createFromFormat('Ymd', substr((string)$rawRequestValue, 0, 8));
        if ($rawRequestValue === false) {
            return null;
        }
        return new DateTime($rawRequestValue->format(DateTime::ISO8601));
    }

    /**
     * @param float|int|string|null $rawResponseValue
     * @return DateTime
     * @throws Exception
     */
    protected function parseResponseValue($rawResponseValue): DateTime
    {
        $rawDate = PhpDateTime::createFromFormat(PhpDateTime::ISO8601, $rawResponseValue);
        return new DateTime($rawDate->format(DateTime::ISO8601));
    }
}
