<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler\EventListener;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Markus Friedrich <markus.friedrich@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\DelayedProcessingEventListener;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingQueuingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\AbstractBaseEventListener;

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
    public function canHandleEvents(): void {
        $this->extensionConfigurationMock
            ->expects($this->once())
            ->method('getMonitoringType')
            ->willReturn(1);

        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');

        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        $eventQueueItemRepositoryMock
            ->expects($this->once())
            ->method('addEventToQueue')
            ->with($event);
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));

        $this->listener->__invoke($event);
        $this->assertTrue($dispatchedEvent instanceof DelayedProcessingQueuingFinishedEvent);
        $this->assertEquals($event, $dispatchedEvent->getDataUpdateEvent());
    }

    /**
     * @test
     */
    public function canSkipEventHandlingIfDisabled(): void {
        $this->extensionConfigurationMock
            ->expects($this->once())
            ->method('getMonitoringType')
            ->willReturn(2);

        $event = new RecordUpdatedEvent(123, 'tx_foo_bar');

        $eventQueueItemRepositoryMock = $this->createMock(EventQueueItemRepository::class);
        $eventQueueItemRepositoryMock
            ->expects($this->never())
            ->method('addEventToQueue');
        GeneralUtility::setSingletonInstance(EventQueueItemRepository::class, $eventQueueItemRepositoryMock);

        $this->eventDispatcherMock
            ->expects($this->never())
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
