<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetUrlDecoderInterface;
use ApacheSolrForTypo3\Solr\System\DateTime\FormatService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parser to build solr range queries from tx_solr[filter]
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 */
class DateRangeUrlDecoder implements FacetUrlDecoderInterface
{

    /**
     * Delimiter for date parts in the URL.
     *
     * @var string
     */
    const DELIMITER = '-';

    /**
     * Parses the given date range from a GET parameter and returns a Solr
     * date range filter.
     *
     * @param string $dateRange The range filter query string from the query URL
     * @param array $configuration Facet configuration
     * @return string Lucene query language filter to be used for querying Solr
     */
    public function decode($dateRange, array $configuration = [])
    {
        list($dateRangeStart, $dateRangeEnd) = explode(self::DELIMITER, $dateRange);

        $dateRangeEnd .= '59'; // adding 59 seconds

        $formatService = GeneralUtility::makeInstance(FormatService::class);
        $dateRangeFilter = '[' . $formatService->timestampToIso(strtotime($dateRangeStart));
        $dateRangeFilter .= ' TO ';
        $dateRangeFilter .= $formatService->timestampToIso(strtotime($dateRangeEnd)) . ']';
        return $dateRangeFilter;
    }
}
