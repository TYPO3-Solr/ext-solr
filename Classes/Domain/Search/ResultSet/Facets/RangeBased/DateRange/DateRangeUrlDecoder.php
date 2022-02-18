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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetUrlDecoderInterface;
use ApacheSolrForTypo3\Solr\System\DateTime\FormatService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parser to build solr range queries from tx_solr[filter]
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @copyright (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 * @copyright (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

        /* @var FormatService $formatService */
        $formatService = GeneralUtility::makeInstance(FormatService::class);
        $fromPart = '*';
        if($dateRangeStart !== ''){
            $fromPart = $formatService->timestampToIso(strtotime($dateRangeStart));
        }

        $toPart = '*';
        if($dateRangeEnd !== ''){
            $dateRangeEnd .= '59'; // adding 59 seconds
            $toPart = $formatService->timestampToIso(strtotime($dateRangeEnd));
        }

        $dateRangeFilter = '[' . $fromPart . ' TO ' . $toPart . ']';
        return $dateRangeFilter;
    }
}
