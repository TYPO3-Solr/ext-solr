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

use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\PageUriBuilder;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;

/**
 * Testcase to check if we can index page documents using the SolrIndexingMiddleware
 */
class PageIndexerTest extends IntegrationTestBase
{
    protected array $testExtensionsToLoad = [
        'apache-solr-for-typo3/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension3',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    #[Test]
    public function canIndexPageIntoSolr(): void
    {
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
            ',
        );

        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');
        self::assertStringContainsString('"custom_stringS":"my text"', $solrContent, 'Document does not contains value build with typoscript');
    }

    #[Test]
    public function canIndexPageWithCustomPageTypeIntoSolr(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_pagetype_into_solr.csv');

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
            ',
        );

        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"custom_stringS":"my text from custom page type"', $solrContent, 'Document does not contains value build with typoscript');
    }

    #[Test]
    public function canIndexTranslatedPageToPageRelation(): void
    {
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
            ',
        );

        $this->indexQueuedPage(4711, 0);
        $this->indexQueuedPage(4711, 1);

        $this->waitToBeVisibleInSolr('core_en');
        $this->waitToBeVisibleInSolr('core_de');

        $solrContentEn = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"title":"Page"', $solrContentEn, 'Solr did not contain the english page');
        self::assertStringNotContainsString('relatedPageTitles_stringM', $solrContentEn, 'There is no relation for the original, so there should not be a related field');

        $solrContentDe = file_get_contents($this->getSolrCoreUrl('core_de') . '/select?q=*:*');
        self::assertStringContainsString('"title":"Seite"', $solrContentDe, 'Solr did not contain the translated page');
        self::assertStringContainsString('"relatedPageTitles_stringM":["Verwandte Seite"]', $solrContentDe, 'Did not get content of related field');
    }

    #[Test]
    public function canIndexPageToCategoryRelation(): void
    {
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
            ',
        );

        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr('core_en');

        $solrContentEn = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"title":"Sub page"', $solrContentEn, 'Solr did not contain the english page');
        self::assertStringContainsString('"categories_stringM":["Test"]', $solrContentEn, 'There is no relation for the original, so ther should not be a related field');
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
            ',
        );
        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');

        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello solr"', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"sortSubTitle_stringS":"the subtitle"', $solrContent, 'Document does not contain subtitle');

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
            ',
        );

        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');

        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"title":"hello subpage"', $solrContent, 'Could not index subpage with custom field configuration into solr');
        self::assertStringContainsString('"additional_stringS":"from rootline"', $solrContent, 'Document does not contain custom field from rootline');
    }

    #[Test]
    public function canExecutePostProcessor(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_into_solr.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
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
            ',
        );
        $this->indexQueuedPage(4711, 0, ['additionalTestPageIndexer' => 1]);

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":2', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"custom_stringS":"my text"', $solrContent, 'Field from post processor was not added');
        self::assertStringContainsString('"custom_stringS":"additional text"', $solrContent, 'Field from post processor was not added');
    }

    #[Test]
    public function canIndexMountedPage(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_mounted_page.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"title":"FirstShared"', $solrContent, 'Could not find content from mounted page in Solr');
    }

    #[Test]
    public function canIndexCrossSiteMounts(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['FE']['enable_mount_pids'] = 1;

        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_mounted_page_from_another_site.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->indexQueuedPage();

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"title":"FirstShared"', $solrContent, 'Could not find content from mounted page in Solr');
    }

    #[Test]
    public function canIndexMultipleMountedPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_multiple_mounted_page.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');

        $this->addTypoScriptToTemplateRecord(111, 'config.index_enable = 1');
        $this->indexQueuedPage();
        $this->indexQueuedPage(4712);

        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');

        self::assertStringContainsString('"numFound":2', $solrContent, 'Unexpected amount of documents in the core');

        self::assertStringContainsString('/pages/44/44-14/', $solrContent, 'Could not find document of first mounted page');
        self::assertStringContainsString('/pages/44/44-24/', $solrContent, 'Could not find document of second mounted page');
    }

    #[Test]
    public function phpProcessDoesNotDieIfPageIsNotAvailable(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/does_not_die_if_page_not_available.csv');
        $response = $this->indexQueuedPage(forceUrl: 'http://testone.site/?id=1636120156');

        // The new sub-request pipeline returns an error status for unavailable pages
        // instead of a JSON response — the important thing is that the process doesn't die.
        self::assertGreaterThanOrEqual(400, $response->getStatusCode(), 'Expected error status for unavailable page');
    }

    /**
     * Executes a Frontend request to trigger page indexing via SolrIndexingMiddleware.
     */
    protected function indexQueuedPage(
        int $indexQueueItem = 4711,
        int $siteLanguageUid = 0,
        array $additionalQueryParams = [],
        string $forceUrl = '',
    ): ResponseInterface {
        $item = $this->getIndexQueueItem($indexQueueItem);

        if ($forceUrl !== '') {
            return $this->executePageIndexer($forceUrl, $item);
        }

        $uriBuilder = GeneralUtility::makeInstance(PageUriBuilder::class);
        $mountPointParameter = '';
        if ($item->hasIndexingProperty('isMountedPage')) {
            $mountPointParameter = $item->getIndexingProperty('mountPageSource')
                . '-' . $item->getIndexingProperty('mountPageDestination');
        }
        $url = $uriBuilder->getPageIndexingUriFromPageItemAndLanguageId(
            $item,
            $siteLanguageUid,
            $mountPointParameter,
        );

        if ($additionalQueryParams !== []) {
            $url = new Uri($url);
            $queryString = ltrim(
                $url->getQuery()
                    . '&id=' . $item->getRecordUid()
                    . GeneralUtility::implodeArrayForUrl('', $additionalQueryParams),
                '&',
            );
            $cacheHash = GeneralUtility::makeInstance(CacheHashCalculator::class)->generateForParameters($queryString);
            if ($cacheHash) {
                $queryString .= '&cHash=' . $cacheHash;
            }
            $url = (string)$url->withQuery($queryString);
        }

        return $this->executePageIndexer($url, $item);
    }
}
