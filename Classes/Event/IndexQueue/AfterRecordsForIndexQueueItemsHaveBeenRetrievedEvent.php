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

namespace ApacheSolrForTypo3\Solr\Event\IndexQueue;

/**
 * PSR-14 Event which is fired after DB records have been fetched for the index queue.
 *
 * Previously used via $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessFetchRecordsForIndexQueueItem']
 */
final class AfterRecordsForIndexQueueItemsHaveBeenRetrievedEvent
{
    public function __construct(
        private readonly string $table,
        private readonly array $uids,
        private array $records
    ) {}

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRecordUids(): array
    {
        return $this->uids;
    }

    public function getRecords(): array
    {
        return $this->records;
    }

    public function setRecords(array $records): void
    {
        $this->records = $records;
    }
}
