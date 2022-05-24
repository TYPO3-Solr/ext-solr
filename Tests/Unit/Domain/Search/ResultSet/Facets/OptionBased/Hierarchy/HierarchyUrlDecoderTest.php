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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy\HierarchyUrlDecoder;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for query parser range
 * @author Markus Goldbach
 */
class HierarchyUrlEncoderTest extends UnitTest
{
    /**
     * @var HierarchyUrlDecoder
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = GeneralUtility::makeInstance(HierarchyUrlDecoder::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function canParseHierarchy3LevelQuery()
    {
        $expected = '"2-sport/skateboarding/street/"';
        $actual = $this->parser->decode('/sport/skateboarding/street/');

        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function canParseHierarchy3LevelQueryAndEscapedSlashes()
    {
        $expected = '"2-sport/skateboarding\\\\/snowboarding/street/"';
        $actual = $this->parser->decode('/sport/skateboarding\/snowboarding/street/');

        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function canParseHierarchy2LevelQuery()
    {
        $expected = '"1-sport/skateboarding/"';
        $actual = $this->parser->decode('/sport/skateboarding/');

        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function canParseHierarchy1LevelQuery()
    {
        $expected = '"0-sport/"';
        $actual = $this->parser->decode('/sport/');

        self::assertEquals($expected, $actual);
    }
}
