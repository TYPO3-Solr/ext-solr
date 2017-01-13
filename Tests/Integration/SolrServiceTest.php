<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\ExtractingQuery;
use ApacheSolrForTypo3\Solr\SolrService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr service is working as expected.
 *
 * @author Timo Schmidt
 */
class SolrServiceTest extends IntegrationTest
{

    /**
     * @var SolrService
     */
    protected $solrService;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->solrService = GeneralUtility::makeInstance(SolrService::class, 'localhost', 8999, '/solr/core_en/');
    }

    /**
     * @test
     */
    public function canExtractByQuery()
    {
        $testFilePath = $this->getFixturePath('testpdf.pdf');
            /** @var $extractQuery \ApacheSolrForTypo3\Solr\ExtractingQuery */
        $extractQuery = GeneralUtility::makeInstance(ExtractingQuery::class, $testFilePath);
        $extractQuery->setExtractOnly();
        $response = $this->solrService->extractByQuery($extractQuery);
        $this->assertContains('PDF Test', $response[0], 'Could not extract text');
    }

    /**
     * @test
     */
    public function canAddSynonym()
    {
        // make sure old synonyms have been deleted
        $this->solrService->deleteSynonym('homepage');

        $synonymsBeforeAdd = $this->solrService->getSynonyms('homepage');
        $this->assertEquals([], $synonymsBeforeAdd, 'Synonyms was not empty');

        $this->solrService->addSynonym('homepage', ['website']);
        $synonymsAfterAdd = $this->solrService->getSynonyms('homepage');
        $this->assertEquals(['website'], $synonymsAfterAdd, 'Could not retrieve synonym after adding');

        $this->solrService->deleteSynonym('homepage');

        $synonymsAfterRemove = $this->solrService->getSynonyms('homepage');
        $this->assertEquals([], $synonymsAfterRemove, 'Synonym was not removed');
    }

    /**
     * @test
     */
    public function canAddStopWord()
    {
        // make sure old stopwords are deleted
        $this->solrService->deleteStopWord('badword');
        $stopWords = $this->solrService->getStopWords();
        $this->assertNotContains('badword', $stopWords, 'Stopwords are not empty after initializing');

        $this->solrService->addStopWords('badword');
        $stopWordsAfterAdd = $this->solrService->getStopWords();
        $this->assertContains('badword', $stopWordsAfterAdd, 'Stopword was not added');

        $this->solrService->deleteStopWord('badword');
        $stopWordsAfterDelete = $this->solrService->getStopWords();
        $this->assertNotContains('badword', $stopWordsAfterDelete, 'Stopwords are not empty after removing');
    }

    /**
     * Check if the default stopswords are stored in the solr server.
     *
     * @test
     */
    public function containsDefaultStopWord()
    {
        $stopWordsInSolr = $this->solrService->getStopWords();
        $this->assertContains('and', $stopWordsInSolr, 'Default stopword and was not present');
    }

    /**
     * @test
     */
    public function canGetSystemInformation()
    {
        $informationResponse = $this->solrService->getSystemInformation();
        $this->assertSame(200, $informationResponse->getHttpStatus(), 'Could not get information response from solr server');
    }

    /**
     * @test
     */
    public function canGetPingRoundtrimRunTime()
    {
        $pingRuntime = $this->solrService->getPingRoundTripRuntime();
        $this->assertGreaterThan(0, $pingRuntime, 'Ping runtime should be larger then 0');
        $this->assertTrue(is_double($pingRuntime),'Ping runtime should be an integer');
    }

    /**
     * @test
     */
    public function canGetSolrServiceVersion()
    {
        $solrServerVersion = $this->solrService->getSolrServerVersion();
        $isVersionHigherSix = version_compare('6.0.0', $solrServerVersion, '<');
        $this->assertTrue($isVersionHigherSix, 'Expecting to run on version larger then 6.0.0');
    }

    /**
     * @test
     */
    public function canReloadCore()
    {
        $result = $this->solrService->reloadCore();
        $this->assertSame(200, $result->getHttpStatus(), 'Reload core did not responde with a 200 ok status');
    }
}
