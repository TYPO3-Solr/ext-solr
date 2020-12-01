<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\StrategyFactory;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Garbage Collector, removes related documents from the index when a record is
 * set to hidden, is deleted or is otherwise made invisible to website visitors.
 *
 * Garbage collection will happen for online/LIVE workspaces only.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class GarbageCollector extends AbstractDataHandlerListener implements SingletonInterface
{
    /**
     * @var array
     */
    protected $trackedRecords = [];

    /**
     * @var TCAService
     */
    protected $tcaService;

    /**
     * GarbageCollector constructor.
     * @param TCAService|null $TCAService
     */
    public function __construct(TCAService $TCAService = null)
    {
        parent::__construct();
        $this->tcaService = $TCAService ?? GeneralUtility::makeInstance(TCAService::class);
    }

    /**
     * Hooks into TCE main and tracks record deletion commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     * @return void
     */
    public function processCmdmap_preProcess($command, $table, $uid, $value, DataHandler $tceMain)
    {
        // workspaces: collect garbage only for LIVE workspace
        if ($command === 'delete' && $GLOBALS['BE_USER']->workspace == 0) {
            $this->collectGarbage($table, $uid);

            if ($table === 'pages') {
                $this->getIndexQueue()->deleteItem($table, $uid);
            }
        }
    }

    /**
     * Holds the configuration when a recursive page queing should be triggered.
     *
     * Note: The SQL transaction is already committed, so the current state covers only "non"-changed fields.
     *
     * @var array
     * @return array
     */
    protected function getUpdateSubPagesRecursiveTriggerConfiguration()
    {
        return [
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
            ]
        ];
    }

    /**
     * Tracks down index documents belonging to a particular record or page and
     * removes them from the index and the Index Queue.
     *
     * @param string $table The record's table name.
     * @param int $uid The record's uid.
     * @throws \UnexpectedValueException if a hook object does not implement interface \ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor
     */
    public function collectGarbage($table, $uid)
    {
        $garbageRemoverStrategy = StrategyFactory::getByTable($table);
        $garbageRemoverStrategy->removeGarbageOf($table, $uid);
    }

    /**
     * @param string $table
     * @param int $uid
     * @param array $changedFields
     */
    protected function deleteSubpagesWhenExtendToSubpagesIsSet($table, $uid, $changedFields)
    {
        if (!$this->isRecursivePageUpdateRequired($uid, $changedFields)) {
            return;
        }

        // get affected subpages when "extendToSubpages" flag was set
        $pagesToDelete = $this->getSubPageIds($uid);
        // we need to at least remove this page
        foreach ($pagesToDelete as $pageToDelete) {
            $this->collectGarbage($table, $pageToDelete);
        }
    }

    // methods checking whether to trigger garbage collection

    /**
     * Hooks into TCE main and tracks page move commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processCmdmap_postProcess($command, $table, $uid, $value, DataHandler $tceMain) {
        // workspaces: collect garbage only for LIVE workspace
        if ($command === 'move' && $table === 'pages' && $GLOBALS['BE_USER']->workspace == 0) {
            // TODO the below comment is not valid anymore, pid has been removed from doc ID
            // ...still needed?

            // must be removed from index since the pid changes and
            // is part of the Solr document ID
            $this->collectGarbage($table, $uid);

            // now re-index with new properties
            $this->getIndexQueue()->updateItem($table, $uid);
        }
    }

    /**
     * Hooks into TCE main and tracks changed records. In this case the current
     * record's values are stored to do a change comparison later on for fields
     * like fe_group.
     *
     * @param array $incomingFields An array of incoming fields, new or changed, not used
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_preProcessFieldArray($incomingFields, $table, $uid, DataHandler $tceMain)
    {
        if (!is_int($uid)) {
            // a newly created record, skip
            return;
        }

        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: collect garbage only for LIVE workspace
            return;
        }

        $hasConfiguredEnableColumnForFeGroup = $this->tcaService->isEnableColumn($table, 'fe_group');
        if (!$hasConfiguredEnableColumnForFeGroup) {
            return;
        }

        $visibilityAffectingFields = $this->tcaService->getVisibilityAffectingFieldsByTable($table);
        $record = (array)BackendUtility::getRecord($table, $uid, $visibilityAffectingFields, '', false);
        // If no record could be found skip further processing
        if (empty($record)) {
            return;
        }

        $record = $this->tcaService->normalizeFrontendGroupField($table, $record);

        // keep previous state of important fields for later comparison
        $this->trackedRecords[$table][$uid] = $record;
    }

    /**
     * Hooks into TCE Main and watches all record updates. If a change is
     * detected that would remove the record from the website, we try to find
     * related documents and remove them from the index.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data, not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, DataHandler $tceMain)
    {
        if ($status === 'new') {
            // a newly created record, skip
            return;
        }

        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: collect garbage only for LIVE workspace
            return;
        }

        $record = $this->getRecordWithFieldRelevantForGarbageCollection($table, $uid);

        // If no record could be found skip further processing
        if (empty($record)) {
            return;
        }

        $record = $this->tcaService->normalizeFrontendGroupField($table, $record);
        $isGarbage = $this->getIsGarbageRecord($table, $record);
        if (!$isGarbage) {
            return;
        }

        $this->collectGarbage($table, $uid);

        if ($table === 'pages') {
            $this->deleteSubpagesWhenExtendToSubpagesIsSet($table, $uid, $fields);
        }
    }

    /**
     * Check if a record is getting invisible due to changes in start or endtime. In addition it is checked that the related
     * queue item was marked as indexed.
     *
     * @param string $table
     * @param array $record
     * @return bool
     */
    protected function isInvisibleByStartOrEndtime($table, $record)
    {
        return (
            ($this->tcaService->isStartTimeInFuture($table, $record) || $this->tcaService->isEndTimeInPast($table, $record)) &&
            $this->isRelatedQueueRecordMarkedAsIndexed($table, $record)
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
    protected function isRelatedQueueRecordMarkedAsIndexed($table, $record)
    {
        if ($table === 'tt_content') {
            $table = 'pages';
            $uid = $record['pid'];
        } else {
            $uid = $record['uid'];
        }

        return $this->getIndexQueue()->containsIndexedItem($table, $uid);
    }

    /**
     * @return Queue
     */
    private function getIndexQueue()
    {
        return GeneralUtility::makeInstance(Queue::class);
    }

    /**
     * Checks whether the a frontend group field exists for the record and if so
     * whether groups have been removed from accessing the record thus making
     * the record invisible to at least some people.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return bool TRUE if frontend groups have been removed from access to the record, FALSE otherwise.
     */
    protected function hasFrontendGroupsRemoved($table, $record)
    {
        if (!isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'])) {
            return false;
        }

        $frontendGroupsField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];

        $previousGroups = explode(',', (string)$this->trackedRecords[$table][$record['uid']][$frontendGroupsField]);
        $currentGroups = explode(',', (string)$record[$frontendGroupsField]);
        $removedGroups = array_diff($previousGroups, $currentGroups);

        return (boolean)count($removedGroups);
    }

    /**
     * Checks whether the page has been excluded from searching.
     *
     * @param array $record An array with record fields that may affect visibility.
     * @return bool True if the page has been excluded from searching, FALSE otherwise
     */
    protected function isPageExcludedFromSearch($record)
    {
        return (boolean)$record['no_search'];
    }

    /**
     * Checks whether a page has a page type that can be indexed.
     * Currently standard pages and mount pages can be indexed.
     *
     * @param array $record A page record
     * @return bool TRUE if the page can be indexed according to its page type, FALSE otherwise
     */
    protected function isIndexablePageType(array $record)
    {
        return $this->frontendEnvironment->isAllowedPageType($record);
    }

    /**
     * Determines if a record is garbage and can be deleted.
     *
     * @param string $table
     * @param array $record
     * @return bool
     */
    protected function getIsGarbageRecord($table, $record):bool
    {
        return $this->tcaService->isHidden($table, $record) ||
                $this->isInvisibleByStartOrEndtime($table, $record) ||
                $this->hasFrontendGroupsRemoved($table, $record) ||
                ($table === 'pages' && $this->isPageExcludedFromSearch($record)) ||
                ($table === 'pages' && !$this->isIndexablePageType($record));
    }

    /**
     * Returns a record with all visibility affecting fields.
     *
     * @param string $table
     * @param int $uid
     * @return array
     */
    protected function getRecordWithFieldRelevantForGarbageCollection($table, $uid):array
    {
        $garbageCollectionRelevantFields = $this->tcaService->getVisibilityAffectingFieldsByTable($table);
        $record = (array)BackendUtility::getRecord($table, $uid, $garbageCollectionRelevantFields, '', false);
        return $record;
    }
}
