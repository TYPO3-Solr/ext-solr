<?php

declare(strict_types = 1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler;

use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\StrategyFactory;

/**
 * Garbage handler
 *
 * Handles updates on potential relevant records and
 * collects the garbage, e.g. a deletion might require
 * index and index queue updates.
 */
class GarbageHandler extends AbstractUpdateHandler
{
    /**
     * Configuration used to check if recursive updates are required
     *
     * Holds the configuration when a recursive page queuing should be triggered, while processing record
     * updates
     *
     * Note: The SQL transaction is already committed, so the current state covers only "non"-changed fields.
     *
     * @var array
     */
    protected $updateSubPagesRecursiveTriggerConfiguration = [
        // the current page has the field "extendToSubpages" enabled and the field "hidden" was set to 1
        // covers following scenarios:
        //   'currentState' =>  ['hidden' => '0', 'extendToSubpages' => '0|1'], 'changeSet' => ['hidden' => '1', (optional)'extendToSubpages' => '1']
        'extendToSubpageEnabledAndHiddenFlagWasAdded' => [
            'currentState' =>  ['extendToSubpages' => '1'],
            'changeSet' => ['hidden' => '1']
        ],
        // the current page has the field "hidden" enabled and the field "extendToSubpages" was set to 1
        // covers following scenarios:
        //   'currentState' =>  ['hidden' => '0|1', 'extendToSubpages' => '0'], 'changeSet' => [(optional)'hidden' => '1', 'extendToSubpages' => '1']
        'hiddenIsEnabledAndExtendToSubPagesWasAdded' => [
            'currentState' =>  ['hidden' => '1'],
            'changeSet' => ['extendToSubpages' => '1']
        ],
        // the field "no_search_sub_entries" of current page was set to 1
        'no_search_sub_entriesFlagWasAdded' => [
            'changeSet' => ['no_search_sub_entries' => '1']
        ],
    ];

    /**
     * Tracks down index documents belonging to a particular record or page and
     * removes them from the index and the Index Queue.
     *
     * @param string $table The record's table name.
     * @param int $uid The record's uid.
     * @throws \UnexpectedValueException if a hook object does not implement interface
     *                                   \ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor
     */
    public function collectGarbage($table, $uid): void
    {
        $garbageRemoverStrategy = StrategyFactory::getByTable($table);
        $garbageRemoverStrategy->removeGarbageOf($table, $uid);
    }

    /**
     * Handles moved pages
     *
     * @param int $uid
     */
    public function handlePageMovement(int $uid): void
    {
        // TODO the below comment is not valid anymore, pid has been removed from doc ID
        // ...still needed?

        // must be removed from index since the pid changes and
        // is part of the Solr document ID
        $this->collectGarbage('pages', $uid);

        // now re-index with new properties
        $this->indexQueue->updateItem('pages', $uid);
    }

    /**
     * Performs a record garbage check
     *
     * @param int $uid
     * @param string $table
     * @param array $updatedFields
     * @param bool $frontendGroupsRemoved
     */
    public function performRecordGarbageCheck(
        int $uid,
        string $table,
        array $updatedFields,
        bool $frontendGroupsRemoved
    ): void {
        $record = $this->getRecordWithFieldRelevantForGarbageCollection($table, $uid);

        // If no record could be found skip further processing
        if (empty($record)) {
            return;
        }

        if ($table === 'pages') {
            $this->deleteSubEntriesWhenRecursiveTriggerIsRecognized($table, $uid, $updatedFields);
        }

        $record = $this->tcaService->normalizeFrontendGroupField($table, $record);
        $isGarbage = $this->getIsGarbageRecord($table, $record, $frontendGroupsRemoved);
        if (!$isGarbage) {
            return;
        }

        $this->collectGarbage($table, $uid);
    }

    /**
     * @param string $table
     * @param int $uid
     * @param array $updatedFields
     */
    protected function deleteSubEntriesWhenRecursiveTriggerIsRecognized(
        string $table,
        int $uid,
        array $updatedFields
    ): void {
        if (!$this->isRecursivePageUpdateRequired($uid, $updatedFields)) {
            return;
        }

        // get affected subpages when "extendToSubpages" flag was set
        $pagesToDelete = $this->getSubPageIds($uid);
        // we need to at least remove this page
        foreach ($pagesToDelete as $pageToDelete) {
            $this->collectGarbage($table, $pageToDelete);
        }
    }

    /**
     * Determines if a record is garbage and can be deleted.
     *
     * @param string $table
     * @param array $record
     * @param bool $frontendGroupsRemoved
     * @return bool
     * @throws DBALDriverException
     */
    protected function getIsGarbageRecord(string $table, array $record, bool $frontendGroupsRemoved): bool
    {
        return $frontendGroupsRemoved
            || $this->tcaService->isHidden($table, $record)
            || $this->isInvisibleByStartOrEndtime($table, $record)
            || ($table === 'pages' && $this->isPageExcludedFromSearch($record))
            || ($table === 'pages' && !$this->isIndexablePageType($record));
    }

    /**
     * Checks whether a page has a page type that can be indexed.
     * Currently, standard pages and mount pages can be indexed.
     *
     * @param array $record A page record
     * @return bool TRUE if the page can be indexed according to its page type, FALSE otherwise
     * @throws DBALDriverException
     */
    protected function isIndexablePageType(array $record): bool
    {
        return $this->frontendEnvironment->isAllowedPageType($record);
    }

    /**
     * Checks whether the page has been excluded from searching.
     *
     * @param array $record An array with record fields that may affect visibility.
     * @return bool True if the page has been excluded from searching, FALSE otherwise
     */
    protected function isPageExcludedFromSearch(array $record): bool
    {
        return (bool)$record['no_search'];
    }


    /**
     * Check if a record is getting invisible due to changes in start or endtime. In addition it is checked that the related
     * queue item was marked as indexed.
     *
     * @param string $table
     * @param array $record
     * @return bool
     */
    protected function isInvisibleByStartOrEndtime(string $table, array $record): bool
    {
        return (
            ($this->tcaService->isStartTimeInFuture($table, $record)
                || $this->tcaService->isEndTimeInPast($table, $record))
            && $this->isRelatedQueueRecordMarkedAsIndexed($table, $record)
        );
    }

    /**
     * Checks if the related index queue item is indexed.
     *
     * * For tt_content the page from the pid is checked
     * * For all other records the table it's self is checked
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return bool True if the record is marked as being indexed
     */
    protected function isRelatedQueueRecordMarkedAsIndexed(string $table, array $record): bool
    {
        if ($table === 'tt_content') {
            $table = 'pages';
            $uid = $record['pid'];
        } else {
            $uid = $record['uid'];
        }

        return $this->indexQueue->containsIndexedItem($table, $uid);
    }

    /**
     * Returns a record with all visibility affecting fields.
     *
     * @param string $table
     * @param int $uid
     * @return array|null
     */
    public function getRecordWithFieldRelevantForGarbageCollection(string $table, int $uid): ?array
    {
        $garbageCollectionRelevantFields = $this->tcaService->getVisibilityAffectingFieldsByTable($table);
        try {
            $queryBuilder = $this->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            $row = $queryBuilder
                ->select(...GeneralUtility::trimExplode(',', $garbageCollectionRelevantFields, true))
                ->from($table)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)))
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable $e) {
            $row = false;
        }

        return is_array($row) ? $row : null;
    }
}
