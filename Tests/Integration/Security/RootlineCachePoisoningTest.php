<?php

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
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Regression guard for SST #2026052210000016 (CVE-2026-56092): indexer sub-requests must not
 * leave forged `fe_group=''`/`extendToSubpages='0'` values in the persistent rootline cache,
 * where a later anonymous request would read them and grant access.
 *
 * `UserGroupDetector::getPageOverlay_preProcess()` (registered as the `getPageOverlay` hook while
 * the indexer's `findUserGroups` phase is active) forged those fields on every `pages` record it
 * saw. `PageRepository::getPagesOverlay()` calls that hook by reference, and
 * `RootlineUtility::getRecordArray()` persists its result — for a translated page view only,
 * `languageUid > 0` — into the `rootline` cache, which TYPO3 10.4 defaults to a persistent
 * database backend in production (and this branch's test setup does not override that default).
 *
 * @group security
 */
class RootlineCachePoisoningTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->importDataSetFromFixture('rootline_cache_poisoning.xml');
    }

    /**
     * PRIMARY regression guard: page 5 is restricted only through its parent's
     * `extendToSubpages`. Building its rootline for a translated view (language 1) while
     * `UserGroupDetector` is activated must not leave the forged values in `cache_rootline`.
     *
     * @test
     */
    public function rootlineCacheMustNotContainListenerMutatedRecordAfterPageIndexing()
    {
        GeneralUtility::makeInstance(UserGroupDetector::class)->activate();

        $this->buildAndCacheRootline(5, 1);

        $aggregated = $this->getAggregatedRootlineCacheContent();

        self::assertStringNotContainsString(
            's:8:"fe_group";s:0:"";',
            $aggregated,
            'CVE-2026-56092: rootline cache persisted the listener-forced empty fe_group.'
        );
        self::assertStringNotContainsString(
            's:16:"extendToSubpages";s:1:"0";',
            $aggregated,
            'CVE-2026-56092: rootline cache persisted the listener-forced extendToSubpages="0".'
        );
    }

    /**
     * COMPARISON BASELINE: without `UserGroupDetector` activated, the cached rootline reflects
     * page 3's real restriction. Confirms the primary test's failure (pre-fix) comes from the
     * listener, not from the fixture or a general rootline-caching bug.
     *
     * @test
     */
    public function rootlineCacheReflectsRealAccessRestrictionsWhenUserGroupDetectorIsNotActivated()
    {
        $this->buildAndCacheRootline(5, 1);

        $aggregated = $this->getAggregatedRootlineCacheContent();

        self::assertStringContainsString(
            's:8:"fe_group";s:1:"1";',
            $aggregated,
            'Baseline invalid: the real fe_group=1 restriction on page 3 was not found in the cached rootline.'
        );
    }

    /**
     * Triggers TSFE's own rootline resolution (`determineId()` -> `RootlineUtility::get()`),
     * which is what persists the (possibly forged) page records into `cache_rootline`.
     */
    protected function buildAndCacheRootline(int $pageId, int $languageId): void
    {
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
        unset($GLOBALS['TSFE']);
        $this->getConfiguredTSFE($pageId, '', $languageId);
    }

    protected function getAggregatedRootlineCacheContent(): string
    {
        $rows = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('cache_rootline')
            ->select(['identifier', 'content'], 'cache_rootline')
            ->fetchAll();

        self::assertNotEmpty(
            $rows,
            'cache_rootline is empty after building the rootline; precondition for the test is broken.'
        );

        $aggregated = '';
        foreach ($rows as $row) {
            $aggregated .= (string)$row['content'];
        }

        return $aggregated;
    }
}
