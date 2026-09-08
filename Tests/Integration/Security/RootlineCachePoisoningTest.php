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

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\Tests\Integration\Fixtures\IndexingServiceForTesting;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Regression guard for CVE-2026-56092: an indexer sub-request must not persist
 * UserGroupDetector::clearPageOverlayAccessRestrictions()'s forged fe_group/extendToSubpages
 * into the shared rootline cache (via BeforeRecordLanguageOverlayEvent + RootlineUtility).
 *
 * Indexes through IndexService/IndexingServiceForTesting, the same entry point
 * AccessProtectedContentTest uses, so both the real findUserGroups and indexPage
 * sub-requests run. The old indexPages()/executePageIndexer() shortcut only fires
 * indexPage and needed IndexingResultCollector flipped manually - production never
 * does that; UserGroupDetectionMiddleware only activates it during findUserGroups.
 *
 * Overrides the rootline cache backend to Typo3DatabaseBackend; FunctionalTestCase
 * defaults to NullBackend, which would mask the pollution.
 */
#[Group('security')]
class RootlineCachePoisoningTest extends IntegrationTestBase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'exceptionalErrors' => E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
            // Production default; FunctionalTestCase overrides this to NullBackend otherwise.
            'caching' => [
                'cacheConfigurations' => [
                    'rootline' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/cve_2026_56092_rootline_poisoning.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            config.index_enable = 1
            ',
        );
        // Start each test from an empty rootline cache and a reset detection singleton.
        GeneralUtility::makeInstance(CacheManager::class)->getCache('rootline')->flush();
        GeneralUtility::makeInstance(IndexingResultCollector::class)->reset();

        // Use the real IndexingService pipeline, same as AccessProtectedContentTest.
        /** @var \Symfony\Component\DependencyInjection\Container $container */
        $container = GeneralUtility::getContainer();
        $container->set(
            IndexingService::class,
            IndexingServiceForTesting::fromProductionService($container->get(IndexingService::class)),
        );
    }

    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * End-to-end reproduction: index, then request the page as an anonymous visitor.
     * Runs for real now (not markTestIncomplete()) since the real pipeline is used.
     */
    #[Test]
    public function anonymousVisitorMustNotReachAccessRestrictedPageAfterPageIndexing(): void
    {
        $this->indexPageThroughRealPipeline(5);

        $response = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/en/parent-restricted-area/child'))->withPageId(5),
        )->getBody();

        self::assertStringNotContainsString(
            'ROOTLINE_POISON_CHILD_MARKER_STU567',
            $response,
            'Anonymous visitor reached access-restricted page content after indexing via the real production pipeline.',
        );
    }

    /**
     * Primary regression guard: after indexing, the rootline cache entry for
     * the restricted page must still carry the DB's `fe_group=1` /
     * `extendToSubpages=1` — not the listener's forced `''`/`'0'`.
     */
    #[Test]
    public function rootlineCacheMustNotContainListenerMutatedRecordAfterPageIndexing(): void
    {
        $this->indexPageThroughRealPipeline(5);

        // Read every cache entry directly from the rootline cache table.
        $rows = $this->getConnectionPool()
            ->getConnectionForTable('cache_rootline')
            ->select(['identifier', 'content'], 'cache_rootline')
            ->fetchAllAssociative();

        self::assertNotEmpty(
            $rows,
            'cache_rootline is empty after indexing — backend override likely did not take effect.',
        );

        // Cache content is serialized PHP; concatenate for substring assertions.
        $aggregated = '';
        foreach ($rows as $row) {
            $aggregated .= (string)$row['content'];
        }

        self::assertStringNotContainsString(
            's:8:"fe_group";s:0:"";',
            $aggregated,
            'Rootline cache persisted the listener-forced empty fe_group.',
        );
        self::assertStringNotContainsString(
            's:16:"extendToSubpages";s:1:"0";',
            $aggregated,
            'Rootline cache persisted the listener-forced extendToSubpages="0".',
        );
    }

    /**
     * Guards the officially supported "index restricted pages" feature: a page
     * whose restriction is only inherited via extendToSubpages must still be
     * indexed with its real content once the record-forging listener is gone.
     * Run this BEFORE removing the listener too, to confirm it already passes
     * pre-fix (proving the assertion isn't accidentally tautological).
     */
    #[Test]
    public function pageRestrictedOnlyViaInheritedExtendToSubpagesIsStillIndexed(): void
    {
        $this->indexPageThroughRealPipeline(5);

        $solrContent = (string)file_get_contents(
            $this->getSolrCoreUrl('core_en') . '/select?q=*:*',
        );
        self::assertStringContainsString(
            'ROOTLINE_POISON_CHILD_MARKER_STU567',
            $solrContent,
            'Indexer must still retrieve the content of a page restricted only via inherited extendToSubpages.',
        );
    }

    /**
     * Counter-check: a cache already warmed by a legitimate authenticated
     * request must stay clean after indexing runs, and access stays enforced.
     */
    #[Test]
    public function cacheWarmupByAuthenticatedRequestBeforeIndexingPreservesAccessRestriction(): void
    {
        // Pre-warm via an authenticated request (user 1 is member of fe_group 1).
        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/en/parent-restricted-area/child'))->withPageId(5),
            $context,
        );

        $this->indexPageThroughRealPipeline(5);

        $response = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/en/parent-restricted-area/child'))->withPageId(5),
        )->getBody();

        self::assertStringNotContainsString(
            'ROOTLINE_POISON_CHILD_MARKER_STU567',
            $response,
            'Anonymous visitor reached the restricted page despite a pre-warmed, clean cache.',
        );
    }

    /**
     * Indexes via the real pipeline: IndexService -> IndexingService -> a
     * findUserGroups sub-request, then indexPage sub-request(s) per group found.
     */
    protected function indexPageThroughRealPipeline(int $pageId): void
    {
        $coreSite = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        $this->addPageToIndexQueue($pageId, $coreSite);

        $solrSite = GeneralUtility::makeInstance(SiteRepository::class)->getSiteByPageId($pageId);
        self::assertNotNull($solrSite, 'No Solr site configuration found for page ' . $pageId);

        $indexService = GeneralUtility::makeInstance(IndexService::class, $solrSite);
        $indexService->indexItems(10);

        $this->waitToBeVisibleInSolr();
    }
}
