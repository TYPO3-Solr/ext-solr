<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Query\FilterEncoder;

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
 *
 * Testcase for query parser range
 * @author Markus Goldbach
 */
class RangeTest extends UnitTest
{
    /**
     * @var \ApacheSolrForTypo3\Solr\Query\FilterEncoder\Range
     */
    protected $rangeParser;

    public function setUp()
    {
        $this->rangeParser = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\Range');
    }

    /**
     * @test
     */
    public function canParseRangeQuery()
    {
        $expected = '[firstValue TO secondValue]';
        $actual = $this->rangeParser->decodeFilter('firstValue-secondValue');

        $this->assertEquals($expected, $actual);
    }
}
