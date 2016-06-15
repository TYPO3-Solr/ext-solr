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

use ApacheSolrForTypo3\Solr\ViewHelper\Relevance;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PHP Unit test for relevance view helper (ApacheSolrForTypo3\Solr\ViewHelper\Relevance)
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class RelevanceTest extends AbstractViewHelperTest
{
    /**
     * Relevance view helper
     *
     * @var Relevance
     */
    protected $viewHelper;

    /**
     * Set up the view helper test
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        // prepare solr request handler
        $solrRequestHandler = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search', $this->getDumbMock('ApacheSolrForTypo3\Solr\SolrService'));

        // init view helper
        $this->viewHelper = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ViewHelper\\Relevance');
    }

    /**
     * Tear down
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        GeneralUtility::purgeInstances();
    }

    /**
     * Provides data for the data score extraction test
     *
     * @return array
     */
    public function dataScoreExtractionDataProvider()
    {
        return array(
            array('document' => serialize(array('score' => 5)), 'expectsException' => false, 'expectedResult' => 5),
            array('document' => '-invalid-', 'expectsException' => true, 'expectedResult' => null),
            array('document' => '###RESULT_DOCUMENT###', 'expectsException' => false, 'expectedResult' => null)
        );
    }

    /**
     * Tests the data score extraction
     *
     * @dataProvider dataScoreExtractionDataProvider
     * @test
     *
     * @param string $document the current document
     * @param boolean $expectsException
     * @param mixed $expectedResult
     * @return void
     */
    public function canReadScore($document, $expectsException, $expectedResult)
    {
        $exception = false;
        try {
            $score = $this->callInaccessibleMethod($this->viewHelper, 'getScore', $document);
        } catch (\RuntimeException $e) {
            $exception = true;
        }

        $this->assertEquals($expectsException, $exception);
        $this->assertEquals($expectedResult, $score);
    }

    /**
     * Provides data for the maximum score extraction test
     *
     * @return array
     */
    public function maximumScoreExtractionDataProvider()
    {
        return array(
            array('document' => serialize(array('__solr_grouping_groupMaximumScore' => 10)), 'globalMaximumScore' => 20, 'expectedResult' => 10),
            array('document' => serialize(array()), 'globalMaximumScore' => 20, 'expectedResult' => 20),
            array('document' => serialize('-invalid-'), 'globalMaximumScore' => 20, 'expectedResult' => 20),
        );
    }

    /**
     * Tests the maximum score extraction
     *
     * @dataProvider maximumScoreExtractionDataProvider
     * @test
     *
     * @param string $document the current document
     * @param integer globalMaximumScore
     * @param integer $expectedResult
     * @return void
     */
    public function canReadMaximumScore($document, $globalMaximumScore, $expectedResult)
    {
        $this->inject($this->viewHelper, 'maxScore', $globalMaximumScore);
        $maximumScore = $this->callInaccessibleMethod($this->viewHelper, 'getMaximumScore', $document);
        $this->assertEquals($expectedResult, $maximumScore);
    }

    /**
     * Provides data for the relevance calculation test
     *
     * @return array
     */
    public function relevanceCalculationDataProvider()
    {
        return array(
            array('documentScore' => 50, 'maximumScore' => 100, 'expectedResult' => 50.0),
            array('documentScore' => 50, 'maximumScore' => 0, 'expectedResult' => ''),
            array('documentScore' => 100, 'maximumScore' => 100, 'expectedResult' => 100.0),
        );
    }

    /**
     * Tests the relevance calculation
     *
     * @dataProvider relevanceCalculationDataProvider
     * @test
     *
     * @param integer $documentScore
     * @param integer $maximumScore
     * @param float $expectedResult
     * @return void
     */
    public function canCalculateRelevance($documentScore, $maximumScore, $expectedResult)
    {
        $relevance = $this->callInaccessibleMethod($this->viewHelper, 'render', $documentScore, $maximumScore);
        $this->assertEquals($expectedResult, $relevance);
    }
}
