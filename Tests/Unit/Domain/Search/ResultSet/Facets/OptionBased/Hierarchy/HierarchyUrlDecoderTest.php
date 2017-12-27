<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyUrlDecoder;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * Testcase for query parser range
 * @author Markus Goldbach
 */
class HierarchyUrlEncoderTest extends UnitTest
{
    /**
     * @var HierarchyUrlDecoder
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = GeneralUtility::makeInstance(HierarchyUrlDecoder::class);
    }

    /**
     * @test
     */
    public function canParseHierarchy3LevelQuery()
    {
        $expected = '"2-sport/skateboarding/street/"';
        $actual = $this->parser->decode('/sport/skateboarding/street/');

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function canParseHierarchy2LevelQuery()
    {
        $expected = '"1-sport/skateboarding/"';
        $actual = $this->parser->decode('/sport/skateboarding/');

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function canParseHierarchy1LevelQuery()
    {
        $expected = '"0-sport/"';
        $actual = $this->parser->decode('/sport/');

        $this->assertEquals($expected, $actual);
    }
}
