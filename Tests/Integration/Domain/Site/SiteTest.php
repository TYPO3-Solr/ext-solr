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
