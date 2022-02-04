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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Site class tests
 *
 * @author Timo Schmidt
 */
class SiteTest extends IntegrationTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @test
     */
    public function canGetDefaultLanguage()
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        /* @var Site $site */
        $site = $siteRepository->getFirstAvailableSite();
        self::assertEquals(0, $site->getDefaultLanguageId(), 'Could not get default language from site');
    }

    /**
     * @test
     */
    public function canCreateInstanceWithRootSiteUidOK()
    {
        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByRootPageId(1);
        self::assertEquals(1, $site->getRootPageId());
    }

    /**
     * @test
     */
    public function canCreateInstanceWithRootSiteUidNOK()
    {
        $this->expectException(InvalidArgumentException::class);
        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByRootPageId(2);
    }

    /**
     * @test
     */
    public function canCreateInstanceWithNonRootSiteUidOK()
    {
        $this->importDataSetFromFixture('can_create_instance_with_non_root_site.xml');
        $this->expectException(InvalidArgumentException::class);

        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $siteRepository->getSiteByRootPageId(151);
    }

    /**
     * @test
     */
    public function canCreateInstanceWithNonRootSiteUidNOK()
    {
        $this->importDataSetFromFixture('can_create_instance_with_non_root_site.xml');
        $this->expectException(InvalidArgumentException::class);

        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $siteRepository->getSiteByRootPageId(152);
    }

    /**
     * @test
     */
    public function canGetAvailableLanguageIds()
    {
        $this->importDataSetFromFixture('can_get_translations_for_root_site.xml');

        /* @var SiteRepository $siteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByRootPageId(1);
        $languageIds = $site->getAvailableLanguageIds();

        self::assertEquals([0, 1, 2], $languageIds);
    }
}
