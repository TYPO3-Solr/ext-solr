<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Index;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Testcase for the record indexer
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class IndexerServiceTest extends IntegrationTest
{

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance('TYPO3\CMS\Core\Authentication\BackendUserAuthentication');
        $GLOBALS['BE_USER'] = $beUser;

        /** @var $languageService  \TYPO3\CMS\Lang\LanguageService */
        $languageService = GeneralUtility::makeInstance('TYPO3\CMS\Lang\LanguageService');
        $languageService->csConvObj = GeneralUtility::makeInstance('TYPO3\CMS\Core\Charset\CharsetConverter');
        $GLOBALS['LANG'] = $languageService;
    }

    /**
     * @param string $table
     * @param integer $uid
     * @return \Apache_Solr_Response
     */
    protected function addToIndexQueue($table, $uid)
    {
        // write an index queue item
        $this->indexQueue->updateItem($table, $uid, null, time());
    }


    public function canResolveAbsRefPrefixDataProvider() {
        return [
            'absRefPrefixIsAuto' => [
                'absRefPrefix' => 'auto',
                'expectedUrl' => '/index.php?id=1&tx_ttnews%5Btt_news%5D=111&L=0&cHash=0c0f0e52711b08554a6658230061fb1a',
            ],
            'absRefPrefixIsSlash' => [
                'absRefPrefix' => 'slash',
                'expectedUrl' => '/index.php?id=1&tx_ttnews%5Btt_news%5D=111&L=0&cHash=0c0f0e52711b08554a6658230061fb1a',
            ],
            'absRefPrefixIsFoo' => [
                'absRefPrefix' => 'foo',
                'expectedUrl' => '/foo/index.php?id=1&tx_ttnews%5Btt_news%5D=111&L=0&cHash=0c0f0e52711b08554a6658230061fb1a',
            ],
            'absRefPrefixIsNone' => [
                'absRefPrefix' => 'none',
                'expectedUrl' => 'index.php?id=1&tx_ttnews%5Btt_news%5D=111&L=0&cHash=0c0f0e52711b08554a6658230061fb1a',
            ]
        ];
    }
    /**
     *
     * @dataProvider canResolveAbsRefPrefixDataProvider
     * @param string $absRefPrefix
     * @param string $expectedUrl
     * @test
     */
    public function canResolveAbsRefPrefix($absRefPrefix, $expectedUrl)
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePath('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePath('fake_extension2_directrelated_tca.php'));

        $this->importDataSetFromFixture('can_index_custom_record_absRefPrefix_'.$absRefPrefix.'.xml');

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);
        // overwrite the absRefPrefix from the imported fixture in the created TSFE right before indexing

        /** @var $indexService IndexService */
        $site = Site::getFirstAvailableSite();
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);
        $indexService->setContextForcedWebRoot(PATH_site);

        // run the indexer
        $indexService->indexItems(1);

        // do we have the record in the index with the value from the mm relation?
        sleep(2);
        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"url":"'.$expectedUrl.'"', $solrContent, 'Generated unexpected url with absRefPrefix = auto');
        $this->assertNotContains('auto', $solrContent, 'absRefPrefix=auto was not resolved');
        $this->cleanUpSolrServerAndAssertEmpty();
    }
}