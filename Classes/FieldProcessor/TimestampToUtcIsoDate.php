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

namespace ApacheSolrForTypo3\Solr\FieldProcessor;

use ApacheSolrForTypo3\Solr\System\DateTime\FormatService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A field processor that converts timestamps to ISO dates as needed by Solr
 *
 * @author Andreas Allacher <andreas.allacher@cyberhouse.at>
 * @copyright (c) 2009-2015 Andreas Allacher <andreas.allacher@cyberhouse.at>
 */
class TimestampToUtcIsoDate implements FieldProcessor
{

    /**
     * Expects a timestamp and converts it to an ISO 8601 date in UTC as needed by Solr.
     *
     * Example date output format: 1995-12-31T23:59:59Z
     * The trailing "Z" designates UTC time and is mandatory
     *
     * @param array $values Array of values, an array because of multivalued fields
     * @return array Modified array of values
     */
    public function process(array $values)
    {
        $results = [];
        /* @var FormatService $formatService */
        $formatService = GeneralUtility::makeInstance(FormatService::class);

        foreach ($values as $timestamp) {
            $results[] = $formatService->timestampToUtcIso($timestamp);
        }

        return $results;
    }
}
