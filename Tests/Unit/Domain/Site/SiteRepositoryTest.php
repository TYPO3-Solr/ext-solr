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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Site;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Site\Entity\Site as CoreSite;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Testcase to check if the SiteRepository class works as expected.
 *
 * The unit test is used to make sure that the SiteRepository works as expected
 *
 * @author Thomas Hohn <tho@systime.dk>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteRepositoryTest extends SetUpUnitTestCase
{
    protected TwoLevelCache|MockObject $cacheMock;
    protected RootPageResolver|MockObject $rootPageResolverMock;
    protected SiteRepository|MockObject $siteRepository;
    protected SiteFinder|MockObject $siteFinderMock;

    protected function setUp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        $this->cacheMock = $this->createMock(TwoLevelCache::class);
        $this->rootPageResolverMock = $this->createMock(RootPageResolver::class);
        $this->siteFinderMock = $this->createMock(SiteFinder::class);

        // we mock buildSite to avoid the creation of real Site objects and pass all dependencies as mock
        $this->siteRepository = $this->getMockBuilder(SiteRepository::class)
            ->setConstructorArgs([$this->rootPageResolverMock, $this->cacheMock, $this->siteFinderMock])
            ->onlyMethods(['buildSite'])
            ->getMock();
        parent::setUp();
    }

    #[Test]
    public function canGetSiteByRootPageId(): void
    {
        $this->fakeEmptyCache();

        $this->assertThatSitesAreCreatedWithPageIds([4711]);
        $this->assertCacheIsWritten();

        $site = $this->siteRepository->getSiteByRootPageId(4711);
        self::assertInstanceOf(Site::class, $site);
    }

    #[Test]
    public function canGetSiteByPageId(): void
    {
        $this->fakeEmptyCache();
        $this->fakeExistingRootPage(222, 111);

        $this->assertThatSitesAreCreatedWithPageIds([111]);
        $this->assertCacheIsWritten();

        $site = $this->siteRepository->getSiteByPageId(222);
        self::assertInstanceOf(Site::class, $site);
    }

    #[Test]
    public function canGetFirstAvailableSite(): void
    {
        $this->fakeEmptyCache();

        $siteMock = $this->getSiteMock(333, [0, 1, 2]);
        $this->fakeSitesInTYPO3Systems([$siteMock]);

        $this->assertThatSitesAreCreatedWithPageIds([333], [
            0 => ['language' => 0],
        ]);
        $this->assertCacheIsWritten();

        $site = $this->siteRepository->getFirstAvailableSite();
        self::assertInstanceOf(Site::class, $site);
    }

    #[Test]
    public function canGetAvailableSites(): void
    {
        $this->fakeEmptyCache();
        $siteMockA = $this->getSiteMock(123, [0, 1]);
        $siteMockB = $this->getSiteMock(234, [0, 2]);
        $this->fakeSitesInTYPO3Systems([$siteMockA, $siteMockB]);

        $this->assertThatSitesAreCreatedWithPageIds([123, 234], [0 => ['language' => 0], 1 => ['language' => 1]]);
        $this->assertCacheIsWritten();

        $sites = $this->siteRepository->getAvailableSites();
        self::assertCount(2, $sites, 'We expect to have two sites with two languages');
    }

    #[Test]
    public function canGetAllLanguages(): void
    {
        $this->fakeEmptyCache();
        $siteMockA = $this->getSiteMock(123, [0, 2, 5]);
        $siteMockB = $this->getSiteMock(234, [0]);
        $this->fakeSitesInTYPO3Systems([$siteMockA, $siteMockB]);

        $this->assertThatSitesAreCreatedWithPageIds(
            [123, 234],
            [
                0 => ['language' => 0],
                2 => ['language' => 2],
                5 => ['language' => 5],
            ]
        );

        $siteOne = $this->siteRepository->getFirstAvailableSite();
        $connections = $siteOne->getAllSolrConnectionConfigurations();
        self::assertEquals([0, 2, 5], array_keys($connections), 'Could not get languages for site');
    }

    protected function fakeEmptyCache(): void
    {
        $this->cacheMock->expects(self::any())->method('get')->willReturn(null);
    }

    protected function assertCacheIsWritten(): void
    {
        $this->cacheMock->expects(self::once())->method('set');
    }

    protected function assertThatSitesAreCreatedWithPageIds(array $pageIds, array $fakedConnectionConfiguration = []): void
    {
        $this->siteRepository->expects(self::any())->method('buildSite')->willReturnCallback(
            function($idToUse) use ($pageIds, $fakedConnectionConfiguration) {
                if (in_array($idToUse, $pageIds)) {
                    $site = $this->createMock(Site::class);
                    $site->expects($this->any())->method('getRootPageId')->willReturn(
                        $idToUse
                    );
                    $site->expects($this->any())->method('isEnabled')->willReturn(count($fakedConnectionConfiguration) > 0);
                    $site->expects($this->any())
                        ->method('getAllSolrConnectionConfigurations')
                        ->willReturn($fakedConnectionConfiguration);
                    return $site;
                }
            }
        );
    }

    protected function fakeExistingRootPage(int $forPageId, int $rootPageId): void
    {
        $this->rootPageResolverMock->expects(self::any())->method('getRootPageId')->with($forPageId)->willReturn($rootPageId);
    }

    /**
     * @param array<int, int> $languageUids
     */
    protected function getSiteMock(int $rootPageUid, array $languageUids): CoreSite|MockObject
    {
        $siteMock = $this->createMock(CoreSite::class);
        $siteMock->expects(self::any())->method('getRootPageId')->willReturn($rootPageUid);

        $languageMocks = [];

        foreach ($languageUids as $languageUid) {
            $languageMock = $this->createMock(SiteLanguage::class);
            $languageMock->expects(self::any())->method('getLanguageId')->willReturn($languageUid);
            $languageMocks[] = $languageMock;
        }

        $siteMock->expects(self::any())->method('getAllLanguages')->willReturn($languageMocks);
        $siteMock->expects(self::any())->method('getDefaultLanguage')->willReturn(reset($languageMocks));

        return $siteMock;
    }

    /**
     * @param array $sitesInTYPO3
     */
    protected function fakeSitesInTYPO3Systems(array $sitesInTYPO3): void
    {
        $this->siteFinderMock->expects(self::any())->method('getAllSites')->willReturn($sitesInTYPO3);
    }
}
