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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index;

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatistic;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexServiceTest extends UnitTest
{
    /**
     * @var Site|MockObject
     */
    protected $siteMock;

    /**
     * @var Queue
     */
    protected $queueMock;

    /**
     * @var EventDispatcher|MockObject
     */
    protected EventDispatcher|MockObject $eventDispatcherMock;

    /**
     * @var SolrLogManager
     */
    protected $logManagerMock;

    protected function setUp(): void
    {
        $this->siteMock = $this->getDumbMock(Site::class);
        $this->queueMock = $this->getDumbMock(Queue::class);
        $this->eventDispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatch'])
            ->getMock();
        $this->eventDispatcherMock->method('dispatch')
            ->willReturnArgument(0);
        $this->logManagerMock = $this->getDumbMock(SolrLogManager::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function eventsAreTriggered()
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->siteMock = $this->getMockBuilder(Site::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSolrConfiguration'])
            ->getMock();
        $this->siteMock->expects(self::once())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        // we create an IndexeService where indexItem is mocked to avoid real indexing in the unit test
        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->eventDispatcherMock, $this->logManagerMock])
            ->onlyMethods(['indexItem'])
            ->getMock();

        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item2 = $this->getDumbMock(Item::class);
        $fakeItems = [$item1, $item2];
        $this->fakeQueueItemContent($fakeItems);

        // we assert that 6 signals will be dispatched 1 at the beginning 1 before and after each items and 1 at the end.
        $this->assertEventsWillBeDispatched(6);
        $indexService->indexItems(2);
    }

    /**
     * @test
     */
    public function testConfigurationIsNotFetchedWhenProgressIsCalculated()
    {
        $this->siteMock->expects(self::never())->method('getSolrConfiguration');

        $statisticMock = $this->getDumbMock(QueueStatistic::class);
        $statisticMock->expects(self::once())->method('getSuccessPercentage')->willReturn(50.0);
        $this->queueMock->expects(self::once())->method('getStatisticsBySite')->willReturn($statisticMock);

        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->eventDispatcherMock, $this->logManagerMock])
            ->onlyMethods(['indexItem'])
            ->getMock();

        $progress = $indexService->getProgress();
        self::assertEquals(50, $progress);
    }

    /**
     * @test
     */
    public function testServerHostIsRestoredInCaseOfAnException()
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->siteMock = $this->getMockBuilder(Site::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSolrConfiguration', 'getDomain'])
            ->getMock();
        $this->siteMock->expects(self::any())->method('getSolrConfiguration')->willReturn($fakeConfiguration);
        $this->siteMock->expects(self::any())->method('getDomain')->willReturn('www.indextest.local');

        /* @var IndexService|MockObject $indexService */
        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->eventDispatcherMock, $this->logManagerMock])
            ->onlyMethods(['getIndexerByItem', 'restoreOriginalHttpHost'])
            ->getMock();

        $indexService->expects(self::exactly(2))->method('restoreOriginalHttpHost');

        $indexerMock = $this->getMockBuilder(Indexer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['index'])
            ->getMock();
        $indexerMock->expects(self::exactly(2))->method('index')->willReturnCallback(function () {
            throw new Exception('unknown error occurred');
        });
        $indexService->expects(self::exactly(2))->method('getIndexerByItem')->willReturn($indexerMock);

        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item1->expects(self::any())->method('getSite')->willReturn($this->siteMock);
        $item1->expects(self::any())->method('getIndexingConfigurationName')->willReturn('fake_table');
        $item2 = $this->getDumbMock(Item::class);
        $item2->expects(self::any())->method('getIndexingConfigurationName')->willReturn('fake_table');

        $fakeItems = [$item1, $item2];
        $this->fakeQueueItemContent($fakeItems);

        $indexService->indexItems(2);
    }

    /**
     * @test
     */
    public function testDomainIsUsedFromSiteObject()
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->siteMock->expects(self::once())->method('getSolrConfiguration')->willReturn($fakeConfiguration);
        $this->siteMock->expects(self::any())->method('getDomain')->willReturn('www.indextest.local');

        /* @var IndexService|MockObject $indexService  */
        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->eventDispatcherMock, $this->logManagerMock])
            ->onlyMethods(['getIndexerByItem'])
            ->getMock();

        $indexerMock = $this->getDumbMock(Indexer::class);
        $indexerMock->expects(self::exactly(2))->method('index')->willReturn(true);
        $indexService->expects(self::exactly(2))->method('getIndexerByItem')->willReturn($indexerMock);

        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item1->expects(self::any())->method('getSite')->willReturn($this->siteMock);
        $item1->expects(self::any())->method('getIndexingConfigurationName')->willReturn('fake_table');
        $item2 = $this->getDumbMock(Item::class);
        $item2->expects(self::any())->method('getIndexingConfigurationName')->willReturn('fake_table');

        $fakeItems = [$item1, $item2];
        $this->fakeQueueItemContent($fakeItems);

        $indexService->indexItems(2);
    }

    /**
     * @param $fakeItems
     */
    protected function fakeQueueItemContent($fakeItems)
    {
        $this->queueMock
            ->expects(self::once())
            ->method('getItemsToIndex')
            ->willReturn($fakeItems);
    }

    /**
     * @param int $amount
     */
    protected function assertEventsWillBeDispatched(int $amount = 0)
    {
        $this->eventDispatcherMock->expects(self::exactly($amount))
            ->method('dispatch')
            ->willReturnArgument(0);
    }
}
