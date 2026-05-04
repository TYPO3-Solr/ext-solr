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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\StrategyFactory;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

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
     */
    protected array $updateSubPagesRecursiveTriggerConfiguration = [
        // the current page has the field "extendToSubpages" enabled and the field "hidden" was set to '1'
        // covers following scenarios:
        //   'currentState' =>  ['hidden' => '0', 'extendToSubpages' => '0|1'], 'changeSet' => ['hidden' => '1', (optional)'extendToSubpages' => '1']
        'extendToSubpageEnabledAndHiddenFlagWasAdded' => [
            'currentState' =>  ['extendToSubpages' => '1'],
            'changeSet' => ['hidden' => '1'],
        ],
        // the current page has the field "hidden" enabled and the field "extendToSubpages" was set to '1'
        // covers following scenarios:
        //   'currentState' =>  ['hidden' => '0|1', 'extendToSubpages' => '0'], 'changeSet' => [(optional)'hidden' => '1', 'extendToSubpages' => '1']
        'hiddenIsEnabledAndExtendToSubPagesWasAdded' => [
            'currentState' =>  ['hidden' => '1'],
            'changeSet' => ['extendToSubpages' => '1'],
        ],
        // the field "no_search_sub_entries" of current page was set to 1
        'no_search_sub_entriesFlagWasAdded' => [
            'changeSet' => ['no_search_sub_entries' => '1'],
        ],
        // the current page has the field "extendToSubpages" enabled and the field "fe_group" was changed
        'extendToSubpageEnabledAndFeGroupWasChanged' => [
            'currentState' =>  ['extendToSubpages' => '1'],
            'changeSet' => ['fe_group' => '*'],
        ],
    ];

    /**
     * Tracks down index documents belonging to a particular record or page and
     * removes them from the index and the Index Queue.
     *
     * @throws UnexpectedValueException if a hook object does not implement interface {@linkt \ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor}
     */
    public function collectGarbage(string $table, int $uid): void
    {
        $garbageRemoverStrategy = StrategyFactory::getByTable($table);
        $garbageRemoverStrategy->removeGarbageOf($table, $uid);
    }

    /**
     * Handles moved pages
     *
     * As rootline and page slug might have changed on page movement,
     * document have to be removed from Solr. Reindexing is taken
     * care of by the DataUpdateHandler.
     *
     * @param int $uid
     * @param int|null $previousParentId
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function handlePageMovement(int $uid, ?int $previousParentId = null): void
    {
        $this->collectGarbage('pages', $uid);

        // collect garbage of subpages
        if ($previousParentId !== null) {
            $pageRecord = BackendUtility::getRecord('pages', $uid);
            if ($pageRecord !== null && (int)$pageRecord['pid'] !== $previousParentId) {
                $subPageIds = $this->getSubPageIds($uid);
                array_walk(
                    $subPageIds,
                    fn(int $subPageId) => $this->collectGarbage('pages', $subPageId),
                );
            }
        }
    }

    /**
     * Performs record garbage check
     *
     * @throws DBALException
     * @throws AspectNotFoundException
     */
    public function performRecordGarbageCheck(
        int $uid,
        string $table,
        array $updatedFields,
        bool $frontendGroupsRemoved,
    ): void {
        $record = $this->getRecordWithFieldRelevantForGarbageCollection($table, $uid);

        // If no record could be found, remove remains from index and queue
        if (empty($record)) {
            $this->collectGarbage($table, $uid);
            return;
        }

        if ($table === 'pages') {
            // We need to get the full record to find out if this is a page translation
            $fullRecord = $this->getRecord('pages', $uid);
            $uidForRecursiveTriggers = $uid;
            if (($fullRecord['sys_language_uid'] ?? null) > 0 && (int)($fullRecord['l10n_parent']) > 0) {
                $uidForRecursiveTriggers = (int)$fullRecord['l10n_parent'];
            }
            $this->deleteSubEntriesWhenRecursiveTriggerIsRecognized($table, $uidForRecursiveTriggers, $updatedFields);
        }

        $record = $this->tcaService->normalizeFrontendGroupField($table, $record);
        $isGarbage = $this->getIsGarbageRecord($table, $record, $frontendGroupsRemoved);
        if (!$isGarbage) {
            return;
        }

        $this->collectGarbage($table, $uid);
    }

    /**
     * Deletes sub-entries if recursive trigger is recognized
     *
     * @throws DBALException
     */
    protected function deleteSubEntriesWhenRecursiveTriggerIsRecognized(
        string $table,
        int $uid,
        array $updatedFields,
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
     * @throws AspectNotFoundException
     * @throws DBALException
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
     * @throws DBALException
     */
    protected function isIndexablePageType(array $record): bool
    {
        try {
            $isAllowedPageType = $this->frontendEnvironment->isAllowedPageType($record);
        } catch (SiteNotFoundException | InvalidArgumentException $e) {
            $this->logger->log(
                LogLevel::WARNING,
                'Couldn\t determine site for page ' . $record['uid'],
                [
                    'pageUid' => $record['uid'],
                    'error' => [
                        'code' => $e->getCode(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'message' => $e->getMessage(),
                    ],
                ],
            );

            $isAllowedPageType = false;
        }

        return $isAllowedPageType;
    }

    /**
     * Checks whether the page has been excluded from searching.
     */
    protected function isPageExcludedFromSearch(array $record): bool
    {
        return (bool)$record['no_search'];
    }

    /**
     * Check if a record is getting invisible due to changes in start or endtime. In addition, it is checked that the related
     * queue item was marked as indexed.
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     */
    protected function isInvisibleByStartOrEndtime(string $table, array $record): bool
    {
        return
            ($this->tcaService->isStartTimeInFuture($table, $record)
                || $this->tcaService->isEndTimeInPast($table, $record))
            && $this->isRelatedQueueRecordMarkedAsIndexed($table, $record)
        ;
    }

    /**
     * Checks if the related index queue item is indexed.
     *
     * * For tt_content the page from the pid is checked
     * * For all other records the table it's self is checked
     *
     * @throws DBALException
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
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \Doctrine\DBAL\ParameterType::INTEGER)))
                ->executeQuery()
                ->fetchAssociative();
        } catch (Throwable) {
            $row = false;
        }

        return is_array($row) ? $row : null;
    }
}
