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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Site;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the SiteRepository class works as expected.
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SiteRepositoryTest extends IntegrationTestBase
{
    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    #[Test]
    public function canGetAllSites(): void
    {
        $sites = $this->siteRepository->getAvailableSites();
        self::assertSame(2, count($sites), 'Expected to retrieve two sites from default tests setup. Note: The third site is not enabled for EXT:solr.');
    }

    #[Test]
    public function canGetAllPagesFromSite(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_all_pages_from_sites.csv');
        $site = $this->siteRepository->getFirstAvailableSite();
        self::assertSame([1, 2, 21, 22, 3, 30], $site->getPages(), 'Can not get all pages from site');
    }

    #[Test]
    public function canGetSiteByRootPageIdExistingRoot(): void
    {
        $site = $this->siteRepository->getSiteByRootPageId(1);
        self::assertContainsOnlyInstancesOf(Site::class, [$site], 'Could not retrieve site from root page');
    }

    #[Test]
    public function canGetSiteByRootPageIdNonExistingRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $siteRepository->getSiteByRootPageId(42);
    }

    #[Test]
    public function canGetSiteByPageIdExistingPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_site_by_page_id.csv');
        $site = $this->siteRepository->getSiteByPageId(2);
        self::assertContainsOnlyInstancesOf(Site::class, [$site], 'Could not retrieve site from page');
    }

    #[Test]
    public function canGetSiteByPageIdNonExistingPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_site_by_page_id.csv');
        $this->siteRepository->getSiteByPageId(42);
    }

    #[Test]
    public function canGetSiteWithDomainFromSiteConfiguration(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_get_site_by_page_id.csv');
        $site = $this->siteRepository->getSiteByPageId(1);
        $domain = $site->getDomain();
        self::assertSame('testone.site', $domain, 'Can not get the domain from Site-Config.');
    }
}
