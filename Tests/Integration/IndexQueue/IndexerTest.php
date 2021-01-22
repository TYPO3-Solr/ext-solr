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

use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Testcase for the record indexer
 *
 * @author Timo Schmidt
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
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->indexer = GeneralUtility::makeInstance(Indexer::class);

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;

        /** @var $languageService  \TYPO3\CMS\Core\Localization\LanguageService */
        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;

        $_SERVER['HTTP_HOST'] = 'test.local.typo3.org';
        $request = ServerRequestFactory::fromGlobals();
        $handlerMock = $this->getMockBuilder( \Psr\Http\Server\RequestHandlerInterface::class)->getMock();
        $normalizer = new \TYPO3\CMS\Core\Middleware\NormalizedParamsAttribute();
        $normalizer->process($request, $handlerMock);

    }

    public function tearDown()
    {
        parent::tearDown();
        $this->cleanUpSolrServerAndAssertEmpty();
        unset($this->indexQueue, $this->indexer);
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
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_mm_relation.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["the tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @return array
     */
    public function getTranslatedRecordDataProvider()
    {
        return [
            'with_l_paramater' => ['can_index_custom_translated_record_with_l_param.xml'],
            'without_l_paramater' => ['can_index_custom_translated_record_without_l_param.xml'],
            'without_l_paramater_and_content_fallback' => ['can_index_custom_translated_record_without_l_param_and_content_fallback.xml']
        ];
    }

    /**
     * @dataProvider getTranslatedRecordDataProvider
     * @test
     */
    public function testCanIndexTranslatedCustomRecord($fixture)
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));

        $this->importDataSetFromFixture($fixture);

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 777);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr('core_en');
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":2', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"original"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"original2"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"url":"http://testone.site/en/?tx_foo%5Buid%5D=88', $solrContent, 'Can not build typolink as expected');
        $this->assertContains('"url":"http://testone.site/en/?tx_foo%5Buid%5D=777', $solrContent, 'Can not build typolink as expected');

        $this->waitToBeVisibleInSolr('core_de');
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_de/select?q=*:*');
        $this->assertContains('"numFound":2', $solrContent, 'Could not find translated record in solr document into solr');
        if ($fixture === 'can_index_custom_translated_record_without_l_param_and_content_fallback.xml') {
            $this->assertContains('"title":"original"', $solrContent, 'Could not index  translated document into solr');
            $this->assertContains('"title":"original2"', $solrContent, 'Could not index  translated document into solr');
        } else {
            $this->assertContains('"title":"translation"', $solrContent, 'Could not index  translated document into solr');
            $this->assertContains('"title":"translation2"', $solrContent, 'Could not index  translated document into solr');
        }
        $this->assertContains('"url":"http://testone.site/de/?tx_foo%5Buid%5D=88', $solrContent, 'Can not build typolink as expected');
        $this->assertContains('"url":"http://testone.site/de/?tx_foo%5Buid%5D=777', $solrContent, 'Can not build typolink as expected');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');
    }

    /**
     * This testcase should check if we can queue an custom record with ordered MM relations.
     *
     * @test
     */
    public function canIndexItemWithMMRelationsInTheExpectedOrder()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_multiple_mm_relations.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the values from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContentJson = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $solrContent = json_decode($solrContentJson, true);
        $solrContentResponse = $solrContent['response'];

        $this->assertArrayHasKey('docs', $solrContentResponse, 'Did not find docs in solr response');

        $solrDocs = $solrContentResponse['docs'];

        $this->assertCount(1, $solrDocs, 'Could not found index document into solr');
        $this->assertInternalType('array', $solrDocs[0]);
        $this->assertEquals('testnews', (string)$solrDocs[0]['title'], 'Title of Solr document is not as expected.');
        $this->assertArrayHasKey('category_stringM', $solrDocs[0], 'Did not find MM related tags.');
        $this->assertCount(2, $solrDocs[0]['category_stringM'], 'Did not find all MM related tags.');
        $this->assertSame(['the tag', 'another tag'], $solrDocs[0]['category_stringM']);

        $this->cleanUpSolrServerAndAssertEmpty();
    }


    /**
     * This testcase should check if we can queue an custom record with MM relations.
     *
     * @test
     */
    public function canIndexTranslatedItemWithMMRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_translated_record_with_mm_relation.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr('core_de');
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_de/select?q=*:*');

        $this->assertContains('"category_stringM":["translated tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"translation"', $solrContent, 'Could not index document into solr');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');
     }

    /**
     * This testcase should check if we can queue an custom record with multiple MM relations.
     *
     * @test
     */
    public function canIndexMultipleMMRelatedItems()
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->writeDefaultSolrTestSiteConfiguration();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_multiple_mm_relations.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr('core_en');
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $decodedSolrContent = json_decode($solrContent);
        // @extensionScannerIgnoreLine
        $tags = $decodedSolrContent->response->docs[0]->tags_stringM;

        $this->assertSame(['the tag', 'another tag'], $tags, $solrContent, 'Did not find MM related tags');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexItemWithMMRelationAndAdditionalWhere()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_mm_relationAndAdditionalWhere.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["another tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexItemWithMMRelationToATranslatedPage()
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');


        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_translated_record_with_mm_relation_to_a_page.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr('core_en');
        $this->waitToBeVisibleInSolr('core_de');

        $solrContentEn = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $solrContentDe = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_de/select?q=*:*');

        $this->assertContains('"relatedPageTitles_stringM":["Related page"]', $solrContentEn, 'Can not find related page title');
        $this->assertContains('"relatedPageTitles_stringM":["Translated related page"]', $solrContentDe, 'Can not find translated related page title');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');
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
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_direct_relation.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["the category"]', $solrContent, 'Did not find direct related category');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sysCategoryId_stringM":["1"]', $solrContent, 'Uid of related sys_category couldn\'t be resolved by using "foreignLabelField"');
        $this->assertContains('"sysCategory_stringM":["sys_category"]', $solrContent, 'Label of related sys_category couldn\'t be resolved by using "foreignLabelField" and "enableRecursiveValueResolution"');
        $this->assertContains('"sysCategoryDescription_stringM":["sys_category description"]', $solrContent, 'Description of related sys_category couldn\'t be resolved by using "foreignLabelField" and "enableRecursiveValueResolution"');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if multiple direct relations can be resolved with the RELATION configuration
     *
     * @test
     */
    public function canIndexItemWithMultipleDirectRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_multiple_direct_relations.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $decodedSolrContent = json_decode($solrContent);

        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');

        // @extensionScannerIgnoreLine
        $category_stringM = $decodedSolrContent->response->docs[0]->category_stringM;
        $this->assertSame(['the category','the second category'], $category_stringM, 'Unexpected category_stringM value');
        // @extensionScannerIgnoreLine
        $sysCategoryId_stringM = $decodedSolrContent->response->docs[0]->sysCategoryId_stringM;
        $this->assertSame(['1','2'], $sysCategoryId_stringM, 'Unexpected sysCategoryId_stringM value');
        // @extensionScannerIgnoreLine
        $sysCategory_stringM = $decodedSolrContent->response->docs[0]->sysCategory_stringM;
        $this->assertSame(['sys_category','sys_category 2'], $sysCategory_stringM, 'Unexpected sysCategory_stringM value');
        // @extensionScannerIgnoreLine
        $sysCategoryDescription_stringM = $decodedSolrContent->response->docs[0]->sysCategoryDescription_stringM;
        $this->assertSame(['sys_category description','second sys_category description'], $sysCategoryDescription_stringM, 'Unexpected sysCategoryDescription_stringM value');

        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if direct relations can be resolved with the RELATION configuration
     * and could be limited with an additionalWhere clause at the same time
     *
     * @test
     */
    public function canIndexItemWithDirectRelationAndAdditionalWhere()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_direct_relationAndAdditionalWhere.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["another category"]', $solrContent, 'Did not find direct related category');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canUseConfigurationFromTemplateInRootLine()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_configuration_in_rootline.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"fieldFromRootLine_stringS":"TESTNEWS"', $solrContent, 'Did not find field configured in rootline');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsInterfaceOnly()
    {
        $this->expectException(\InvalidArgumentException::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = \ApacheSolrForTypo3\Solr\IndexQueue\AdditionalIndexQueueItemIndexer::class;
        $document = new Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);
        $this->callInaccessibleMethod($this->indexer,'getAdditionalDocuments', $item, 0, $document);
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsNotImplementingInterface()
    {
        $this->expectException(\UnexpectedValueException::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = \ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Helpers\DummyIndexer::class;
        $document = new Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);
        $this->callInaccessibleMethod($this->indexer, 'getAdditionalDocuments', $item, 0, $document);
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsNonExistingClass()
    {
        $this->expectException(\InvalidArgumentException::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = 'NonExistingClass';
        $document = new Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);

        $result = $this->callInaccessibleMethod($this->indexer,'getAdditionalDocuments', $item, 0, $document);
    }

    /**
     * @test
     */
    public function canGetAdditionalDocuments()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = \ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Helpers\DummyAdditionalIndexQueueItemIndexer::class;
        $document = new Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);

        $result = $this->callInaccessibleMethod($this->indexer,'getAdditionalDocuments', $item, 0, $document);
        $this->assertSame([], $result);
    }

    /**
     * @test
     */
    public function testCanIndexCustomRecordOutsideOfSiteRoot()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_outside_site_root.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"external testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function testCanIndexCustomRecordOutsideOfSiteRootWithTemplate()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_outside_site_root_with_template.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 1);

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":2', $solrContent, 'Could not index document into solr');

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*&fq=site:testone.site');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"url":"http://testone.site/en/"', $solrContent, 'Item was indexed with false site UID');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @param string $table
     * @param int $uid
     * @return ResponseAdapter
     */
    protected function addToQueueAndIndexRecord($table, $uid)
    {
        // write an index queue item
        $this->indexQueue->updateItem($table, $uid);

        // run the indexer
        $items = $this->indexQueue->getItems($table, $uid);
        foreach ($items as $item) {
            $result = $this->indexer->index($item);
        }

        return $result;
    }

    /**
     * @test
     */
    public function getSolrConnectionsByItemReturnsNoDefaultConnectionIfRootPageIsHideDefaultLanguage()
    {
        $this->importDataSetFromFixture('can_index_with_rootPage_set_to_hide_default_language.xml');
        $itemMetaData = [
            'uid' => 1,
            'root' => 1,
            'item_type' => 'pages',
            'item_uid' => 1,
            'indexing_configuration' => '',
            'has_indexing_properties' => false
        ];
        $item = new Item($itemMetaData);

        $result = $this->callInaccessibleMethod($this->indexer,'getSolrConnectionsByItem', $item);

        $this->assertInstanceOf(SolrConnection::class, $result[1], "Expect SolrConnection object in connection array item with key 1.");
        $this->assertCount(1, $result, "Expect only one SOLR connection.");
        $this->assertArrayNotHasKey(0, $result, "Expect, that there is no solr connection returned for default language,");
    }

    /**
     * @test
     */
    public function getSolrConnectionsByItemReturnsNoDefaultConnectionDefaultLanguageIsHiddenInSiteConfig()
    {
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort('http', 'localhost', 8999, true);
        $this->importDataSetFromFixture('can_index_with_rootPage_set_to_hide_default_language.xml');
        $itemMetaData = [
            'uid' => 1,
            'root' => 1,
            'item_type' => 'pages',
            'item_uid' => 1,
            'indexing_configuration' => '',
            'has_indexing_properties' => false
        ];
        $item = new Item($itemMetaData);

        $result = $this->callInaccessibleMethod($this->indexer,'getSolrConnectionsByItem', $item);

        $this->assertEmpty($result[0], 'Connection for default language was expected to be empty');
        $this->assertInstanceOf(SolrConnection::class, $result[1], "Expect SolrConnection object in connection array item with key 1.");
        $this->assertCount(1, $result, "Expect only one SOLR connection.");
        $this->assertArrayNotHasKey(0, $result, "Expect, that there is no solr connection returned for default language,");
    }

    /**
     * @test
     */
    public function getSolrConnectionsByItemReturnsProperItemInNestedSite()
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort();
        $this->importDataSetFromFixture('can_index_with_multiple_sites.xml');
        $result = $this->addToQueueAndIndexRecord('pages', 1);
        self::assertTrue($result, 'Indexing was not indicated to be successful');
        $result = $this->addToQueueAndIndexRecord('pages', 111);
        self::assertTrue($result, 'Indexing was not indicated to be successful');
        $result = $this->addToQueueAndIndexRecord('pages', 120);
        self::assertTrue($result, 'Indexing was not indicated to be successful');
        $this->waitToBeVisibleInSolr();
        $solrContentJson = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $solrContent = json_decode($solrContentJson, true);
        $solrContentResponse = $solrContent['response'];
        self::assertArrayHasKey('docs', $solrContentResponse, 'Did not find docs in solr response');

        $solrDocs = $solrContentResponse['docs'];
        self::assertCount(3, $solrDocs, 'Could not found index document into solr');

        $sites = array_column($solrDocs, 'site');
        self::assertEquals('testone.site', $sites[0]);
        self::assertEquals('testtwo.site', $sites[1]);
        self::assertEquals('testtwo.site', $sites[2]);
    }
}
