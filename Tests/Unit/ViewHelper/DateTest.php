<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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

use ApacheSolrForTypo3\Solr\ViewHelper\Date;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PHP Unit test for date format view helper (ApacheSolrForTypo3\Solr\ViewHelper\Date)
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class DateTest extends AbstractViewHelperTest
{

    /**
     * Set up the view helper test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        date_default_timezone_set('Europe/Berlin');
    }

    /**
     * Returns the date view helper
     *
     * @return Date
     */
    protected function getDateViewHelper()
    {
        return GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ViewHelper\\Date');
    }

    /**
     * Sets the default format in TypoScript
     *
     * @param string $glue
     * @return void
     */
    protected function setDefaultFormat($format)
    {
        if (!is_null($format)) {
            $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['general.']['dateFormat.']['date'] = $format;
        } else {
            unset($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['general.']['dateFormat.']);
        }
        $this->configurationManager->reset();
    }

    /**
     * Provides data for the date formatting tests
     *
     * @return array
     */
    public function dateFormattingDataProvider()
    {
        $timestamp = '1465893746'; // 14 Jun 2016 10:42:26 +0200
        return array(
            array('defaultFormat' => 'd.m.Y H:i', 'timestamp' => $timestamp, 'format' => 'd.m.Y H:i', 'expectedResult' => '14.06.2016 10:42'),
            array('defaultFormat' => 'd.m.Y H:i', 'timestamp' => $timestamp, 'format' => 'Y-m-d H:i', 'expectedResult' => '2016-06-14 10:42'),
            array('defaultFormat' => 'd.m.Y H:i', 'timestamp' => $timestamp, 'format' => null, 'expectedResult' => '14.06.2016 10:42'),
            array('defaultFormat' => null, 'timestamp' => $timestamp, 'format' => null, 'expectedResult' => '14.06.2016 10:42')
        );
    }

    /**
     * Tests the date formatting
     *
     * @dataProvider dateFormattingDataProvider
     * @test
     *
     * @param string $defaultFormat the default format provided via TypoScript
     * @param integer $timestamp the timestamp to format
     * @param mixed $format the requested format
     * @param string $expectedResult
     * @return void
     */
    public function canFormatDates($defaultFormat, $timestamp, $format, $expectedResult)
    {
        $arguments = array($timestamp);
        if (!is_null($format)) {
            $arguments[] = $format;
        }
        $this->setDefaultFormat($defaultFormat);
        $formattedDate = $this->getDateViewHelper()->execute($arguments);
        $this->assertEquals($expectedResult, $formattedDate, 'Formatted date "' . $formattedDate . '"  doesn\'t match the expected result: ' . $expectedResult);
    }
}
