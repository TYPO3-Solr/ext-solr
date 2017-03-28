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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr service is working as expected.
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class SolrServiceTest extends IntegrationTest
{

    /**
     * @var \ApacheSolrForTypo3\Solr\SolrService
     */
    protected $solrService;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->solrService = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\SolrService', 'localhost', 8080, '/solr/core_en/');
    }

    /**
     * @test
     */
    public function canExtractByQuery()
    {
        $testFilePath = $this->getFixturePath('testpdf.pdf');
            /** @var $extractQuery \ApacheSolrForTypo3\Solr\ExtractingQuery */
        $extractQuery = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\ExtractingQuery', $testFilePath);
        $extractQuery->setExtractOnly();
        $response = $this->solrService->extractByQuery($extractQuery);
        $this->assertContains('PDF Test', $response[0], 'Could not extract text');
    }

    /**
     * @test
     */
    public function canAddAndDeleteSynonyms()
    {
        $baseword = 'base';
        $synonyms = array(
            'pad',
            'underlay',
            'record'
        );

        $this->solrService->addSynonym($baseword, $synonyms);
        $synonymsFromSolr = $this->solrService->getSynonyms();
        $this->assertArrayHasKey($baseword, $synonymsFromSolr);

        $this->solrService->deleteSynonym($baseword);
        $synonymsFromSolr = $this->solrService->getSynonyms();
        $this->assertArrayNotHasKey($baseword, $synonymsFromSolr);
    }

    /**
     * @test
     */
    public function canAddAndDeleteSynonymsWithUmlauts()
    {
        $baseword = 'ärger';
        $synonyms = array(
            'kummer',
            'problem',
            'unruhe'
        );

        $this->solrService->addSynonym($baseword, $synonyms);
        $synonymsFromSolr = $this->solrService->getSynonyms();
        $this->assertArrayHasKey($baseword, $synonymsFromSolr);

        $this->solrService->deleteSynonym($baseword);
        $synonymsFromSolr = $this->solrService->getSynonyms();
        $this->assertArrayNotHasKey($baseword, $synonymsFromSolr);
    }

}
