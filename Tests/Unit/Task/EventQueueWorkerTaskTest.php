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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Task;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Execution;
use TYPO3\CMS\Scheduler\Scheduler;

/**
 * Testcase for EventQueueWorkerTask
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class EventQueueWorkerTaskTest extends UnitTest
{
    /**
     * @var EventQueueWorkerTask
     */
    protected $task;

    protected function setUp(): void
    {
        GeneralUtility::setSingletonInstance(Scheduler::class, $this->createMock(Scheduler::class));
        GeneralUtility::addInstance(Execution::class, $this->createMock(Execution::class));

        $this->task = new EventQueueWorkerTask();
        $this->task->setLimit(99);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function canProcessEventQueue(): void
    {
        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $eventDispatcherMock);

        /** @var RecordUpdatedEvent $event */
        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');
        $serializedEvent = serialize($event);
        /** @var RecordUpdatedEvent $unserializedEvent */
        $unserializedEvent = unserialize($serializedEvent);
        $queueItem = [
            'uid' => 10,
            'event' => $serializedEvent,
        ];

        $eventQueueItemRepositoryMock
            ->expects(self::once())
            ->method('getEventQueueItems')
            ->with(99)
            ->willReturn([$queueItem]);

        $dispatchedEvents = [];
        $eventDispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvents) {
                $dispatchedEvents[] = func_get_arg(0);
            });

        $eventQueueItemRepositoryMock
            ->expects(self::never())
            ->method('updateEventQueueItem');

        $eventQueueItemRepositoryMock
            ->expects(self::once())
            ->method('deleteEventQueueItems')
            ->with([10]);

        $this->task->execute();

        $unserializedEvent->setForceImmediateProcessing(true);
        self::assertCount(2, $dispatchedEvents);
        self::assertEquals($unserializedEvent, $dispatchedEvents[0]);
        self::assertTrue($dispatchedEvents[1] instanceof DelayedProcessingFinishedEvent);
    }

    /**
     * @test
     */
    public function canHandleErrors(): void
    {
        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        GeneralUtility::addInstance(EventDispatcherInterface::class, $eventDispatcherMock);
        $solrLogManagerMock = $this->createMock(SolrLogManager::class);
        GeneralUtility::addInstance(SolrLogManager::class, $solrLogManagerMock);

        /** @var RecordUpdatedEvent $event */
        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');
        $serializedEvent = serialize($event);
        /** @var RecordUpdatedEvent $unserializedEvent */
        $unserializedEvent = unserialize($serializedEvent);
        $queueItem = [
            'uid' => 10,
            'event' => $serializedEvent,
        ];

        $eventQueueItemRepositoryMock
            ->expects(self::once())
            ->method('getEventQueueItems')
            ->with(99)
            ->willReturn([$queueItem]);

        $eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new \Exception('', 1641889238));

        $solrLogManagerMock
            ->expects(self::once())
            ->method('log')
            ->with(SolrLogManager::ERROR, self::anything(), self::anything());

        $eventQueueItemRepositoryMock
            ->expects(self::once())
            ->method('updateEventQueueItem')
            ->with(10, ['error' => 1, 'error_message' => '[1641889238]']);

        $eventQueueItemRepositoryMock
            ->expects(self::once())
            ->method('deleteEventQueueItems')
            ->with([]);

        $this->task->execute();
    }
}
