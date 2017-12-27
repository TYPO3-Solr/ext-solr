<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\ExtractingQuery;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr write service is working as expected.
 *
 * @author Timo Hund
 */
class SolrWriteServiceTest extends IntegrationTest
{

    /**
     * @var SolrService
     */
    protected $solrWriteService;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->solrWriteService = GeneralUtility::makeInstance(SolrWriteService::class, 'localhost', 8999, '/solr/core_en/');
    }

    /**
     * @test
     */
    public function canExtractByQuery()
    {
        $testFilePath = $this->getFixturePathByName('testpdf.pdf');
            /** @var $extractQuery \ApacheSolrForTypo3\Solr\ExtractingQuery */
        $extractQuery = GeneralUtility::makeInstance(ExtractingQuery::class, $testFilePath);
        $extractQuery->setExtractOnly();
        $response = $this->solrWriteService->extractByQuery($extractQuery);
        $this->assertContains('PDF Test', $response[0], 'Could not extract text');
    }
}
