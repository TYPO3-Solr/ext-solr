<?php

declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2021 Markus Friedrich <markus.friedrich@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\ProcessingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\PageMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\VersionSwappedEvent;

/**
 * Event listener for immediate processing of
 * record updates
 */
class ImmediateProcessingEventListener extends AbstractBaseEventListener
{
    public const MONITORING_TYPE = 0;

    /**
     * Handles the data update events
     *
     * @param DataUpdateEventInterface $event
     */
    public function __invoke(DataUpdateEventInterface $event): void
    {
        if (!$event->isImmediateProcessingForced() && !$this->isProcessingEnabled()) {
            return;
        }

        $methodName = $this->getMethodNameByEvent($event);
        if (method_exists($this, $methodName)) {
            $this->{$methodName}($event);
            $event->setStopProcessing(true);
            $this->dispatchEvent(ProcessingFinishedEvent::class, $event);
        }
    }

    /**
     * Determines the right method by event name
     *
     * @param DataUpdateEventInterface $event
     */
    protected function getMethodNameByEvent(DataUpdateEventInterface $event): string
    {
        $eventClassName = get_class($event);
        $methodName = substr($eventClassName, (int)strrpos($eventClassName, '\\') + 1);
        return 'handle' . $methodName;
    }

    /**
     * Handles the deletion of a content element
     *
     * @param ContentElementDeletedEvent $event
     */
    protected function handleContentElementDeletedEvent(ContentElementDeletedEvent $event): void
    {
        $this->getDataUpdateHandler()->handleContentElementDeletion($event->getUid());
    }

    /**
     * Handles a version swap
     *
     * @param VersionSwappedEvent $event
     */
    protected function handleVersionSwappedEvent(VersionSwappedEvent $event): void
    {
        $this->getDataUpdateHandler()->handleVersionSwap($event->getUid(), $event->getTable());
    }

    /**
     * Handles moved records, including pages
     *
     * @param RecordMovedEvent $event
     */
    protected function handleRecordMovedEvent(RecordMovedEvent $event): void
    {
        if ($event->isPageUpdate()) {
            $this->getDataUpdateHandler()->handleMovedPage($event->getUid());
        } else {
            $this->getDataUpdateHandler()->handleMovedRecord($event->getUid(), $event->getTable());
        }
    }

    /**
     * Handles record updates
     *
     * @param RecordUpdatedEvent $event
     */
    protected function handleRecordUpdatedEvent(RecordUpdatedEvent $event): void
    {
        if ($event->isContentElementUpdate()) {
            $this->getDataUpdateHandler()->handleContentElementUpdate($event->getUid(), $event->getFields());
        } elseif ($event->isPageUpdate()) {
            $this->getDataUpdateHandler()->handlePageUpdate($event->getUid(), $event->getFields());
        } else {
            $this->getDataUpdateHandler()->handleRecordUpdate($event->getUid(), $event->getTable());
        }
    }

    /**
     * Handles record deletion
     *
     * @param RecordDeletedEvent $event
     */
    protected function handleRecordDeletedEvent(RecordDeletedEvent $event): void
    {
        $this->getGarbageHandler()->collectGarbage($event->getTable(), $event->getUid());
    }

    /**
     * Handles a page movement
     *
     * @param PageMovedEvent $event
     */
    protected function handlePageMovedEvent(PageMovedEvent $event): void
    {
        $this->getGarbageHandler()->handlePageMovement($event->getUid());
    }

    /**
     * Performs garbage checks
     *
     * @param RecordGarbageCheckEvent $event
     */
    protected function handleRecordGarbageCheckEvent(RecordGarbageCheckEvent $event): void
    {
        $this->getGarbageHandler()->performRecordGarbageCheck(
            $event->getUid(),
            $event->getTable(),
            $event->getFields(),
            $event->frontendGroupsRemoved()
        );
    }
}
