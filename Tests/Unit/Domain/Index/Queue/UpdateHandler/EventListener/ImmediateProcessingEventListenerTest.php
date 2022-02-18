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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\EventListener;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\AbstractBaseEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\ProcessingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\ImmediateProcessingEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\PageMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\VersionSwappedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the ImmediateProcessingEventListener
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class ImmediateProcessingEventListenerTest extends AbstractEventListenerTest
{
    protected function setUp(): void
    {
        if (!class_exists('SolrUnitTestsInvalidDataUpdateEvent')) {
            eval(
                'use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;'
                . 'class SolrUnitTestsInvalidDataUpdateEvent extends ContentElementDeletedEvent {}'
            );
        }
        parent::setUp();
    }

    /**
     * @param string $eventClass
     * @param string $handlerClass
     * @param array $eventArguments
     * @param bool $eventHandled
     *
     * @test
     * @dataProvider canHandleEventsDataProvider
     */
    public function canHandleEvents(
        string $eventClass,
        string $handlerClass,
        array $eventArguments,
        bool $eventHandled
    ): void {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getMonitoringType')
            ->willReturn(0);

        /** @var DataUpdateEventInterface $event */
        $event = new $eventClass(...$eventArguments);
        $this->checkEventHandling($event, $handlerClass, $eventHandled);
    }

    /**
     * @param string $eventClass
     * @param string $handlerClass
     * @param array $eventArguments
     * @param bool $eventHandled
     *
     * @test
     * @dataProvider canHandleEventsDataProvider
     */
    public function canHandleEventsIfHandlingInactiveButForced(
        string $eventClass,
        string $handlerClass,
        array $eventArguments,
        bool $eventHandled
    ): void {
        $this->extensionConfigurationMock
            ->expects(self::any())
            ->method('getMonitoringType')
            ->willReturn(2);

        /** @var DataUpdateEventInterface $event */
        $event = new $eventClass(...$eventArguments);
        $event->setForceImmediateProcessing(true);
        $this->checkEventHandling($event, $handlerClass, $eventHandled);
    }

    /**
     * Checks the event handling
     *
     * @param DataUpdateEventInterface $event
     * @param bool $eventHandled
     */
    protected function checkEventHandling(
        DataUpdateEventInterface $event,
        string $handlerClass,
        bool $eventHandled
    ): void {
        $handlerMock = $this->createMock($handlerClass);
        GeneralUtility::addInstance($handlerClass, $handlerMock);

        $dispatchedEvent = null;
        if ($eventHandled) {
            $this->eventDispatcherMock
                ->expects(self::once())
                ->method('dispatch')
                ->willReturnCallback(function () use (&$dispatchedEvent) {
                    $dispatchedEvent = func_get_arg(0);
                });
        } else {
            $this->eventDispatcherMock
                ->expects(self::never())
                ->method('dispatch');
        }

        $this->listener->__invoke($event);
        if ($eventHandled) {
            self::assertTrue($dispatchedEvent instanceof ProcessingFinishedEvent);
            self::assertEquals($dispatchedEvent->getDataUpdateEvent(), $event);
            self::assertTrue($dispatchedEvent->getDataUpdateEvent()->isPropagationStopped());
        }
    }

    /**
     * Data provider for canDispatchEvents
     *
     * @return array
     */
    public function canHandleEventsDataProvider(): array
    {
        return [
            [ContentElementDeletedEvent::class, DataUpdateHandler::class, [1], true],
            [VersionSwappedEvent::class, DataUpdateHandler::class, [1, 'pages'], true],
            [RecordMovedEvent::class, DataUpdateHandler::class, [1, 'pages'], true],
            [RecordUpdatedEvent::class, DataUpdateHandler::class, [1, 'pages'], true],
            [RecordDeletedEvent::class, GarbageHandler::class, [1, 'pages'], true],
            [PageMovedEvent::class, GarbageHandler::class, [1], true],
            [RecordGarbageCheckEvent::class, GarbageHandler::class, [1, 'pages', ['hidden'], false], true],
            ['SolrUnitTestsInvalidDataUpdateEvent', DataUpdateHandler::class, [1], false],
        ];
    }

    /**
     * Init listener
     *
     * @return AbstractBaseEventListener
     */
    protected function initListener(): AbstractBaseEventListener
    {
        return new ImmediateProcessingEventListener($this->extensionConfigurationMock, $this->eventDispatcherMock);
    }

    /**
     * Returns the current monitoring type
     *
     * @return int
     */
    protected function getMonitoringType(): int
    {
        return ImmediateProcessingEventListener::MONITORING_TYPE;
    }
}
