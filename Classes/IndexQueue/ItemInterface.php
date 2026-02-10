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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;

/**
 * The presentation of an index queue item
 */
interface ItemInterface
{
    public const STATE_BLOCKED = -1;
    public const STATE_PENDING = 0;
    public const STATE_INDEXED = 1;

    /**
     * Returns the uid of the index queue entry itself
     */
    public function getIndexQueueUid(): int;

    /**
     * Returns the root page uid of the item
     */
    public function getRootPageUid(): int;

    /**
     * Returns the error message
     *
     * @return string
     */
    public function getErrors(): string;

    /**
     * Returns if the indexing of this item leads to an error.
     *
     * @return bool
     */
    public function getHasErrors(): bool;

    /**
     * Gets the site the item belongs to.
     *
     * @return Site|null
     */
    public function getSite(): ?Site;

    /**
     * Items state: pending, indexed, blocked
     *
     * @return int
     */
    public function getState(): int;

    /**
     * Returns the item type i.e. pages
     */
    public function getType(): string;

    /**
     * Returns the index configuration or calculate it based on the item provider
     *
     * @return string
     */
    public function getIndexingConfigurationName(): string;

    /**
     * Returns the timestamp of last changed
     */
    public function getChanged(): int;

    /**
     * Returns the timestamp of last indexing
     */
    public function getIndexed(): int;

    /**
     * Returns the uid of related record (item_uid).
     *
     * @return string|int The uid of the item record, usually an integer uid, could be a
     *                    different value for non-database-record types.
     */
    public function getRecordUid();

    /**
     * Returns the index priority.
     *
     * @return int
     */
    public function getIndexPriority(): int;
}
