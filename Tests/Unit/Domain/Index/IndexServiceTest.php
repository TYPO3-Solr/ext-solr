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
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use RuntimeException;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexServiceTest extends SetUpUnitTestCase
{
    protected Site|MockObject $siteMock;
    protected Queue|MockObject $queueMock;
    protected EventDispatcher|MockObject $eventDispatcherMock;
    protected SolrLogManager|MockObject $logManagerMock;

    protected function setUp(): void
    {
        $this->siteMock = $this->createMock(Site::class);
        $this->queueMock = $this->createMock(Queue::class);
        $this->eventDispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatch'])
            ->getMock();
        $this->eventDispatcherMock->method('dispatch')
            ->willReturnArgument(0);
        $this->logManagerMock = $this->createMock(SolrLogManager::class);
        parent::setUp();
    }

    /**
     * Helper: register a mock IndexingService in the DI container
     * so that IndexService can resolve it via GeneralUtility::getContainer().
     */
    protected function mockIndexingServiceInContainer(IndexingService|MockObject $indexingServiceMock): void
    {
        $containerMock = $this->createMock(ContainerInterface::class);
        $containerMock->method('get')
            ->with(IndexingService::class)
            ->willReturn($indexingServiceMock);
        GeneralUtility::setContainer($containerMock);
    }

    #[Test]
    public function eventsAreTriggered(): void
    {
        $fakeConfiguration = $this->createMock(TypoScriptConfiguration::class);
        $this->siteMock = $this->getMockBuilder(Site::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSolrConfiguration'])
            ->getMock();
        $this->siteMock->expects(self::once())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $indexingServiceMock = $this->createMock(IndexingService::class);
        $indexingServiceMock->method('indexItems')->willReturn(true);
        $this->mockIndexingServiceInContainer($indexingServiceMock);

        $indexService = new IndexService(
            $this->siteMock,
            $this->queueMock,
            $this->eventDispatcherMock,
            $this->logManagerMock,
        );

        $item1 = $this->createMock(Item::class);
        $item1->method('getType')->willReturn('tx_test');
        $item1->method('getItemPid')->willReturn(1);
        $item1->method('getRecordPageId')->willReturn(1);
        $item2 = $this->createMock(Item::class);
        $item2->method('getType')->willReturn('tx_test');
        $item2->method('getItemPid')->willReturn(1);
        $item2->method('getRecordPageId')->willReturn(1);
        $this->fakeQueueItemContent([$item1, $item2]);

        // 6 events: 1 before all + 1 before + 1 after each item (2 items) + 1 after all
        $this->assertEventsWillBeDispatched(6);
        $indexService->indexItems(2);
    }

    #[Test]
    public function testConfigurationIsNotFetchedWhenProgressIsCalculated(): void
    {
        $this->siteMock->expects(self::never())->method('getSolrConfiguration');

        $statisticMock = $this->createMock(QueueStatistic::class);
        $statisticMock->expects(self::once())->method('getSuccessPercentage')->willReturn(50.0);
        $this->queueMock->expects(self::once())->method('getStatisticsBySite')->willReturn($statisticMock);

        $indexService = new IndexService(
            $this->siteMock,
            $this->queueMock,
            $this->eventDispatcherMock,
            $this->logManagerMock,
        );

        $progress = $indexService->getProgress();
        self::assertEquals(50, $progress);
    }

    #[Test]
    public function testExceptionsAreCaughtAndItemMarkedAsFailed(): void
    {
        $fakeConfiguration = $this->createMock(TypoScriptConfiguration::class);
        $this->siteMock = $this->getMockBuilder(Site::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSolrConfiguration'])
            ->getMock();
        $this->siteMock->expects(self::any())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $indexingServiceMock = $this->createMock(IndexingService::class);
        $indexingServiceMock->method('indexItems')->willThrowException(
            new RuntimeException('unknown error occurred', 8102831540),
        );
        $this->mockIndexingServiceInContainer($indexingServiceMock);

        $indexService = new IndexService(
            $this->siteMock,
            $this->queueMock,
            $this->eventDispatcherMock,
            $this->logManagerMock,
        );

        $item1 = $this->createMock(Item::class);
        $item1->method('getType')->willReturn('tx_test');
        $item1->method('getItemPid')->willReturn(1);
        $item1->method('getRecordPageId')->willReturn(1);
        $item1->method('getIndexQueueUid')->willReturn(1);
        $item2 = $this->createMock(Item::class);
        $item2->method('getType')->willReturn('tx_test');
        $item2->method('getItemPid')->willReturn(1);
        $item2->method('getRecordPageId')->willReturn(1);
        $item2->method('getIndexQueueUid')->willReturn(2);
        $this->fakeQueueItemContent([$item1, $item2]);

        $result = $indexService->indexItems(2);
        self::assertFalse($result);
    }

    #[Test]
    public function testDomainIsUsedFromSiteObject(): void
    {
        $fakeConfiguration = $this->createMock(TypoScriptConfiguration::class);
        $this->siteMock->expects(self::once())->method('getSolrConfiguration')->willReturn($fakeConfiguration);

        $indexingServiceMock = $this->createMock(IndexingService::class);
        $indexingServiceMock->expects(self::exactly(2))->method('indexItems')->willReturn(true);
        $this->mockIndexingServiceInContainer($indexingServiceMock);

        $indexService = new IndexService(
            $this->siteMock,
            $this->queueMock,
            $this->eventDispatcherMock,
            $this->logManagerMock,
        );

        $item1 = $this->createMock(Item::class);
        $item1->method('getType')->willReturn('tx_test');
        $item1->method('getItemPid')->willReturn(1);
        $item1->method('getRecordPageId')->willReturn(1);
        $item2 = $this->createMock(Item::class);
        $item2->method('getType')->willReturn('tx_test');
        $item2->method('getItemPid')->willReturn(1);
        $item2->method('getRecordPageId')->willReturn(1);
        $this->fakeQueueItemContent([$item1, $item2]);

        $indexService->indexItems(2);
    }

    protected function fakeQueueItemContent(array $fakeItems): void
    {
        $this->queueMock
            ->expects(self::once())
            ->method('getItemsToIndex')
            ->willReturn($fakeItems);
    }

    protected function assertEventsWillBeDispatched(int $amount = 0): void
    {
        $this->eventDispatcherMock->expects(self::exactly($amount))
            ->method('dispatch')
            ->willReturnArgument(0);
    }
}
