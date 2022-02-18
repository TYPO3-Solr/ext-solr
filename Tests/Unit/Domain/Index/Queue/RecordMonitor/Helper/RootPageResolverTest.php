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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\RecordMonitor\Helper;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RootPageResolverTest extends UnitTest
{
    /**
     * @var TwoLevelCache
     */
    protected $cacheMock;

    /**
     * @var ConfigurationAwareRecordService
     */
    protected $recordServiceMock;

    /**
     * @var RootPageResolver
     */
    protected $rootPageResolver;

    protected function setUp(): void
    {
        $this->fakeDisabledCache();

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];

        $this->recordServiceMock = $this->getDumbMock(ConfigurationAwareRecordService::class);

        /** @var $rootPageResolver RootPageResolver */
        $this->rootPageResolver = $this->getMockBuilder(RootPageResolver::class)
            ->setConstructorArgs([$this->recordServiceMock, $this->cacheMock])
            ->onlyMethods(['getIsRootPageId', 'getAlternativeSiteRootPagesIds', 'getRootPageIdByTableAndUid', 'getRecordPageId', 'getPageRecordByPageId'])->getMock();
        parent::setUp();
    }

    /**
     * @test
     */
    public function getResponsibleRootPageIdsMergesRootLineAndTypoScriptReferences()
    {
        $this->rootPageResolver->expects(self::once())->method('getRootPageIdByTableAndUid')->willReturn(222);
        $this->rootPageResolver->expects(self::once())->method('getRecordPageId')->willReturn(111);

        $this->rootPageResolver->expects(self::once())->method('getIsRootPageId')->willReturn(true);
        $this->rootPageResolver->expects(self::once())->method('getAlternativeSiteRootPagesIds')->willReturn([333, 444]);

        $resolvedRootPages = $this->rootPageResolver->getResponsibleRootPageIds('pages', 41);

        $message = 'Root page resolver did not retrieve and merge root page ids from root line and typoscript references';
        self::assertEquals([222, 333, 444], $resolvedRootPages, $message);
    }

    /**
     * @test
     */
    public function getResponsibleRootPageIdsIgnoresPageFromRootLineThatIsNoSiteRoot()
    {
        $this->rootPageResolver->expects(self::once())->method('getRootPageIdByTableAndUid')->willReturn(222);
        $this->rootPageResolver->expects(self::once())->method('getRecordPageId')->willReturn(111);

        $this->rootPageResolver->expects(self::once())->method('getIsRootPageId')->willReturn(false);
        $this->rootPageResolver->expects(self::once())->method('getAlternativeSiteRootPagesIds')->willReturn([333, 444]);

        $resolvedRootPages = $this->rootPageResolver->getResponsibleRootPageIds('pages', 41);

        $message = 'Root page resolver should only return rootPageIds from references';
        self::assertEquals([333, 444], $resolvedRootPages, $message);
    }

    /**
     * @test
     */
    public function getIsRootPageIdWithPageIdZero()
    {
        /* @var RootPageResolver|MockObject $rootPageResolver */
        $this->rootPageResolver = $this->getMockBuilder(RootPageResolver::class)
            ->setConstructorArgs([$this->recordServiceMock, $this->cacheMock])
            ->onlyMethods([])
            ->getMock();
        $rootPage = $this->rootPageResolver->getIsRootPageId(0);
        self::assertFalse($rootPage);
    }

    /**
     * @test
     */
    public function getIsRootPageWithPageIdMinusOne()
    {
        $this->rootPageResolver = $this->getMockBuilder(RootPageResolver::class)
            ->setConstructorArgs([$this->recordServiceMock, $this->cacheMock])
            ->onlyMethods([])
            ->getMock();
        $rootPage = $this->rootPageResolver->getIsRootPageId(-1);
        self::assertFalse($rootPage);
    }

    /**
     * @test
     */
    public function getIsRootPageIdWithUnknownPageId()
    {
        $this->rootPageResolver = $this->getMockBuilder(RootPageResolver::class)
            ->setConstructorArgs([$this->recordServiceMock, $this->cacheMock])
            ->onlyMethods(['getPageRecordByPageId'])->getMock();
        $this->rootPageResolver->expects(self::once())->method('getPageRecordByPageId')->willReturn(null);
        $this->expectException(\InvalidArgumentException::class);
        $this->rootPageResolver->getIsRootPageId(42);
    }

    protected function fakeDisabledCache()
    {
        $this->cacheMock = $this->getDumbMock(TwoLevelCache::class);
        $this->cacheMock->method('get')->willReturn(false);
    }
}
