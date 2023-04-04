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
