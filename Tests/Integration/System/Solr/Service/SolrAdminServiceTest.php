<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr admin service is working as expected.
 *
 * @author Timo Hund
 */
class SolrAdminServiceTest extends IntegrationTest
{

    /**
     * @var SolrAdminService
     */
    protected $solrAdminService;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->solrAdminService = GeneralUtility::makeInstance(SolrAdminService::class, 'localhost', 8999, '/solr/core_en/');
    }

    /**
     * @return array
     */
    public function synonymDataProvider()
    {
        return [
            'normal' => ['baseword' => 'homepage', 'synonyms' => ['website']],
            'umlaut' => ['baseword' => 'früher', 'synonyms' => ['vergangenheit']]

        ];
    }

    /**
     * @param string $baseWord
     * @param array $synonyms
     * @dataProvider synonymDataProvider
     * @test
     */
    public function canAddSynonym($baseWord, $synonyms = [])
    {
        // make sure old synonyms have been deleted
        $this->solrAdminService->deleteSynonym($baseWord);

        $synonymsBeforeAdd = $this->solrAdminService->getSynonyms($baseWord);
        $this->assertEquals([], $synonymsBeforeAdd, 'Synonyms was not empty');

        $this->solrAdminService->addSynonym($baseWord, $synonyms);
        $synonymsAfterAdd = $this->solrAdminService->getSynonyms($baseWord);
        $this->assertEquals($synonyms, $synonymsAfterAdd, 'Could not retrieve synonym after adding');

        $this->solrAdminService->deleteSynonym($baseWord);

        $synonymsAfterRemove = $this->solrAdminService->getSynonyms($baseWord);
        $this->assertEquals([], $synonymsAfterRemove, 'Synonym was not removed');
    }

    /**
     * @return array
     */
    public function stopWordDataProvider()
    {
        return [
            'normal' => ['stopword' => 'badword'],
            'umlaut' => ['stopword' => 'frühaufsteher']
        ];
    }

    /**
     * @test
     * @dataProvider stopwordDataProvider
     */
    public function canAddStopWord($stopWord)
    {
        // make sure old stopwords are deleted
        $this->solrAdminService->deleteStopWord($stopWord);
        $stopWords = $this->solrAdminService->getStopWords();
        $this->assertNotContains($stopWord, $stopWords, 'Stopwords are not empty after initializing');

        $this->solrAdminService->addStopWords($stopWord);
        $stopWordsAfterAdd = $this->solrAdminService->getStopWords();
        $this->assertContains($stopWord, $stopWordsAfterAdd, 'Stopword was not added');

        $this->solrAdminService->deleteStopWord($stopWord);
        $stopWordsAfterDelete = $this->solrAdminService->getStopWords();
        $this->assertNotContains($stopWord, $stopWordsAfterDelete, 'Stopwords are not empty after removing');
    }

    /**
     * Check if the default stopswords are stored in the solr server.
     *
     * @test
     */
    public function containsDefaultStopWord()
    {
        $stopWordsInSolr = $this->solrAdminService->getStopWords();
        $this->assertContains('and', $stopWordsInSolr, 'Default stopword and was not present');
    }

    /**
     * @test
     */
    public function canGetSystemInformation()
    {
        $informationResponse = $this->solrAdminService->getSystemInformation();
        $this->assertSame(200, $informationResponse->getHttpStatus(), 'Could not get information response from solr server');
    }

    /**
     * @test
     */
    public function canGetPingRoundtrimRunTime()
    {
        $pingRuntime = $this->solrAdminService->getPingRoundTripRuntime();
        $this->assertGreaterThan(0, $pingRuntime, 'Ping runtime should be larger then 0');
        $this->assertTrue(is_double($pingRuntime),'Ping runtime should be an integer');
    }

    /**
     * @test
     */
    public function canGetSolrServiceVersion()
    {
        $solrServerVersion = $this->solrAdminService->getSolrServerVersion();
        $isVersionHigherSix = version_compare('6.0.0', $solrServerVersion, '<');
        $this->assertTrue($isVersionHigherSix, 'Expecting to run on version larger then 6.0.0');
    }

    /**
     * @test
     */
    public function canReloadCore()
    {
        $result = $this->solrAdminService->reloadCore();
        $this->assertSame(200, $result->getHttpStatus(), 'Reload core did not responde with a 200 ok status');
    }
}
