<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingQueuingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Event listener just queueing the data changes and stopping
 * the propagation
 */
class DelayedProcessingEventListener extends AbstractBaseEventListener
{
    public const MONITORING_TYPE = 1;

    /**
     * Queues the data update event for delayed processing and
     * stops propagation
     *
     * @param DataUpdateEventInterface $event
     */
    public function __invoke(DataUpdateEventInterface $event): void
    {
        if ($this->getMonitoringType() !== self::MONITORING_TYPE) {
            return;
        }

        $this->getEventQueueItemRepository()->addEventToQueue($event);
        $event->setStopProcessing(true);
        $this->dispatchEvent(DelayedProcessingQueuingFinishedEvent::class, $event);
    }

    /**
     * Return the EventQueueItemRepository
     *
     * @return EventQueueItemRepository
     */
    protected function getEventQueueItemRepository(): EventQueueItemRepository
    {
        return GeneralUtility::makeInstance(EventQueueItemRepository::class);
    }
}
