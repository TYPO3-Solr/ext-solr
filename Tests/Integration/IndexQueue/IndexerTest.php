<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

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

use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the record indexer
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class IndexerTest extends IntegrationTest
{

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var Indexer
     */
    protected $indexer;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Queue');
        $this->indexer = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Indexer');

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance('TYPO3\CMS\Core\Authentication\BackendUserAuthentication');
        $GLOBALS['BE_USER'] = $beUser;

        /** @var $languageService  \TYPO3\CMS\Lang\LanguageService */
        $languageService = GeneralUtility::makeInstance('TYPO3\CMS\Lang\LanguageService');
        $languageService->csConvObj = GeneralUtility::makeInstance('TYPO3\CMS\Core\Charset\CharsetConverter');
        $GLOBALS['LANG'] = $languageService;
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations.
     *
     * @test
     */
    public function canIndexItemWithMMRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePath('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePath('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_mm_relation.xml');

        // write an index queue item
        $this->indexQueue->updateItem('tx_fakeextension_domain_model_bar', 88);

        // run the indexer
        $items = $this->indexQueue->getItems('tx_fakeextension_domain_model_bar', 88);
        foreach ($items as $item) {
            $result =  $this->indexer->index($item);
        }

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        sleep(3);
        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["the tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations.
     *
     * @test
     */
    public function canIndexTranslatedItemWithMMRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePath('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePath('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_translated_record_with_mm_relation.xml');

        // write an index queue item
        $this->indexQueue->updateItem('tx_fakeextension_domain_model_bar', 88);

        // run the indexer
        $items = $this->indexQueue->getItems('tx_fakeextension_domain_model_bar', 88);
        foreach ($items as $item) {
            $result =  $this->indexer->index($item);
        }

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        sleep(3);
        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["another tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"translation"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if direct relations can be resolved with the RELATION configuration
     *
     * @test
     */
    public function canIndexItemWithDirectRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePath('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePath('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_direct_relation.xml');

        // write an index queue item
        $this->indexQueue->updateItem('tx_fakeextension_domain_model_bar', 111);

        // run the indexer
        $items = $this->indexQueue->getItems('tx_fakeextension_domain_model_bar', 111);
        foreach ($items as $item) {
            $result =  $this->indexer->index($item);
        }

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        sleep(3);
        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["the category"]', $solrContent, 'Did not find direct related category');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }
}
