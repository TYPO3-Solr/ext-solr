<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

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

use ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Exception\DocumentPreparationException;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 *
 * @author Timo Schmidt
 */
class PageIndexerTest extends IntegrationTest
{

    /**
     * @return void
     */
    public function setUp() {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    public function tearDown()
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canIndexPageIntoSolr()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importDataSetFromFixture('can_index_into_solr.xml');

        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');
        $this->assertContains('"custom_stringS":"my text"', $solrContent, 'Document does not contains value build with typoscript');
    }

    /**
     * @test
     */
    public function canIndexPageWithCustomPageTypeIntoSolr()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importDataSetFromFixture('can_index_custom_pagetype_into_solr.xml');

        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"custom_stringS":"my text from custom page type"', $solrContent, 'Document does not contains value build with typoscript');
    }


    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexTranslatedPageToPageRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension3_table.sql');

        $additionalPageTca = include($this->getFixturePathByName('fake_extension3_pages_tca.php'));
        $GLOBALS['TCA']['pages']['columns']['page_relations'] = $additionalPageTca['columns']['page_relations'];
        $GLOBALS['TCA']['pages']['columns']['relations'] = $additionalPageTca['columns']['relations'];

        $this->importDataSetFromFixture('can_index_page_with_relation_to_page.xml');

        $this->executePageIndexer([], 1, 0, '', '', null, '', '', 0);
        $this->executePageIndexer([], 1, 0, '', '', null, '', '', 1);

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr('core_en');
        $this->waitToBeVisibleInSolr('core_de');

        $solrContentEn = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"title":"Page"', $solrContentEn, 'Solr did not contain the english page');
        $this->assertNotContains('relatedPageTitles_stringM', $solrContentEn, 'There is no relation for the original, so ther should not be a related field');

        $solrContentDe = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_de/select?q=*:*');
        $this->assertContains('"title":"Seite"', $solrContentDe, 'Solr did not contain the translated page');
        $this->assertContains('"relatedPageTitles_stringM":["Verwante Seite"]', $solrContentDe, 'Did not get content of releated field');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexPageToCategoryRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');

        $this->importDataSetFromFixture('can_index_page_with_relation_to_category.xml');
        $this->executePageIndexer([], 10, 0, '', '', null, '', '', 0);

        $this->waitToBeVisibleInSolr('core_en');

        $solrContentEn = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"title":"Sub page"', $solrContentEn, 'Solr did not contain the english page');
        $this->assertContains('"categories_stringM":["Test"]', $solrContentEn, 'There is no relation for the original, so ther should not be a related field');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
    }

    /**
     * @test
     */
    public function canIndexPageIntoSolrWithAdditionalFields()
    {
        //@todo additional fields indexer requires the hook to be activated which is normally done in ext_localconf.php
            // this needs to be unified with the PageFieldMappingIndexer registration.
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']['ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer'] = AdditionalFieldsIndexer::class;

        $this->importDataSetFromFixture('can_index_with_additional_fields_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

            // field values from index.queue.pages.fields.
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');

            // field values from index.additionalFields
        $this->assertContains('"additional_sortSubTitle_stringS":"subtitle"', $solrContent, 'Document does not contains value from index.additionFields');
        $this->assertContains('"additional_custom_stringS":"my text"', $solrContent, 'Document does not contains value from index.additionFields');
    }

    /**
     * @test
     */
    public function canIndexPageIntoSolrWithAdditionalFieldsFromRootLine()
    {
        $this->importDataSetFromFixture('can_overwrite_configuration_in_rootline.xml');
        $this->executePageIndexer([], 2);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        // field values from index.queue.pages.fields.
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"hello subpage"', $solrContent, 'Could not index subpage with custom field configuration into solr');
        $this->assertContains('"additional_stringS":"from rootline"', $solrContent, 'Document does not contain custom field from rootline');
    }

    /**
     * @test
     */
    public function canExecutePostProcessor()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPagePostProcessPageDocument']['TestPostProcessor'] = TestPostProcessor::class;

        $this->importDataSetFromFixture('can_index_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"postProcessorField_stringS":"postprocessed"', $solrContent, 'Field from post processor was not added');
    }

    /**
     * @test
     */
    public function canExecuteAdditionalPageIndexer()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments']['TestAdditionalPageIndexer'] = TestAdditionalPageIndexer::class;

        $this->importDataSetFromFixture('can_index_into_solr.xml');
        $this->executePageIndexer();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":2', $solrContent, 'Could not index document into solr');
        $this->assertContains('"custom_stringS":"my text"', $solrContent, 'Field from post processor was not added');
        $this->assertContains('"custom_stringS":"additional text"', $solrContent, 'Field from post processor was not added');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * There is following scenario:
     *
     *  [0]
     *  |
     *  ——[20] Shared-Pages (Not root)
     *  |   |
     *  |   ——[24] FirstShared (Not root)
     *  |
     *  ——[ 1] Page (Root)
     *  |
     *  ——[14] Mount Point (to [24] to show contents from)
     *
     * @test
     */
    public function canIndexMountedPage()
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;

        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importDataSetFromFixture('can_index_mounted_page.xml');
        $this->executePageIndexer([], 24, 0, '', '', null, '24-14');

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        $this->assertContains('"title":"FirstShared (Not root)"', $solrContent, 'Could not find content from mounted page in solr');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * There is following scenario:
     *
     *  [0]
     *  |
     *  ——[20] Shared-Pages (Not root)
     *  |   |
     *  |   ——[44] FirstShared (Not root)
     *  |
     *  ——[ 1] Page (Root)
     *  |
     *  ——[14] Mount Point (to [24] to show contents from)
     *
     *  |
     *  ——[ 2] Page (Root)
     *  |
     *  ——[24] Mount Point (to [24] to show contents from)
     * @test
     */
    public function canIndexMultipleMountedPage()
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;

        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importDataSetFromFixture('can_index_multiple_mounted_page.xml');
        $this->executePageIndexer([], 44, 0, '', '', null, '44-14');
        $this->executePageIndexer([], 44, 0, '', '', null, '44-24');

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        $this->assertContains('"numFound":2', $solrContent, 'Unexpected amount of documents in the core');

        $this->assertContains('"url":"index.php?id=44&MP=44-14"', $solrContent, 'Could not find document of first mounted page');
        $this->assertContains('"url":"index.php?id=44&MP=44-24"', $solrContent, 'Could not find document of second mounted page');
    }

    /**
     * This Test should tests, that TYPO3 CMS on FE does not die if page is not available.
     * If something goes wrong the exception must be thrown instead of dieing, to make marking the items as failed possible.
     *
     * @test
     */
    public function phpProcessDoesNotDieIfPageIsNotAvailable() {
        $this->applyXClassOverriddenTypoScriptFrontendController();
        $this->applyUsingErrorControllerForCMS9andAbove();
        $this->registerShutdownFunctionToPrintExplanationOf404HandlingOnCMSIfDieIsCalled();
        $this->expectException(PageNotFoundException::class);


        $this->importDataSetFromFixture('does_not_die_if_page_not_available.xml');
        define('EXT_SOLR_INDEXING_CONTEXT', true);
        $this->executePageIndexer(null, null, null, null, null, null, null, null, 3, ['sys_language_mode' => 'strict']);
    }

    /**
     * Registers shutdown function to print proper information about TYPO3 CMS behaviour on unavailable pages.
     */
    protected function registerShutdownFunctionToPrintExplanationOf404HandlingOnCMSIfDieIsCalled()
    {
        register_shutdown_function(function() {
            // prompt only after phpProcessDoesNotDieIfPageIsNotAvailable() test case
            if ($this->getName() !== 'phpProcessDoesNotDieIfPageIsNotAvailable') {
                return;
            }

            // don't show HTML or other stuff from CMS in output
            ob_clean();

            $message = PHP_EOL . PHP_EOL . PHP_EOL .
                'Note: This test case kills whole PHPUnit process on failing, which is expected behaviour, because TYPO3 CMS uses die() function in cases if page or record is not available.';
            $message .= PHP_EOL . PHP_EOL . 'TYPO3 CMS API for registering shutdown callbacks for handling of unavailable pages is changed.' . PHP_EOL .
                'TypoScriptFrontendController::pageUnavailableAndExit() is not called anymore.' . PHP_EOL .
                'Please refer to the TYPO3 CMS documentation and use new API for this functionality.';

            printf(PHP_EOL . PHP_EOL . "\033[01;31m%s\033[0m", $message);
        });
    }

    /**
     * @param array $typo3ConfVars
     * @param int $pageId
     * @param int $type
     * @param string $no_cache
     * @param string $cHash
     * @param null $_2
     * @param string $MP
     * @param string $RDCT
     * @param int $languageId
     * @throws \TYPO3\CMS\Core\Error\Http\ServiceUnavailableException
     */
    protected function executePageIndexer($typo3ConfVars = [], $pageId = 1, $type = 0, $no_cache = '', $cHash = '', $_2 = null, $MP = '', $RDCT = '', $languageId = 0, $additionalConfigs = [])
    {
        GeneralUtility::_GETset($languageId, 'L');
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();

        $config = [
            'config' => array_merge([
                'index_enable' => 1,
                'sys_language_uid' => $languageId
            ], $additionalConfigs)
        ];

        unset($GLOBALS['TSFE']);

        $TSFE = $this->getConfiguredTSFE($typo3ConfVars, $pageId, $type, $no_cache, $cHash, $_2, $MP, $RDCT, $config);
        $TSFE->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $GLOBALS['TSFE'] = $TSFE;

        /** @var $request \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest */
        $request = GeneralUtility::makeInstance(PageIndexerRequest::class);
        $request->setParameter('item', 4711);

        /** @var $request \ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse */
        $response = GeneralUtility::makeInstance(PageIndexerResponse::class);

        /** @var $pageIndexer  \ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer */
        $pageIndexer = GeneralUtility::makeInstance(PageIndexer::class);
        $pageIndexer->activate();
        $pageIndexer->processRequest($request, $response);
        $pageIndexer->hook_indexContent($TSFE);
    }

    /**
     * Simulates loading in ext_localconf.php loaded XClass for TSFE.
     */
    private function applyXClassOverriddenTypoScriptFrontendController()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TypoScriptFrontendController::class] = array(
            'className' => OverriddenTypoScriptFrontendController::class
        );
    }
}
