<?php

namespace ApacheSolrForTypo3\Solr\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2021 Markus Friedrich <markus.friedrich@dkd.de>
 *
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * A worker processing the queued data update events
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
final class EventQueueWorkerTask extends AbstractTask
{
    const DEFAULT_PROCESSING_LIMIT = 100;

    /**
     * Processing limit, the number of events to process
     *
     * @var int
     */
    protected $limit = self::DEFAULT_PROCESSING_LIMIT;

    /**
     * Works through the indexing queue and indexes the queued items into Solr.
     *
     * @return bool Returns TRUE on success, FALSE if no items were indexed or none were found.
     */
    public function execute(): bool
    {
        $this->processEvents();
        return true;
    }

    /**
     * Process queued data update events
     */
    protected function processEvents(): void
    {
        $itemRepository = $this->getEventQueueItemRepository();
        $dispatcher = $this->getEventDispatcher();

        $queueItems = $itemRepository->getEventQueueItems($this->limit);
        $processedItems = [];
        foreach ($queueItems as $queueItem) {
            try {
                $event = unserialize($queueItem['event']);
                if (!$event instanceof DataUpdateEventInterface) {
                    throw new \InvalidArgumentException(
                        'Unsupported event found: '
                            . (is_object($event) ? get_class($event) : (string)$event),
                        1639747163
                    );
                }

                $event->setForceImmediateProcessing(true);
                $dispatcher->dispatch($event);
                $processedItems[] = $queueItem['uid'];

                // dispatch event processing finished event
                $dispatcher->dispatch(
                    new DelayedProcessingFinishedEvent($event)
                );
            } catch (\Throwable $e) {
                $this->getSolrLogManager()->log(
                    SolrLogManager::ERROR,
                    'Couldn\'t process queued event',
                    [
                        'eventQueueItemUid' => $queueItem['uid'],
                        'error' => $e->getMessage(),
                        'errorCode' => $e->getCode(),
                        'errorFile' => $e->getFile() . ':' . $e->getLine(),
                    ]
                );
                $itemRepository->updateEventQueueItem(
                    $queueItem['uid'],
                    [
                        'error' => 1,
                        'error_message' => $e->getMessage() . '[' . $e->getCode() . ']',
                    ]
                );
            }
        }

        // update event queue
        $itemRepository->deleteEventQueueItems($processedItems);
    }

    /**
     * Returns some additional information about indexing progress, shown in
     * the scheduler's task overview list.
     *
     * @return string Information to display
     */
    public function getAdditionalInformation(): string
    {
        $message = LocalizationUtility::translate(
            'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.statusMsg'
        );

        $fullItemCount = $this->getEventQueueItemRepository()->count(false);
        $pendingItemsCount = $this->getEventQueueItemRepository()->count();
        return sprintf(
            $message,
            $pendingItemsCount,
            ($fullItemCount - $pendingItemsCount),
            $this->limit
        );
    }

    /**
     * Sets the limit
     *
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * Returns the limit
     *
     * @param int $limit
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Return the SolrLogManager
     *
     * @return SolrLogManager
     */
    protected function getSolrLogManager(): SolrLogManager
    {
        return  GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
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

    /**
     * Returns the EventDispatcher
     *
     * @return EventDispatcherInterface
     */
    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }
}
