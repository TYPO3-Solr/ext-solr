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

use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Regression guard for TYPO3 Security Team Ticket #2026052010000029 (CVE-2026-56094).
 *
 * Asserts the secure behavior: a request-supplied `tx_solr[additionalFilters][siteHash]` must not preempt the system siteHash filter.
 * In a shared-core multi-site install that bypass would let anonymous visitors of one site read public documents of another site in the same core.
 */
#[Group('frontend')]
#[Group('security')]
class AdditionalFiltersSiteHashBypassTest extends IntegrationTestBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        // Shared core: both testone.site and testtwo.site map to core_en (EN).

        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/default_search_results_plugin.csv');

        // Site 1: enable indexing and render the pi_results plugin on page 2022.
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
        // Site 2 (template uid 111) needs indexing enabled too.
        $this->addTypoScriptToTemplateRecord(
            111,
            /* @lang TYPO3_TypoScript */
            '
            config.index_enable = 1
            ',
        );
    }

    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * Primary guard: an injected additionalFilters[siteHash] on the results page must not leak Site 2 documents into a Site 1 search response.
     */
    #[Test]
    public function crossSiteDocumentMustNotLeakViaAdditionalFiltersSiteHash(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CVE-2026-56094_additionalfilters_sitehash.csv');

        // Page 2 → Site 1, Page 112 → Site 2; both indexed into the shared core_en.
        $this->indexPages([2, 112]);
        $this->assertSolrContainsDocumentCount(2);

        $response = (string)$this->executeFrontendSubRequest(
            $this->getSiteOneRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[additionalFilters][siteHash]', '*:*'),
        )->getBody();

        self::assertStringContainsString(
            'Found 1 result',
            $response,
            'CVE-2026-56094: expected exactly the single Site 1 document — guards against a vacuous pass.',
        );
        self::assertStringContainsString(
            'SiteOnePublicDoc',
            $response,
            'CVE-2026-56094: own Site 1 document must be present in the Site 1 search response.',
        );
        self::assertStringNotContainsString(
            'SiteTwoPublicDoc',
            $response,
            'CVE-2026-56094: cross-site document title from Site 2 leaked into Site 1 search response.',
        );
        self::assertStringNotContainsString(
            'SiteTwoPublicBody',
            $response,
            'CVE-2026-56094: cross-site document body marker from Site 2 leaked into Site 1 search response.',
        );
    }

    /**
     * Suggest top-results variant: addTopResultsToSuggestions() copies the request additionalFilters into a regular search,
     * so the injected siteHash must not leak Site 2 documents into the suggest JSON either.
     */
    #[Test]
    public function suggestTopResultsMustNotLeakCrossSiteDocument(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CVE-2026-56094_additionalfilters_sitehash.csv');
        $this->indexPages([2, 112]);
        $this->assertSolrContainsDocumentCount(2);

        // Enable suggest top-results endpoint on both sites' templates.
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            @import \'EXT:solr/Configuration/TypoScript/Examples/Suggest/setup.typoscript\'
            ',
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            /* @lang TYPO3_TypoScript */
            '
            @import \'EXT:solr/Configuration/TypoScript/Examples/Suggest/setup.typoscript\'
            ',
        );

        // "sharedCrossSiteToken" is in both sites' bodytexts, so without the siteHash filter the top-results search would match both sites.
        $response = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/en/'))
                ->withPageId(1)
                ->withQueryParameter('type', '7384')
                ->withQueryParameter('tx_solr[queryString]', 'sharedCrossSiteToken')
                ->withQueryParameter('tx_solr[additionalFilters][siteHash]', '*:*'),
        )->getBody();

        self::assertStringContainsString(
            'SiteOnePublicDoc',
            $response,
            'CVE-2026-56094: own Site 1 document must be present in the suggest top-results.',
        );
        self::assertSame(
            1,
            substr_count($response, '"link":'),
            'CVE-2026-56094: suggest top-results must contain exactly one document (the Site 1 one).',
        );
        self::assertStringNotContainsString(
            'SiteTwoPublicDoc',
            $response,
            'CVE-2026-56094: suggest top-results leaked the title of a Site 2 document into a Site 1 suggest response.',
        );
        self::assertStringNotContainsString(
            'SiteTwoPublicBody',
            $response,
            'CVE-2026-56094: suggest top-results leaked the body marker of a Site 2 document into a Site 1 suggest response.',
        );
    }

    /**
     * Baseline: additionalFilters[access] is already neutralized by useUserAccessGroups() (remove-then-set), so the restricted document stays hidden.
     * Passes before and after the fix.
     */
    #[Test]
    public function accessRestrictedDocumentMustNotLeakViaAdditionalFiltersAccess(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CVE-2026-56094_additionalfilters_sitehash.csv');

        // Site 1 public page 2 + access-restricted page 13 (fe_group=1).
        $this->indexPages([2, 13], 1);
        $this->assertSolrContainsDocumentCount(2);

        $response = (string)$this->executeFrontendSubRequest(
            $this->getSiteOneRequest()
                ->withQueryParameter('tx_solr[q]', '*')
                ->withQueryParameter('tx_solr[additionalFilters][access]', '*:*'),
        )->getBody();

        self::assertStringContainsString(
            'Found 1 result',
            $response,
            'Baseline: expected exactly the single public Site 1 document — guards against a vacuous pass.',
        );
        self::assertStringContainsString(
            'SiteOnePublicDoc',
            $response,
            'Baseline: public Site 1 document must be present in the search response.',
        );
        self::assertStringNotContainsString(
            'SiteOneRestrictedDoc',
            $response,
            'Baseline broken: additionalFilters[access] succeeded in bypassing the access filter — please re-verify QueryBuilder::useUserAccessGroups().',
        );
        self::assertStringNotContainsString(
            'SiteOneRestrictedBody',
            $response,
            'Baseline broken: additionalFilters[access] disclosed restricted content body.',
        );
    }

    protected function getSiteOneRequest(int $pageId = 2022): InternalRequest
    {
        return (new InternalRequest('http://testone.site/'))->withPageId($pageId);
    }
}
