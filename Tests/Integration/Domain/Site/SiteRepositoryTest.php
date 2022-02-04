<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Site;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 - Thomas Hohn <tho@systime.dk>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Doctrine\DBAL\DBALException;
use Exception;
use InvalidArgumentException;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;

/**
 * Testcase to check if the SiteRepository class works as expected.
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SiteRepositoryTest extends IntegrationTest
{
    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @throws NoSuchCacheException
     * @throws TestingFrameworkCoreException
     * @throws DBALException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * @test
     * @throws Exception
     */
    public function canGetAllSites()
    {
        $sites = $this->siteRepository->getAvailableSites();
        self::assertSame(2, count($sites), 'Expected to retrieve two sites from default tests setup. Note: The third site is not enabled for EXT:solr.');
    }

    /**
     * @test
     * @throws TestingFrameworkCoreException
     * @throws Exception
     */
    public function canGetAllPagesFromSite()
    {
        $this->importDataSetFromFixture('can_get_all_pages_from_sites.xml');
        $site = $this->siteRepository->getFirstAvailableSite();
        self::assertSame([1, 2, 21, 22, 3, 30], $site->getPages(), 'Can not get all pages from site');
    }

    /**
     * @test
     */
    public function canGetSiteByRootPageIdExistingRoot()
    {
        $site = $this->siteRepository->getSiteByRootPageId(1);
        self::assertContainsOnlyInstancesOf(Site::class, [$site], 'Could not retrieve site from root page');
    }

    /**
     * @test
     */
    public function canGetSiteByRootPageIdNonExistingRoot()
    {
        $this->expectException(InvalidArgumentException::class);
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $siteRepository->getSiteByRootPageId(42);
    }

    /**
     * @test
     * @throws Exception
     */
    public function canGetSiteByPageIdExistingPage()
    {
        $this->importDataSetFromFixture('can_get_site_by_page_id.xml');
        $site = $this->siteRepository->getSiteByPageId(2);
        self::assertContainsOnlyInstancesOf(Site::class, [$site], 'Could not retrieve site from page');
    }

    /**
     * @test
     * @throws TestingFrameworkCoreException
     */
    public function canGetSiteByPageIdNonExistingPage()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->importDataSetFromFixture('can_get_site_by_page_id.xml');
        $this->siteRepository->getSiteByPageId(42);
    }

    /**
     * @test
     * @throws TestingFrameworkCoreException
     */
    public function canGetSiteWithDomainFromSiteConfiguration()
    {
        $this->importDataSetFromFixture('can_get_site_by_page_id.xml');
        $site = $this->siteRepository->getSiteByPageId(1);
        $domain = $site->getDomain();
        self::assertSame('testone.site', $domain, 'Can not configured domain with sys_domain record');
    }
}
