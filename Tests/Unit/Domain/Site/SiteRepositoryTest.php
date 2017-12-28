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
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Core\Registry;

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

    public function setUp()
    {
        $this->cacheMock = $this->getDumbMock(TwoLevelCache::class);
        $this->rootPageResolverMock = $this->getDumbMock(RootPageResolver::class);
        $this->registryMock = $this->getDumbMock(Registry::class);

        // we mock buildSite to avoid the creation of real Site objects and pass all dependencies as mock
        $this->siteRepository = $this->getMockBuilder(SiteRepository::class)
            ->setConstructorArgs([$this->rootPageResolverMock, $this->cacheMock, $this->registryMock])
            ->setMethods(['buildSite'])
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
        $this->fakeSitesInRegistry([
            '333|0' => ['rootPageUid' => 333],
            '333|1' => ['rootPageUid' => 333],
            '333|2' => ['rootPageUid' => 333]
        ]);

        $this->assertThatSitesAreCreatedWithPageIds([333]);
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
        $this->fakeSitesInRegistry([
            '123|0' => ['rootPageUid' => 123],
            '123|1' => ['rootPageUid' => 123],
            '234|0' => ['rootPageUid' => 234],
            '234|2' => ['rootPageUid' => 234],
        ]);

        $this->assertThatSitesAreCreatedWithPageIds([123,234]);
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
        $this->fakeSitesInRegistry([
            '123|0' => ['rootPageUid' => 123],
            '123|1' => ['rootPageUid' => 123],
            '123|2' => ['rootPageUid' => 123],
            '234|0' => ['rootPageUid' => 234]
        ]);
        $this->assertThatSitesAreCreatedWithPageIds([123,234]);

        $siteOne = $this->siteRepository->getFirstAvailableSite();
        $languages = $this->siteRepository->getAllLanguages($siteOne);
        $this->assertEquals([0,1,2], $languages, 'Could not get languages for site');
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
     */
    protected function assertThatSitesAreCreatedWithPageIds(array $pageIds)
    {
        $this->siteRepository->expects($this->any())->method('buildSite')->will(
            $this->returnCallback(function($idToUse) use ($pageIds) {
                if(in_array($idToUse, $pageIds)) {
                    $site = $this->getDumbMock(Site::class);
                    $site->expects($this->any())->method('getRootPageId')->will(
                        $this->returnValue($idToUse)
                    );
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
     * @param array $sitesInRegistry
     */
    protected function fakeSitesInRegistry(array $sitesInRegistry)
    {
        $this->registryMock->expects($this->any())->method('get')->will(
            $this->returnValue($sitesInRegistry)
        );
    }
 }