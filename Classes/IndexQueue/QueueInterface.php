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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatistic;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;

/**
 * This interface defines which methods are required by a index queue.
 * It will allow use different queues in future
  */
interface QueueInterface
{
    /**
     * Returns the timestamp of the last indexing run.
     *
     * @param int $rootPageId The root page uid for which to get
     *      the last indexed item id
     * @return int Timestamp of last index run.
     */
    public function getLastIndexTime(int $rootPageId): int;

    /**
     * Returns the uid of the last indexed item in the queue
     *
     * @param int $rootPageId The root page uid for which to get
     *      the last indexed item id
     * @return int The last indexed item's ID.
     */
    public function getLastIndexedItemId(int $rootPageId): int;

    /**
     * Marks an item as needing (re)indexing.
     *
     * Like with Solr itself, there's no add method, just a simple update method
     * that handles the adds, too.
     *
     * The method creates or updates the index queue items for all related rootPageIds.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a different value for non-database-record types.
     * @param int $forcedChangeTime The change time for the item if set, otherwise value from getItemChangedTime() is used.
     * @param array|null $validLanguageUids List of valid language uids, others will be ignored. Depends on your queue implementation, may be irrelevant
     * @return int Number of updated/created items
     */
    public function updateItem(string $itemType, $itemUid, int $forcedChangeTime = 0, ?array $validLanguageUids = null): int;

    /**
     * Finds indexing errors for the current site
     *
     * @param Site $site
     * @return array Error items for the current site's Index Queue
     */
    public function getErrorsBySite(Site $site): array;

    /**
     * Resets all the errors for all index queue items.
     *
     * @return int affected rows
     */
    public function resetAllErrors(): int;

    /**
     * Resets the errors in the index queue for a specific site
     *
     * @param Site $site
     * @return int affected rows
     */
    public function resetErrorsBySite(Site $site): int;

    /**
     * Resets the error in the index queue for a specific item
     *
     * @param ItemInterface $item
     * @return int affected rows
     */
    public function resetErrorByItem(ItemInterface $item): int;

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *                   different value for non-database-record types.
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     */
    public function containsItem(string $itemType, $itemUid): bool;

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *                   different value for non-database-record types.
     * @param int $rootPageId
     * @param string $indexingConfiguration
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     */
    public function containsItemWithRootPageId(string $itemType, $itemUid, int $rootPageId, string $indexingConfiguration): bool;

    /**
     * Checks whether the Index Queue contains a specific item that has been
     * marked as indexed.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int|string $itemUid The item's uid, usually an integer uid, could be a
     *               different value for non-database-record types.
     * @return bool TRUE if the item is found in the queue and marked as
     *              indexed, FALSE otherwise
     */
    public function containsIndexedItem(string $itemType, $itemUid): bool;

    /**
     * Removes an item from the Index Queue.
     *
     * @param string $itemType The type of the item to remove, usually a table name.
     * @param int|string $itemUid The uid of the item to remove, usually an integer uid, could be a
     *                   different value for non-database-record types.
     */
    public function deleteItem(string $itemType, $itemUid): void;

    /**
     * Removes all items of a certain type from the Index Queue.
     *
     * @param string $itemType The type of items to remove, usually a table name.
     */
    public function deleteItemsByType(string $itemType): void;

    /**
     * Removes all items of a certain site from the Index Queue. Accepts an
     * optional parameter to limit the deleted items by indexing configuration.
     *
     * @param Site $site The site to remove items for.
     * @param string $indexingConfigurationName Name of a specific indexing
     *      configuration
     */
    public function deleteItemsBySite(Site $site, string $indexingConfigurationName = ''): void;

    /**
     * Removes all items from the Index Queue.
     */
    public function deleteAllItems(): void;

    /**
     * Gets a single Index Queue item by its uid.
     *
     * @param int $itemId Index Queue item uid
     * @return ?ItemInterface The request Index Queue item or NULL if no item with $itemId was found
     */
    public function getItem(int $itemId): ?ItemInterface;

    /**
     * Gets Index Queue items by type and uid.
     *
     * @param string $itemType item type, usually  the table name
     * @param int|string $itemUid item uid
     * @return ItemInterface[] An array of items matching $itemType and $itemUid
     */
    public function getItems(string $itemType, $itemUid): array;

    /**
     * Returns all items in the queue.
     *
     * @return ItemInterface[] An array of items
     */
    public function getAllItems(): array;

    /**
     * Returns the number of items for all queues.
     *
     * @return int
     */
    public function getAllItemsCount(): int;

    /**
     * Extracts the number of pending, indexed and erroneous items from the
     * Index Queue.
     *
     * @param Site $site
     * @param string $indexingConfigurationName
     *
     * @return QueueStatistic
     */
    public function getStatisticsBySite(Site $site, string $indexingConfigurationName = ''): QueueStatistic;

    /**
     * Gets $limit number of items to index for a particular $site.
     *
     * @param Site $site TYPO3 site
     * @param int $limit Number of items to get from the queue
     * @return ItemInterface[] Items to index to the given solr server
     */
    public function getItemsToIndex(Site $site, int $limit = 50): array;

    /**
     * Marks an item as failed and causes the indexer to skip the item in the
     * next run.
     *
     * @param ItemInterface $item Either the item's Index Queue uid or the complete item
     * @param string $errorMessage Error message
     */
    public function markItemAsFailed(ItemInterface $item, string $errorMessage = '');

    /**
     * Sets the timestamp of when an item last has been indexed.
     *
     * @param ItemInterface $item
     * @return int affected rows
     */
    public function updateIndexTimeByItem(ItemInterface $item): int;

    /**
     * Sets the change timestamp of an item.
     *
     * @param ItemInterface $item
     * @param int $forcedChangeTime The change time for the item
     * @return int affected rows
     */
    public function setForcedChangeTimeByItem(ItemInterface $item, int $forcedChangeTime): int;
}
