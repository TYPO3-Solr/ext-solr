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

use ApacheSolrForTypo3\Solr\Domain\Site\Exception\InvalidSiteConfigurationCombinationException;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * @test
     */
    public function canGetAllSites()
    {
        $this->importDataSetFromFixture('can_get_all_sites.xml');
        $sites = $this->siteRepository->getAvailableSites();
        $this->assertSame(1, count($sites), 'Expected to retrieve one site from fixture');
    }

    /**
     * @test
     */
    public function getAvailableSitesDoNotReturnSitesNotEnabled(): void
    {
        $this->importDataSetFromFixture('get_available_sites_do_not_return_sites_not_enabled.xml');
        $sites = $this->siteRepository->getAvailableSites();
        $this->assertSame(2, count($sites), 'Expected to retrieve two sites');
    }

    /**
     * @test
     */
    public function canGetAllPagesFromSite()
    {
        $this->importDataSetFromFixture('can_get_all_pages_from_sites.xml');
        $site = $this->siteRepository->getFirstAvailableSite();
        $this->assertSame([1,2,21,22,3,30], $site->getPages(), 'Can not get all pages from site');
    }

    /**
     * @test
     */
    public function canGetSiteByRootPageIdExistingRoot()
    {
        $this->importDataSetFromFixture('can_get_site_by_root_page_id.xml');
        $site = $this->siteRepository->getSiteByRootPageId(1);
        $this->assertContainsOnlyInstancesOf(Site::class, [$site], 'Could not retrieve site from root page');
    }

    /**
     * @test
     */
    public function canGetSiteByRootPageIdNonExistingRoot()
    {
        $this->expectException(\InvalidArgumentException::class);
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->importDataSetFromFixture('can_get_site_by_root_page_id.xml');
        $siteRepository->getSiteByRootPageId(42);
    }

    /**
     * @test
     */
    public function canGetSiteByPageIdExistingPage()
    {
        $this->importDataSetFromFixture('can_get_site_by_page_id.xml');
        $site = $this->siteRepository->getSiteByPageId(2);
        $this->assertContainsOnlyInstancesOf(Site::class, [$site], 'Could not retrieve site from page');
    }

    /**
     * @test
     */
    public function canGetSiteByPageIdNonExistingPage()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->importDataSetFromFixture('can_get_site_by_page_id.xml');
        $this->siteRepository->getSiteByPageId(42);
    }

    /**
     * @test
     */
    public function canGetSiteWithDomainFromSiteConfiguration()
    {
        $this->importDataSetFromFixture('can_get_site_by_page_id.xml');
        $site = $this->siteRepository->getSiteByPageId(1);
        $domain = $site->getDomain();
        $this->assertSame('testone.site', $domain, 'Can not configured domain with sys_domain record');
    }
}
