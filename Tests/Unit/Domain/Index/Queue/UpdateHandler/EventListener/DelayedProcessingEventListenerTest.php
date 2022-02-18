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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\AbstractBaseEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\DelayedProcessingEventListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingQueuingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the DelayedProcessingEventListener
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class DelayedProcessingEventListenerTest extends AbstractEventListenerTest
{
    /**
     * @test
     */
    public function canHandleEvents(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getMonitoringType')
            ->willReturn(1);

        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');

        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        $eventQueueItemRepositoryMock
            ->expects(self::once())
            ->method('addEventToQueue')
            ->with($event);
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });

        $this->listener->__invoke($event);
        self::assertTrue($dispatchedEvent instanceof DelayedProcessingQueuingFinishedEvent);
        self::assertEquals($event, $dispatchedEvent->getDataUpdateEvent());
    }

    /**
     * @test
     */
    public function canSkipEventHandlingIfDisabled(): void
    {
        $this->extensionConfigurationMock
            ->expects(self::once())
            ->method('getMonitoringType')
            ->willReturn(2);

        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');

        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        $eventQueueItemRepositoryMock
            ->expects(self::never())
            ->method('addEventToQueue');
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $this->listener->__invoke($event);
    }

    /**
     * Init listener
     *
     * @return AbstractBaseEventListener
     */
    protected function initListener(): AbstractBaseEventListener
    {
        return new DelayedProcessingEventListener($this->extensionConfigurationMock, $this->eventDispatcherMock);
    }

    /**
     * Returns the current monitoring type
     *
     * @return int
     */
    protected function getMonitoringType(): int
    {
        return DelayedProcessingEventListener::MONITORING_TYPE;
    }
}
