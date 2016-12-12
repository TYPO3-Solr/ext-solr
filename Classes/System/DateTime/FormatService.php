<?php
namespace ApacheSolrForTypo3\Solr\System\DateTime;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2016 Timo Schmidt <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use DateTime;
use DateTimeZone;

/**
 * Testcase to check if the configuration object can be used as expected
 *
 * @author Hendrik Putzek <hendrik.putzek@dkd.de>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FormatService
{

    /**
     * @see http://php.net/manual/de/function.date.php for formatting options
     * @param string $input the passed date string
     * @param string $inputFormat the input format that should be used for parsing
     * @param string $outputFormat The output format, when nothing is passed
     * $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] will be used or Y-m-d when nothing is configured
     * @param DateTimeZone $timezone
     * @return \DateTime|string
     */
    public function format($input = '', $inputFormat = 'Y-m-d\TH:i:s\Z', $outputFormat = '', $timezone = null)
    {
        if ($outputFormat === '') {
            // when no value was passed we us the TYPO3 configured or fallback to Y-m-d
            $outputFormat = $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?: 'Y-m-d';
        }

        // try to create DateTime object
        $timezone = is_null($timezone) ?  new DateTimeZone(date_default_timezone_get()) : $timezone;
        return $this->getFormattedDate($input, $inputFormat, $outputFormat, $timezone);
    }

    /**
     * Applies the formatting using DateTime.
     *
     * @param string $input
     * @param string $inputFormat
     * @param string $outputFormat
     * @param DateTimeZone $timezone
     * @return \DateTime|string
     */
    protected function getFormattedDate($input, $inputFormat, $outputFormat, DateTimeZone $timezone)
    {
        $formattedDate = DateTime::createFromFormat($inputFormat, $input, $timezone);
        if ($formattedDate) {
            return $formattedDate->format($outputFormat);
        }

        return $input;
    }
}
