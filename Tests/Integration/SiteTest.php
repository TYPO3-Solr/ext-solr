<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Site class tests
 *
 * @author Timo Schmidt
 */
class SiteTest extends IntegrationTest
{

    /**
     * @var Site
     */
    private $site;

    /**
     * @test
     */
    public function canGetAllSites()
    {
        $this->importDataSetFromFixture('can_get_all_sites.xml');
        $sites = Site::getAvailableSites();
        $this->assertSame(1, count($sites), 'Expected to retrieve one site from fixture');
    }

    /**
     * @test
     */
    public function canGetDefaultLanguage()
    {
        $this->importDataSetFromFixture('can_get_default_language.xml');
        $site = Site::getFirstAvailableSite();
        $this->assertEquals(888, $site->getDefaultLanguage(), 'Could not get default language from site');
    }

    /**
     * @test
     */
    public function canGetAllPagesFromSite()
    {
        $this->importDataSetFromFixture('can_get_all_pages_from_sites.xml');
        $site = Site::getFirstAvailableSite();
        $this->assertEquals([1,2,21,22,3,30], $site->getPages(), 'Can not get all pages from site');
    }

    /**
     * @test
     */
    public function canCreateInstanceWithRootSiteUidOK() {
        $this->importDataSetFromFixture('can_create_instance_with_root_site.xml');
        $this->site = GeneralUtility::makeInstance(Site::class, 1);
        $this->assertEquals(1, $this->site->getRootPageId());
    }

    /**
     * @test
     */
    public function canCreateInstanceWithRootSiteUidNOK() {
        $this->importDataSetFromFixture('can_create_instance_with_root_site.xml');
        $this->setExpectedException(\InvalidArgumentException::class);
        $this->site = GeneralUtility::makeInstance(Site::class, 2);
    }

    /**
     * @test
     */
    public function canCreateInstanceWithNonRootSiteUidOK() {
        $this->importDataSetFromFixture('can_create_instance_with_non_root_site.xml');
        $this->setExpectedException(\InvalidArgumentException::class);
        $this->site = GeneralUtility::makeInstance(Site::class, 1);
    }

    /**
     * @test
     */
    public function canCreateInstanceWithNonRootSiteUidNOK() {
        $this->importDataSetFromFixture('can_create_instance_with_non_root_site.xml');
        $this->setExpectedException(\InvalidArgumentException::class);
        $this->site = GeneralUtility::makeInstance(Site::class, 2);
    }
}
