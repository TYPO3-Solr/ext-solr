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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Security;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use ApacheSolrForTypo3\Solr\Tests\Integration\TSFETestBootstrapper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Regression guard for SST #2026050810000025:
 * the highlighter must not surface `field:*` queries as a Solr HTTP 500 field-existence oracle.
 */
#[Group('frontend')]
#[Group('security')]
class QueryInjectionViaRawSolrSyntaxTest extends IntegrationTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/default_search_results_plugin.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            config.index_enable = 1
            [page["uid"] == 2022]
            page.10 = RECORDS
            page.10 {
              source = 2022
              dontCheckPid = 1
              tables = tt_content
              wrap >
            }
            [end]
            ',
        );
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sst_2026050810000025_query_injection.csv');
        $this->indexPages([2, 3, 4]);
        $this->assertSolrContainsDocumentCount(3);
    }

    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    #[Test]
    public function literalTokenSearchReturnsExpectedDocument(): void
    {
        $response = $this->executeSearch('markeralpha235567');
        self::assertStringContainsString(
            'markeralpha235567',
            $response,
            'Sanity check failed: literal-token search for the alpha marker did not return the alpha document.',
        );
    }

    /** PDF §3.1 — `siteHash:*` must not enumerate documents via field selector. */
    #[Test]
    public function wildcardFieldEnumerationOperatorMustNotEnumerateIndexedDocuments(): void
    {
        $response = $this->executeSearch('siteHash:*');
        foreach (['markeralpha235567', 'markerbeta235567', 'markergamma235567'] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $response,
                'SST 235567 / PDF §3.1: siteHash:* field enumeration leaked ' . $marker,
            );
        }
    }

    /** PDF §3.2 — prefix wildcard must not enable per-character value extraction. */
    #[Test]
    public function prefixWildcardOperatorMustNotEnableBlindValueExtraction(): void
    {
        $response = $this->executeSearch('title:markeralpha*');
        self::assertStringNotContainsString(
            'markeralpha235567',
            $response,
            'SST 235567 / PDF §3.2: prefix-wildcard (title:markeralpha*) leaked the alpha document.',
        );
    }

    /** PDF §3.3 — `?` wildcard must not enable length detection on indexed values. */
    #[Test]
    public function singleCharWildcardOperatorMustNotEnableLengthDetection(): void
    {
        $response = $this->executeSearch('title:markeralpha?35567');
        self::assertStringNotContainsString(
            'markeralpha235567',
            $response,
            'SST 235567 / PDF §3.3: single-character wildcard (?) leaked the alpha document.',
        );
    }

    /** PDF §3.4 — range query must not enable binary-search value extraction. */
    #[Test]
    public function rangeQueryOperatorMustNotEnableBinarySearchExtraction(): void
    {
        $response = $this->executeSearch('title:[m TO n]');
        foreach (['markeralpha235567', 'markerbeta235567', 'markergamma235567'] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $response,
                'SST 235567 / PDF §3.4: range query (title:[m TO n]) leaked ' . $marker,
            );
        }
    }

    /** Combined field-selector + range against the hex-hash siteHash field must not match the test corpus. */
    #[Test]
    public function fieldSelectorOperatorMustNotTargetArbitraryIndexedField(): void
    {
        $response = $this->executeSearch('siteHash:[0 TO 9]');
        foreach (['markeralpha235567', 'markerbeta235567', 'markergamma235567'] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $response,
                'SST 235567: field-selector range query against siteHash leaked ' . $marker,
            );
        }
    }

    #[Test]
    public function existingFieldWildcardMustNotTriggerSolrCommunicationException(): void
    {
        GeneralUtility::makeInstance(TSFETestBootstrapper::class)->bootstrap(1);
        $configuration = new TypoScriptConfiguration([
            'plugin.' => ['tx_solr.' => ['search.' => [
                'query.' => ['queryFields' => 'content,title'],
                'results.' => [
                    'resultsHighlighting' => 1,
                    'resultsHighlighting.' => [
                        'fragmentSize' => 50,
                        'wrap' => '<mark>|</mark>',
                    ],
                ],
            ]]],
        ]);
        $query = (new QueryBuilder($configuration))->buildSearchQuery('siteHash:*');
        try {
            GeneralUtility::makeInstance(Search::class)->search($query);
        } catch (SolrCommunicationException $e) {
            self::fail('SST 235567: highlighter raised ' . $e::class . ' — ' . $e->getMessage());
        }
    }

    protected function executeSearch(string $rawQuery): string
    {
        return (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/'))
                ->withPageId(2022)
                ->withQueryParameter('tx_solr[q]', $rawQuery),
        )->getBody();
    }
}
