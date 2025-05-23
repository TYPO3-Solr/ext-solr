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

namespace ApacheSolrForTypo3\Solr\Task;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\EventListener\Events\DelayedProcessingFinishedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\DataUpdateEventInterface;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use Doctrine\DBAL\Exception as DBALException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * A worker processing the queued data update events
 */
final class EventQueueWorkerTask extends AbstractTask
{
    public const DEFAULT_PROCESSING_LIMIT = 100;

    /**
     * Processing limit, the number of events to process
     */
    protected int $limit = self::DEFAULT_PROCESSING_LIMIT;

    /**
     * Works through the indexing queue and indexes the queued items into Solr.
     *
     * @return bool Returns TRUE on success, FALSE if no items were indexed or none were found.
     *
     * @throws DBALException
     * @noinspection PhpMissingReturnTypeInspection See {@link AbstractTask::execute}
     */
    public function execute()
    {
        $this->processEvents();
        return true;
    }

    /**
     * Process queued data update events
     *
     * @throws DBALException
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
                    throw new InvalidArgumentException(
                        'Unsupported event found: '
                            . (is_object($event) ? get_class($event) : (string)$event),
                        1639747163,
                    );
                }

                $event->setForceImmediateProcessing(true);
                $dispatcher->dispatch($event);
                $processedItems[] = $queueItem['uid'];

                // dispatch event processing finished event
                $dispatcher->dispatch(
                    new DelayedProcessingFinishedEvent($event),
                );
            } catch (Throwable $e) {
                $this->getSolrLogManager()->error(
                    'Couldn\'t process queued event',
                    [
                        'eventQueueItemUid' => $queueItem['uid'],
                        'error' => $e->getMessage(),
                        'errorCode' => $e->getCode(),
                        'errorFile' => $e->getFile() . ':' . $e->getLine(),
                    ],
                );
                $itemRepository->updateEventQueueItem(
                    $queueItem['uid'],
                    [
                        'error' => 1,
                        'error_message' => $e->getMessage() . '[' . $e->getCode() . ']',
                    ],
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
     * @throws DBALException
     */
    public function getAdditionalInformation(): string
    {
        $message = LocalizationUtility::translate(
            'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.statusMsg',
        );

        $fullItemCount = $this->getEventQueueItemRepository()->count(false);
        $pendingItemsCount = $this->getEventQueueItemRepository()->count();
        return sprintf(
            $message,
            $pendingItemsCount,
            ($fullItemCount - $pendingItemsCount),
            $this->limit,
        );
    }

    /**
     * Sets the limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * Returns the limit
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Return the SolrLogManager
     */
    protected function getSolrLogManager(): SolrLogManager
    {
        return GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    /**
     * Return the EventQueueItemRepository
     */
    protected function getEventQueueItemRepository(): EventQueueItemRepository
    {
        return GeneralUtility::makeInstance(EventQueueItemRepository::class);
    }

    /**
     * Returns the EventDispatcher
     */
    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }
}
