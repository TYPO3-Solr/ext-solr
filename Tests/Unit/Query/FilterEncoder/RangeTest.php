<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Query\FilterEncoder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for query parser range
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class RangeTest extends UnitTest
{
    /**
     * Parser to build Solr range queries
     *
     * @var \ApacheSolrForTypo3\Solr\Query\FilterEncoder\Range
     */
    protected $rangeParser;

    public function setUp()
    {
        $this->rangeParser = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\Range');
    }

    /**
     * Provides data for filter decoding tests
     *
     * @return array
     */
    public function rangeQueryParsingDataProvider()
    {
        return array(
            array('firstValue' => '50', 'secondValue' => '100', 'expected' => '[50 TO 100]'),
            array('firstValue' => '-10', 'secondValue' => '20', 'expected' => '[-10 TO 20]'),
            array('firstValue' => '-10', 'secondValue' => '-5', 'expected' => '[-10 TO -5]')
        );
    }

    /**
     * Test the filter decoding
     *
     * @dataProvider rangeQueryParsingDataProvider
     * @test
     *
     * @param string $firstValue
     * @param string $secondValue
     * @param string $expectedResult
     * @return void
     */
    public function canParseRangeQuery($firstValue, $secondValue, $expectedResult)
    {
        $actual = $this->rangeParser->decodeFilter($firstValue . '-' . $secondValue);
        $this->assertEquals($expectedResult, $actual);
    }

    /**
     * Test the handling of invalid parameters
     *
     * @test
     * @expectedException \InvalidArgumentException
     * @return void
     */
    public function canHandleInvalidParameters()
    {
        $this->rangeParser->decodeFilter('invalid-value');
    }
}
