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

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\UserGroupDetector;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Regression guard for CVE-2026-56092: an EXT:solr page-indexer
 * sub-request must not poison the TYPO3 rootline cache with the
 * `fe_group=''`/`extendToSubpages='0'` values that
 * UserGroupDetector::getPageOverlay_preProcess() (Classes/IndexQueue/
 * FrontendHelper/UserGroupDetector.php:130-142) forces onto the `pages`
 * record via BeforeRecordLanguageOverlayEvent — a value that
 * RootlineUtility::enrichPageRecordArray() then persists as-is.
 *
 * Overrides the `rootline` cache backend to Typo3DatabaseBackend because
 * FunctionalTestCase defaults it to NullBackend, which would mask the
 * pollution.
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
        // Start each test from an empty rootline cache.
        GeneralUtility::makeInstance(CacheManager::class)->getCache('rootline')->flush();
    }

    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * End-to-end reproduction: indexer sub-request followed by an anonymous
     * request on the same restricted page. Cannot be modeled in-process —
     * the indexer and anonymous sub-requests share one PHP process here, so
     * TSFE rebuilds the rootline from the DB instead of reading the
     * persisted (poisoned) cache entry that separate workers would share in
     * production. The pollution itself is proven directly by
     * rootlineCacheMustNotContainListenerMutatedRecordAfterPageIndexing().
     */
    #[Test]
    public function anonymousVisitorMustNotReachAccessRestrictedPageAfterPageIndexing(): void
    {
        self::markTestIncomplete(
            'CVE-2026-56092: in-process test runner cannot model the cross-worker cache-read scenario; '
            . 'see rootlineCacheMustNotContainListenerMutatedRecordAfterPageIndexing() for the direct proof.',
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
        $this->activateUserGroupDetector();
        $this->indexPages([5], 1);
        $this->assertSolrContainsDocumentCount(1);

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
     */
    #[Test]
    public function pageRestrictedOnlyViaInheritedExtendToSubpagesIsStillIndexed(): void
    {
        $this->activateUserGroupDetector();
        $this->indexPages([5], 1);

        $solrContent = (string)file_get_contents(
            $this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*',
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

        $this->activateUserGroupDetector();
        $this->indexPages([5], 1);

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
     * Activates UserGroupDetector explicitly: executePageIndexer() only runs the
     * indexPage phase, not the findUserGroups phase that activates it in production.
     * The service is `shared: true` (Configuration/Services.yaml:335-339), so this
     * affects any frontend sub-request in the same PHP process afterward.
     */
    protected function activateUserGroupDetector(): void
    {
        GeneralUtility::makeInstance(UserGroupDetector::class)->activate();
    }
}
