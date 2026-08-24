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

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
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
 * Regression guard for CVE-2026-56092: indexer sub-requests must not leave forged
 * `fe_group=''`/`extendToSubpages='0'` values in the rootline cache, where later anonymous
 * requests would read them and grant access.
 *
 * TYPO3 12.4 carries that path — `RootlineUtility` persists what
 * `PageRepository::getLanguageOverlay()` returns, forgeable via
 * `BeforeRecordLanguageOverlayEvent`. Binding the removed listener there makes three of the
 * four cases fail. `RecordAccessGrantedEvent`, which this branch used, never reaches it, so
 * these cases guard against reintroduction rather than reproducing a defect that existed here.
 */
#[Group('security')]
class RootlineCachePoisoningTest extends IntegrationTestBase
{
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'exceptionalErrors' => E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
            // FunctionalTestCase defaults this to NullBackend, which would mask the pollution.
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
        GeneralUtility::makeInstance(CacheManager::class)->getCache('rootline')->flush();
    }

    protected function tearDown(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * Page 5 is restricted only through its parent's `extendToSubpages`.
     */
    #[Test]
    public function anonymousVisitorMustNotReachAccessRestrictedPageAfterPageIndexing(): void
    {
        $this->indexPagesAsProductionIndexerDoes([5], 1);

        $response = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/en/parent-restricted-area/child'))->withPageId(5),
        )->getBody();

        self::assertStringNotContainsString(
            'ROOTLINE_POISON_CHILD_MARKER_STU567',
            $response,
            'Anonymous visitor reached the restricted page after indexing.',
        );
    }

    /**
     * Primary guard: cached rootline records must still carry the values from the database.
     */
    #[Test]
    public function rootlineCacheMustNotContainListenerMutatedRecordAfterPageIndexing(): void
    {
        $this->indexPagesAsProductionIndexerDoes([5], 1);
        $this->assertSolrContainsDocumentCount(1);

        $rows = $this->getConnectionPool()
            ->getConnectionForTable('cache_rootline')
            ->select(['identifier', 'content'], 'cache_rootline')
            ->fetchAllAssociative();

        self::assertNotEmpty(
            $rows,
            'cache_rootline is empty after indexing — backend override likely did not take effect.',
        );

        // Serialized PHP; concatenated so one assertion covers every entry.
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
     * Indexing restricted pages is a supported feature and must survive the removal.
     */
    #[Test]
    public function pageRestrictedOnlyViaInheritedExtendToSubpagesIsStillIndexed(): void
    {
        $this->indexPagesAsProductionIndexerDoes([5], 1);

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
     * A cache warmed by a legitimate request must stay clean once indexing runs.
     */
    #[Test]
    public function cacheWarmupByAuthenticatedRequestBeforeIndexingPreservesAccessRestriction(): void
    {
        // User 1 is a member of fe_group 1.
        $context = (new InternalRequestContext())->withFrontendUserId(1);
        $this->executeFrontendSubRequest(
            (new InternalRequest('http://testone.site/en/parent-restricted-area/child'))->withPageId(5),
            $context,
        );

        $this->indexPagesAsProductionIndexerDoes([5], 1);

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
     * Mirrors PageIndexer::index(): two separate sub-requests, findUserGroups first. Only that
     * phase activates UserGroupDetector, and activating it from the test process instead would
     * hit another container instance than the listener resolves, leaving the assertions vacuous.
     *
     * @param int[] $pageIds
     */
    protected function indexPagesAsProductionIndexerDoes(array $pageIds, ?int $frontendUserId = null): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        foreach ($pageIds as $pageId) {
            $site = $siteFinder->getSiteByPageId($pageId);
            $queueItem = $this->addPageToIndexQueue($pageId, $site);
            $frontendUrl = (string)$site->getRouter()->generateUri($pageId);
            $this->executeIndexerSubRequest($frontendUrl, $queueItem, $frontendUserId, ['findUserGroups'], true);
            $this->executeIndexerSubRequest($frontendUrl, $queueItem, $frontendUserId, ['indexPage']);
        }
        $this->waitToBeVisibleInSolr();
    }

    /**
     * Request shape of IntegrationTestBase::executePageIndexer(), plus control over the rootline
     * cache the rendering sub-request sees.
     *
     * Flush on the findUserGroups phase only: computing the access rootline warms the cache
     * here, so without it the sub-request reads a clean entry and the poisoning stays hidden.
     * Flushing on indexPage as well would let that phase rebuild cleanly and overwrite the
     * poisoned entry.
     *
     * @param string[] $actions
     */
    private function executeIndexerSubRequest(
        string $url,
        Item $item,
        ?int $frontendUserId,
        array $actions,
        bool $flushRootlineCacheBeforeRendering = false,
    ): void {
        $request = new InternalRequest($url);

        $indexerRequest = GeneralUtility::makeInstance(PageIndexerRequest::class);
        $indexerRequest->setIndexQueueItem($item);
        $indexerRequest->setParameter(
            'accessRootline',
            (string)Rootline::getAccessRootlineByPageId($item->getRecordUid()),
        );
        $indexerRequest->setParameter('item', $item->getIndexQueueUid());
        foreach ($actions as $action) {
            $indexerRequest->addAction($action);
        }
        foreach ($indexerRequest->getHeaders() as $header) {
            [$headerName, $headerValue] = GeneralUtility::trimExplode(':', $header, true, 2);
            $request = $request->withAddedHeader($headerName, $headerValue);
        }

        if ($flushRootlineCacheBeforeRendering) {
            GeneralUtility::makeInstance(CacheManager::class)->getCache('rootline')->flush();
        }

        $requestContext = $frontendUserId !== null
            ? (new InternalRequestContext())->withFrontendUserId($frontendUserId)
            : null;
        $this->executeFrontendSubRequest($request, $requestContext)->getBody()->rewind();
    }
}
