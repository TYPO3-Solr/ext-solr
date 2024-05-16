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

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

/**
 * Testcase to check if we can index page documents using the PageIndexer
 *
 * @author Timo Schmidt
 * (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
 */
class PageIndexerTest extends IntegrationTestBase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension3',
    ];

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

    #[Test]
    public function canIndexPageIntoSolr(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue.pages.fields {
              sortSubTitle_stringS = subtitle
              custom_stringS = TEXT
              custom_stringS.value = my text
            }
            '
        );

        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');
        self::assertStringContainsString('"custom_stringS":"my text"', $solrContent, 'Document does not contains value build with typoscript');
    }

    #[Test]
    public function canIndexPageWithCustomPageTypeIntoSolr(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_pagetype_into_solr.csv');

        // @TODO: Check page type in fixture, currently not set to 130
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue.mytype < plugin.tx_solr.index.queue.pages
            plugin.tx_solr.index.queue.mytype {
              allowedPageTypes = 130
              additionalWhereClause = doktype = 130
              fields.custom_stringS = TEXT
              fields.custom_stringS.value = my text from custom page type
            }
            '
        );

        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"custom_stringS":"my text from custom page type"', $solrContent, 'Document does not contains value build with typoscript');
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     */
    #[Test]
    public function canIndexTranslatedPageToPageRelation(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_page_with_relation_to_page.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue.pages.fields.relatedPageTitles_stringM = SOLR_RELATION
            plugin.tx_solr.index.queue.pages.fields.relatedPageTitles_stringM {
              localField = page_relations
              enableRecursiveValueResolution = 0
              multiValue = 1
            }
            '
        );

        $this->indexQueuedPage(2, '/en/');
        $this->indexQueuedPage(2, '/de/');

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
     */
    #[Test]
    public function canIndexPageToCategoryRelation(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty('core_en');

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_page_with_relation_to_category.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue.pages.fields.categories_stringM = SOLR_RELATION
            plugin.tx_solr.index.queue.pages.fields.categories_stringM {
              localField = categories
              foreignLabelField = title
              multiValue = 1
            }
            '
        );

        $this->indexQueuedPage(10);

        $this->waitToBeVisibleInSolr('core_en');

        $solrContentEn = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"title":"Sub page"', $solrContentEn, 'Solr did not contain the english page');
        self::assertStringContainsString('"categories_stringM":["Test"]', $solrContentEn, 'There is no relation for the original, so ther should not be a related field');

        $this->cleanUpSolrServerAndAssertEmpty('core_en');
    }

    #[Test]
    public function canIndexPageIntoSolrWithAdditionalFields(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_with_additional_fields_into_solr.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.additionalFields {
              additional_sortSubTitle_stringS = subtitle
              additional_custom_stringS = TEXT
              additional_custom_stringS.value = my text
            }
            '
        );
        $this->indexQueuedPage(2);

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

    #[Test]
    public function canIndexPageIntoSolrWithAdditionalFieldsFromRootLine(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_overwrite_configuration_in_rootline.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue.pages.fields.additional_stringS = TEXT
            plugin.tx_solr.index.queue.pages.fields.additional_stringS.value = from rootline
            '
        );

        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');

        // field values from index.queue.pages.fields.
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello subpage"', $solrContent, 'Could not index subpage with custom field configuration into solr');
        self::assertStringContainsString('"additional_stringS":"from rootline"', $solrContent, 'Document does not contain custom field from rootline');
    }

    #[Test]
    public function canExecutePostProcessor(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage(2);

        // we wait to make sure the document will be available in solr
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"postProcessorField_stringS":"postprocessed"', $solrContent, 'Field from post processor was not added');
    }

    #[Test]
    public function canExecuteAdditionalPageIndexer(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');
        $this->addSimpleFrontendRenderingToTypoScriptRendering(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue.pages.fields {
              custom_stringS = TEXT
              custom_stringS.value = my text
            }
            '
        );
        $this->indexQueuedPage(2, '/en/', ['additionalTestPageIndexer' => true]);

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
     */
    #[Test]
    public function canIndexMountedPage(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;

        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_mounted_page.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
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
     */
    #[Test]
    public function canIndexMultipleMountedPage(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_multiple_mounted_page.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
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
     */
    #[Test]
    public function phpProcessDoesNotDieIfPageIsNotAvailable(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/does_not_die_if_page_not_available.csv');
        $response = $this->indexQueuedPage(1636120156);

        $decodedResponse = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($decodedResponse, 'Response couldn\'t be decoded');

        $actionResults = unserialize($decodedResponse['actionResults']['indexPage']);
        self::assertFalse($actionResults['pageIndexed'] ?? null, 'Index page result not set to false as expected!');
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
        $item = $this->getIndexQueueItem(4711);
        return $this->executePageIndexer($url, $item);
    }
}
