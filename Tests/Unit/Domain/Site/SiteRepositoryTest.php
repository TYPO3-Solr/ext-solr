<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Site;

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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Registry;
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
class SiteRepositoryTest extends UnitTest
{
    /**
     * @var TwoLevelCache
     */
    protected $cacheMock;

    /**
     * @var RootPageResolver
     */
    protected $rootPageResolverMock;

    /**
     * @var Registry
     */
    protected $registryMock;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;

    /**
     * @var SiteFinder
     */
    protected $siteFinderMock;

    public function setUp()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        $this->cacheMock = $this->getDumbMock(TwoLevelCache::class);
        $this->rootPageResolverMock = $this->getDumbMock(RootPageResolver::class);
        $this->registryMock = $this->getDumbMock(Registry::class);
        $this->siteFinderMock = $this->getDumbMock(SiteFinder::class);

        // we mock buildSite to avoid the creation of real Site objects and pass all dependencies as mock
        $this->siteRepository = $this->getMockBuilder(SiteRepository::class)
            ->setConstructorArgs([$this->rootPageResolverMock, $this->cacheMock, $this->registryMock, $this->siteFinderMock])
            ->setMethods(['buildSite','getSolrServersFromRegistry'])
            ->getMock();
    }

    /**
     * @test
     */
    public function canGetSiteByRootPageId()
    {
        $this->fakeEmptyCache();

        $this->assertThatSitesAreCreatedWithPageIds([4711]);
        $this->assertCacheIsWritten();

        $site = $this->siteRepository->getSiteByRootPageId(4711);
        $this->assertInstanceOf(Site::class, $site);
    }

    /**
     * @test
     */
    public function canGetSiteByPageId()
    {
        $this->fakeEmptyCache();
        $this->fakeExistingRootPage(222, 111);

        $this->assertThatSitesAreCreatedWithPageIds([111]);
        $this->assertCacheIsWritten();

        $site = $this->siteRepository->getSiteByPageId(222);
        $this->assertInstanceOf(Site::class, $site);
    }

    /**
     * @test
     */
    public function canGetFirstAvailableSite()
    {
        $this->fakeEmptyCache();

        $siteMock = $this->getSiteMock(333, [0,1,2]);
        $this->fakeSitesInTYPO3Systems([$siteMock]);

        $this->assertThatSitesAreCreatedWithPageIds([333], [
            0 => ['language' => 0]
        ]);
        $this->assertCacheIsWritten();

        $site = $this->siteRepository->getFirstAvailableSite();
        $this->assertInstanceOf(Site::class, $site);
    }

    /**
     * @test
     */
    public function canGetAvailableSites()
    {
        $this->fakeEmptyCache();
        $siteMockA = $this->getSiteMock(123, [0,1]);
        $siteMockB = $this->getSiteMock(234, [0,2]);
        $this->fakeSitesInTYPO3Systems([$siteMockA, $siteMockB]);

        $this->assertThatSitesAreCreatedWithPageIds([123,234],[0 => ['language' => 0], 1 => ['language' => 1]]);
        $this->assertCacheIsWritten();

        $sites = $this->siteRepository->getAvailableSites();
        $this->assertCount(2, $sites, 'We expect to have two sites with two languages');
    }

    /**
     * @test
     */
    public function canGetAllLanguages()
    {
        $this->fakeEmptyCache();
        $siteMockA = $this->getSiteMock(123, [0,2,5]);
        $siteMockB = $this->getSiteMock(234, [0]);
        $this->fakeSitesInTYPO3Systems([$siteMockA, $siteMockB]);

        $this->assertThatSitesAreCreatedWithPageIds(
            [123,234],
            [
                0 => ['language' => 0],
                2 => ['language' => 2],
                5 => ['language' => 5],
            ]
        );

        $siteOne = $this->siteRepository->getFirstAvailableSite();
        $connections =$siteOne->getAllSolrConnectionConfigurations();
        $this->assertEquals([0,2,5], array_keys($connections), 'Could not get languages for site');
    }

    /**
     * @return void
     */
    protected function fakeEmptyCache()
    {
        $this->cacheMock->expects($this->any())->method('get')->will($this->returnValue(null));
    }

    /**
     * @return void
     */
    protected function assertCacheIsWritten()
    {
        $this->cacheMock->expects($this->once())->method('set');
    }

    /**
     * @param array $pageIds
     * @param array $fakedConnectionConfiguration
     */
    protected function assertThatSitesAreCreatedWithPageIds(array $pageIds, array $fakedConnectionConfiguration = [])
    {
        $this->siteRepository->expects($this->any())->method('buildSite')->will(
            $this->returnCallback(function($idToUse) use ($pageIds, $fakedConnectionConfiguration) {
                if(in_array($idToUse, $pageIds)) {
                    $site = $this->getDumbMock(Site::class);
                    $site->expects($this->any())->method('getRootPageId')->will(
                        $this->returnValue($idToUse)
                    );
                    $site->expects($this->any())->method('isEnabled')->willReturn(count($fakedConnectionConfiguration) > 0);
                    $site->expects($this->any())
                        ->method('getAllSolrConnectionConfigurations')
                        ->willReturn($fakedConnectionConfiguration);
                    return $site;
                }
            })
        );
    }

    /**
     * @param integer $forPageId
     * @param integer $rootPageId
     */
    protected function fakeExistingRootPage($forPageId, $rootPageId)
    {
        $this->rootPageResolverMock->expects($this->any())->method('getRootPageId')->with($forPageId)->will($this->returnValue($rootPageId));
    }

    /**
     * @param int $rootPageUid
     * @return \TYPO3\CMS\Core\Site\Entity\Site
     */
    protected function getSiteMock(int $rootPageUid, array $languageUids)
    {
            /** @var \TYPO3\CMS\Core\Site\Entity\Site $siteMock */
        $siteMock = $this->getDumbMock( \TYPO3\CMS\Core\Site\Entity\Site::class);
        $siteMock->expects($this->any())->method('getRootPageId')->willReturn($rootPageUid);

        $languageMocks = [];
        $defaultLanguage = null;

        foreach($languageUids as $languageUid) {
            $languageMock = $this->getDumbMock(SiteLanguage::class);
            $languageMock->expects($this->any())->method('getLanguageId')->willReturn($languageUid);
            $languageMocks[] = $languageMock;
        }

        $siteMock->expects($this->any())->method('getAllLanguages')->willReturn($languageMocks);
        $siteMock->expects($this->any())->method('getDefaultLanguage')->willReturn(reset($languageMocks));

        return $siteMock;
    }

    /**
     * @param array $sitesInRegistry
     */
    protected function fakeSitesInRegistry(array $sitesInRegistry)
    {
        $this->siteRepository->expects($this->any())->method('getSolrServersFromRegistry')->will(
            $this->returnValue($sitesInRegistry)
        );
    }

    /**
     * @param array $sitesInTYPO3
     */
    protected function fakeSitesInTYPO3Systems(array $sitesInTYPO3)
    {
        $this->siteFinderMock->expects($this->any())->method('getAllSites')->will(
            $this->returnValue($sitesInTYPO3)
        );
    }
 }
