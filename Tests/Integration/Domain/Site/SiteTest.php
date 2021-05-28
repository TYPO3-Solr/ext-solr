<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Site;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
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

    public function setUp() {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetDefaultLanguage()
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->importDataSetFromFixture('can_get_default_language.xml');
        $site = $siteRepository->getFirstAvailableSite();
        $this->assertEquals(0, $site->getDefaultLanguage(), 'Could not get default language from site');
    }

    /**
     * @test
     */
    public function canCreateInstanceWithRootSiteUidOK() {
        $this->importDataSetFromFixture('can_create_instance_with_root_site.xml');

            /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByRootPageId(1);
        $this->assertEquals(1, $this->site->getRootPageId());
    }

    /**
     * @test
     */
    public function canCreateInstanceWithRootSiteUidNOK() {
        $this->importDataSetFromFixture('can_create_instance_with_root_site.xml');
        $this->expectException(\InvalidArgumentException::class);
        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByRootPageId(2);
    }

    /**
     * @test
     */
    public function canCreateInstanceWithNonRootSiteUidOK() {
        $this->importDataSetFromFixture('can_create_instance_with_non_root_site.xml');
        $this->expectException(\InvalidArgumentException::class);

        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByRootPageId(1);
    }

    /**
     * @test
     */
    public function canCreateInstanceWithNonRootSiteUidNOK() {
        $this->importDataSetFromFixture('can_create_instance_with_non_root_site.xml');
        $this->expectException(\InvalidArgumentException::class);

        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByRootPageId(2);
    }


    /**
     * @test
     */
    public function canGetAvailableLanguageIds() {
        $this->importDataSetFromFixture('can_get_translations_for_root_site.xml');

        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $this->site = $siteRepository->getSiteByRootPageId(1);
        $languageIds = $this->site->getAvailableLanguageIds();

        $this->assertEquals([0, 1, 2], $languageIds);
    }
}
