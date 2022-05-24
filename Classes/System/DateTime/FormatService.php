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

namespace ApacheSolrForTypo3\Solr\System\DateTime;

use DateTime;
use DateTimeZone;

/**
 * Testcase to check if the configuration object can be used as expected
 *
 * @author Hendrik Putzek <hendrik.putzek@dkd.de>
 * @author Timo Hund <timo.hund@dkd.de>
 * @copyright (c) 2011-2016 Timo Schmidt <timo.hund@dkd.de>
 */
class FormatService
{
    const SOLR_ISO_DATETIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * @see http://php.net/manual/de/function.date.php for formatting options
     * @param string $input the passed date string
     * @param string $inputFormat the input format that should be used for parsing
     * @param string $outputFormat The output format, when nothing is passed
     * $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] will be used or Y-m-d when nothing is configured
     * @param DateTimeZone|null $timezone
     * @return \DateTime|string
     */
    public function format($input = '', $inputFormat = 'Y-m-d\TH:i:s\Z', $outputFormat = '', $timezone = null)
    {
        if ($outputFormat === '') {
            // when no value was passed we us the TYPO3 configured or fallback to Y-m-d
            $outputFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?: 'Y-m-d';
        }

        // try to create DateTime object
        $timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        return $this->getFormattedDate($input, $inputFormat, $outputFormat, $timezone);
    }

    /**
     * Converts a date from unix timestamp to ISO 8601 format.
     *
     * @param ?int $timestamp unix timestamp
     * @return string the date in ISO 8601 format
     */
    public function timestampToIso(?int $timestamp = 0): string
    {
        return date(self::SOLR_ISO_DATETIME_FORMAT, $timestamp ?? 0);
    }

    /**
     * Converts a date from ISO 8601 format to unix timestamp.
     *
     * @param string $isoTime date in ISO 8601 format
     * @return int unix timestamp
     */
    public function isoToTimestamp($isoTime): int
    {
        $dateTime = \DateTime::createFromFormat(
            self::SOLR_ISO_DATETIME_FORMAT,
            $isoTime
        );
        return $dateTime ? (int)$dateTime->format('U') : 0;
    }

    /**
     * Converts a date from unix timestamp to ISO 8601 format in UTC timezone.
     *
     * @param ?int $timestamp unix timestamp
     * @return string the date in ISO 8601 format
     */
    public function timestampToUtcIso(?int $timestamp = 0): string
    {
        return gmdate(self::SOLR_ISO_DATETIME_FORMAT, $timestamp ?? 0);
    }

    /**
     * Converts a date from ISO 8601 format in UTC timezone to unix timestamp.
     *
     * @param string $isoTime date in ISO 8601 format
     * @return int unix timestamp
     */
    public function utcIsoToTimestamp($isoTime): int
    {
        $utcTimeZone = new \DateTimeZone('UTC');
        $dateTime = \DateTime::createFromFormat(
            self::SOLR_ISO_DATETIME_FORMAT,
            $isoTime,
            $utcTimeZone
        );
        return $dateTime ? (int)$dateTime->format('U') : 0;
    }

    /**
     * Applies the formatting using DateTime.
     *
     * @param string $input
     * @param string $inputFormat
     * @param string $outputFormat
     * @param DateTimeZone $timezone
     * @return string
     */
    protected function getFormattedDate($input, $inputFormat, $outputFormat, DateTimeZone $timezone): string
    {
        $formattedDate = DateTime::createFromFormat($inputFormat, $input, $timezone);
        if ($formattedDate) {
            return $formattedDate->format($outputFormat);
        }

        return (string)$input;
    }
}
