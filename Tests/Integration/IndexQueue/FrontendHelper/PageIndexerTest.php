<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\AdditionalFieldsIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 *
 * @author Timo Schmidt
 * (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
 */
class PageIndexerTest extends IntegrationTest
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension3',
    ];

    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    /**
     * @throws NoSuchCacheException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * Executed after each test. Emptys solr and checks if the index is empty
     */
    protected function tearDown(): void
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');

        $this->indexQueuedPage();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');
        self::assertStringContainsString('"custom_stringS":"my text"', $solrContent, 'Document does not contains value build with typoscript');
    }

    /**
     * @test
     */
    public function canIndexPageWithCustomPageTypeIntoSolr()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_pagetype_into_solr.csv');

        $this->indexQueuedPage();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"custom_stringS":"my text from custom page type"', $solrContent, 'Document does not contains value build with typoscript');
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_page_with_relation_to_page.csv');

        $this->indexQueuedPage(1, '/en/');
        $this->indexQueuedPage(1, '/de/');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr('core_en');
        $this->waitToBeVisibleInSolr('core_de');

        $solrContentEn = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"Page"', $solrContentEn, 'Solr did not contain the english page');
        self::assertStringNotContainsString('relatedPageTitles_stringM', $solrContentEn, 'There is no relation for the original, so there should not be a related field');

        $solrContentDe = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_de/select?q=*:*');
        self::assertStringContainsString('"title":"Seite"', $solrContentDe, 'Solr did not contain the translated page');
        self::assertStringContainsString('"relatedPageTitles_stringM":["Verwandte Seite"]', $solrContentDe, 'Did not get content of related field');

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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_page_with_relation_to_category.csv');
        $this->indexQueuedPage(10);

        $this->waitToBeVisibleInSolr('core_en');

        $solrContentEn = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"Sub page"', $solrContentEn, 'Solr did not contain the english page');
        self::assertStringContainsString('"categories_stringM":["Test"]', $solrContentEn, 'There is no relation for the original, so ther should not be a related field');

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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_with_additional_fields_into_solr.csv');
        $this->indexQueuedPage();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        // field values from index.queue.pages.fields.
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');

        // field values from index.additionalFields
        self::assertStringContainsString('"additional_sortSubTitle_stringS":"subtitle"', $solrContent, 'Document does not contains value from index.additionFields');
        self::assertStringContainsString('"additional_custom_stringS":"my text"', $solrContent, 'Document does not contains value from index.additionFields');
    }

    /**
     * @test
     */
    public function canIndexPageIntoSolrWithAdditionalFieldsFromRootLine()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_overwrite_configuration_in_rootline.csv');
        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        // field values from index.queue.pages.fields.
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello subpage"', $solrContent, 'Could not index subpage with custom field configuration into solr');
        self::assertStringContainsString('"additional_stringS":"from rootline"', $solrContent, 'Document does not contain custom field from rootline');
    }

    /**
     * @test
     */
    public function canExecutePostProcessor()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');
        $this->indexQueuedPage();

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"postProcessorField_stringS":"postprocessed"', $solrContent, 'Field from post processor was not added');
    }

    /**
     * @test
     */
    public function canExecuteAdditionalPageIndexer()
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');
        $this->indexQueuedPage(1, '/en/', ['additionalTestPageIndexer' => true]);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":2', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"custom_stringS":"my text"', $solrContent, 'Field from post processor was not added');
        self::assertStringContainsString('"custom_stringS":"additional text"', $solrContent, 'Field from post processor was not added');
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_mounted_page.csv');
        $this->indexQueuedPage(24, '/en/', ['MP' => '24-14']);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"FirstShared (Not root)"', $solrContent, 'Could not find content from mounted page in solr');
    }

    /**
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
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_multiple_mounted_page.csv');
        $this->indexQueuedPage(44, '/en/', ['MP' => '44-14']);
        $this->indexQueuedPage(44, '/en/', ['MP' => '44-24']);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        self::assertStringContainsString('"numFound":2', $solrContent, 'Unexpected amount of documents in the core');

        self::assertStringContainsString('/pages/44/44-14/', $solrContent, 'Could not find document of first mounted page');
        self::assertStringContainsString('/pages/44/44-24/', $solrContent, 'Could not find document of second mounted page');
    }

    /**
     * This Test should test, that TYPO3 CMS on FE does not die if page is not available.
     * If something goes wrong the exception must be thrown instead of dying, to make marking the items as failed possible.
     *
     * @test
     */
    public function phpProcessDoesNotDieIfPageIsNotAvailable()
    {
        // @todo: 1636120156
        self::markTestIncomplete('The behaviour since TYPO3 11 and EXT:solr 11.5 must be checked twice, to be able to finalize request properly and mark items failed.');
        $this->registerShutdownFunctionToPrintExplanationOf404HandlingOnCMSIfDieIsCalled();
        $this->expectException(SiteNotFoundException::class);

        $this->importCSVDataSet(__DIR__ . '/Fixtures/does_not_die_if_page_not_available.csv');
        $this->indexQueuedPage(1636120156);
    }

    /**
     * Registers shutdown function to print proper information about TYPO3 CMS behaviour on unavailable pages.
     */
    protected function registerShutdownFunctionToPrintExplanationOf404HandlingOnCMSIfDieIsCalled()
    {
        register_shutdown_function(function () {
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
     * Executes a Frontend request within the same PHP process (possible since TYPO3 v11).
     */
    protected function indexQueuedPage(int $pageId = 1, string $siteLanguageBase = '/en/', $additionalQueryParams = [], string $domain = 'http://testone.site'): ResponseInterface
    {
        $additionalQueryParams['id'] = $pageId;
        $additionalQueryParams = array_filter($additionalQueryParams);
        $queryString = http_build_query($additionalQueryParams, '', '&');
        $cacheHash = GeneralUtility::makeInstance(CacheHashCalculator::class)->generateForParameters($queryString);
        if ($cacheHash) {
            $queryString .= '&cHash=' . $cacheHash;
        }
        $url = rtrim($domain, '/') . '/' . ltrim($siteLanguageBase, '/') . '?' . $queryString;

        // Now add the headers for item 4711 to the request
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');
        $item = $connection->select(['*'], 'tx_solr_indexqueue_item', ['uid' => 4711])->fetchAssociative();
        return $this->executePageIndexer($url, new Item($item));
    }
}
