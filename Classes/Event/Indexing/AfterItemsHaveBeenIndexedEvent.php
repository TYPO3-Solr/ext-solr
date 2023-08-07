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

namespace ApacheSolrForTypo3\Solr\Event\Indexing;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Task\IndexQueueWorkerTask;

/**
 * This event is dispatched after the indexing of items ends
 */
final class AfterItemsHaveBeenIndexedEvent
{
    /**
     * @var array<Item>
     */
    private array $items;

    private ?IndexQueueWorkerTask $task;

    private string $runId;

    public function __construct(array $items, ?IndexQueueWorkerTask $task, string $runId)
    {
        $this->items = $items;
        $this->task = $task;
        $this->runId = $runId;
    }

    /**
     * @return array<Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param array<Item> $items
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getTask(): ?IndexQueueWorkerTask
    {
        return $this->task;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }
}
