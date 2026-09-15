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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Integration\Controller\AbstractFrontendControllerTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Regression guard for SST #2026052010000029 (CVE-2026-56094): request-provided
 * `tx_solr[additionalFilters][siteHash]` must not preempt the system siteHash filter.
 * In a shared-Solr-core multi-site install that bypass lets anonymous visitors of one
 * site read public documents of another site sharing the same core.
 *
 * 11.2 specifics: rather than bootstrapping two full TYPO3 sites (this branch's TSFE
 * test bootstrap does not reliably resolve a second, independent site's TypoScript
 * template within the same test run), this exercises the exact vulnerable interaction
 * directly at the query level, matching production's actual call order in
 * `SearchResultSetService::doASearch()`:
 *   1. `QueryBuilder::buildSearchQuery()` applies the request's `additionalFilters`
 *      (this is what `removeReservedFiltersFromRequest()` now sanitizes).
 *   2. `AccessComponent::initializeSearchComponent()` runs afterwards, calling
 *      `useSiteHashFromTypoScript()` then `useUserAccessGroups()` on the same query.
 * A second, "other site" document is injected directly into the shared core with a
 * different (but realistically computed, via `SiteHashService::getSiteHashForDomain()`)
 * siteHash, standing in for a real second site sharing the same Solr core.
 *
 * @group frontend
 * @group security
 */
class AdditionalFiltersSiteHashBypassTest extends AbstractFrontendControllerTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeBEUser(1);
        $this->importDataSetFromFixture('additional_filters_sitehash_bypass.xml');
    }

    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * Primary guard: an injected additionalFilters[siteHash] must not preempt the
     * system siteHash filter `AccessComponent` applies afterwards, which would let a
     * document belonging to another site (sharing the same core) leak into the result.
     *
     * @test
     */
    public function additionalFiltersSiteHashMustNotPreemptSystemSiteHashFilter()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([2]);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(1);

        $this->addForeignSiteDocument();
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(2);

        $queryBuilder = new QueryBuilder(Util::getSolrConfiguration());

        // Same order as SearchResultSetService::doASearch(): buildSearchQuery() first
        // (applying request additionalFilters), AccessComponent afterwards.
        $query = $queryBuilder->buildSearchQuery('sharedcrosssitetoken235572', 10, ['siteHash' => '*:*']);
        $queryBuilder->startFrom($query)
            ->useSiteHashFromTypoScript(1)
            ->useUserAccessGroups([0]);

        $response = GeneralUtility::makeInstance(Search::class)->search($query)->getRawResponse();
        $response = json_decode($response, true);

        self::assertSame(
            1,
            $response['response']['numFound'],
            'CVE-2026-56094: additionalFilters[siteHash] preempted the system siteHash filter, returning documents from another site sharing the same core.'
        );
        self::assertSame(
            'SiteOnePublicDoc',
            $response['response']['docs'][0]['title'],
            'CVE-2026-56094: expected only the own-site document; the foreign-site document leaked instead.'
        );
    }

    /**
     * Injects a document into the shared core with a siteHash belonging to a different,
     * hypothetical site — standing in for a real second TYPO3 site sharing the same core.
     */
    protected function addForeignSiteDocument()
    {
        $foreignSiteHash = GeneralUtility::makeInstance(SiteHashService::class)->getSiteHashForDomain('testtwo.site');

        $document = GeneralUtility::makeInstance(Document::class);
        $document->setField('id', 'foreignSite/pages/9999/0/0/0');
        $document->setField('appKey', 'EXT:solr');
        $document->setField('type', 'pages');
        $document->setField('uid', 9999);
        $document->setField('pid', 9999);
        $document->setField('siteHash', $foreignSiteHash);
        $document->setField('title', 'SiteTwoPublicDoc');
        $document->setField('content', 'sharedcrosssitetoken235572 SiteTwoPublicBody must NOT be returned when searching from site one');
        $document->setField('url', 'http://testtwo.site/');

        $connection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(2, 0);
        $writeService = $connection->getWriteService();
        $writeService->addDocuments([$document]);
        $writeService->commit();
    }
}
