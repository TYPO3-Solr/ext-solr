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
 * Event which is fired after an index queue item has been updated.
 *
 * Previously available as:
 * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem']
 */
final class AfterIndexQueueItemHasBeenMarkedForReindexingEvent
{
    public function __construct(
        private readonly string $itemType,
        private readonly int $itemUid,
        private readonly int $forcedChangeTime,
        private int $updateCount
    ) {
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function getItemUid(): int
    {
        return $this->itemUid;
    }

    public function getForcedChangeTime(): int
    {
        return $this->forcedChangeTime;
    }

    public function getUpdateCount(): int
    {
        return $this->updateCount;
    }

    public function setUpdateCount(int $updateCount): void
    {
        $this->updateCount = $updateCount;
    }
}
