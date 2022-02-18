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
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class IndexServiceTest extends UnitTest
{
    /**
     * @var Site
     */
    protected $siteMock;

    /**
     * @var Queue
     */
    protected $queueMock;

    /**
     * @var Dispatcher
     */
    protected $dispatcherMock;

    /**
     * @var SolrLogManager
     */
    protected $logManagerMock;

    protected function setUp(): void
    {
        $this->siteMock = $this->getDumbMock(Site::class);
        $this->queueMock = $this->getDumbMock(Queue::class);
        $this->dispatcherMock = $this->getDumbMock(Dispatcher::class);
        $this->logManagerMock = $this->getDumbMock(SolrLogManager::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function signalsAreTriggered()
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->siteMock->expects(self::once())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        // we create an IndexeService where indexItem is mocked to avoid real indexing in the unit test
        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->dispatcherMock, $this->logManagerMock])
            ->onlyMethods(['indexItem'])
            ->getMock();

        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item2 = $this->getDumbMock(Item::class);
        $fakeItems = [$item1, $item2];
        $this->fakeQueueItemContent($fakeItems);

        // we assert that 6 signals will be dispatched 1 at the beginning 1 before and after each items and 1 at the end.
        $this->assertSignalsWillBeDispatched(6);
        $indexService->indexItems(2);
    }

    /**
     * @test
     */
    public function testConfigurationIsNotFetchedWhenProgressIsCaluclated()
    {
        $this->siteMock->expects(self::never())->method('getSolrConfiguration');

        $statisticMock = $this->getDumbMock(QueueStatistic::class);
        $statisticMock->expects(self::once())->method('getSuccessPercentage')->willReturn(50.0);
        $this->queueMock->expects(self::once())->method('getStatisticsBySite')->willReturn($statisticMock);

        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->dispatcherMock, $this->logManagerMock])
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
        $this->siteMock->expects(self::once())->method('getSolrConfiguration')->willReturn($fakeConfiguration);
        $this->siteMock->expects(self::once())->method('getDomain')->willReturn('www.indextest.local');

        /** @var $indexService IndexService */
        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->dispatcherMock, $this->logManagerMock])
            ->onlyMethods(['getIndexerByItem', 'restoreOriginalHttpHost'])
            ->getMock();

        $indexService->expects(self::exactly(2))->method('restoreOriginalHttpHost');

        $indexerMock = $this->getDumbMock(Indexer::class);
        $indexerMock->expects(self::exactly(2))->method('index')->willReturnCallback(function () {
            throw new Exception('unknowen error occured');
        });
        $indexService->expects(self::exactly(2))->method('getIndexerByItem')->willReturn($indexerMock);

        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item1->expects(self::any())->method('getSite')->willReturn($this->siteMock);
        $item2 = $this->getDumbMock(Item::class);

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

        /** @var $indexService IndexService */
        $indexService = $this->getMockBuilder(IndexService::class)
            ->setConstructorArgs([$this->siteMock, $this->queueMock, $this->dispatcherMock, $this->logManagerMock])
            ->onlyMethods(['getIndexerByItem'])
            ->getMock();

        $indexerMock = $this->getDumbMock(Indexer::class);
        $indexerMock->expects(self::exactly(2))->method('index')->willReturn(true);
        $indexService->expects(self::exactly(2))->method('getIndexerByItem')->willReturn($indexerMock);

        // we fake an index queue with two items
        $item1 = $this->getDumbMock(Item::class);
        $item1->expects(self::any())->method('getSite')->willReturn($this->siteMock);
        $item2 = $this->getDumbMock(Item::class);

        $fakeItems = [$item1, $item2];
        $this->fakeQueueItemContent($fakeItems);

        $indexService->indexItems(2);
    }

    /**
     * @param $fakeItems
     */
    protected function fakeQueueItemContent($fakeItems)
    {
        $this->queueMock->expects(self::once())->method('getItemsToIndex')->willReturn($fakeItems);
    }

    /**
     * @param int $amount
     */
    protected function assertSignalsWillBeDispatched($amount = 0)
    {
        $this->dispatcherMock->expects(self::exactly($amount))->method('dispatch');
    }
}
