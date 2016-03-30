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
 *  the Free Software Foundation; either version 2 of the License, or
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

    protected $trackedRecords = array();

    /**
     * Hooks into TCE main and tracks record deletion commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param integer $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     * @return void
     */
    public function processCmdmap_preProcess(
        $command,
        $table,
        $uid,
        $value,
        DataHandler $tceMain
    ) {
        // workspaces: collect garbage only for LIVE workspace
        if ($command == 'delete' && $GLOBALS['BE_USER']->workspace == 0) {
            $this->collectGarbage($table, $uid);

            if ($table == 'pages') {
                $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
                $indexQueue->deleteItem($table, $uid);
            }
        }
    }

    /**
     * Holds the configuration when a recursive page queing should be triggered.
     *
     * @var array
     */
    protected function getUpdateSubPagesRecursiveTriggerConfiguration()
    {
        return array(
            // the current page has the field "extendToSubpages" enabled and the field "hidden" was set to 1
            'extendToSubpageEnabledAndHiddenFlagWasAdded' => array(
                'currentState' =>  array('extendToSubpages' => "1"),
                'changeSet' => array('hidden' => "1")
            ),
            // the current page has the field "hidden" enabled and the field "extendToSubpages" was set to 1
            'hiddenIsEnabledAndExtendToSubPagesWasAdded' => array(
                'currentState' =>  array('hidden' => "1"),
                'changeSet' => array('extendToSubpages' => "1")
            )
        );
    }

    /**
     * Tracks down index documents belonging to a particular record or page and
     * removes them from the index and the Index Queue.
     *
     * @param string $table The record's table name.
     * @param integer $uid The record's uid.
     * @throws \UnexpectedValueException if a hook object does not implement interface \ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor
     */
    public function collectGarbage($table, $uid)
    {
        if ($table == 'tt_content' || $table == 'pages' || $table == 'pages_language_overlay') {
            $this->collectPageGarbage($table, $uid);
        } else {
            $this->collectRecordGarbage($table, $uid);
        }

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'] as $classReference) {
                $garbageCollectorPostProcessor = GeneralUtility::getUserObj($classReference);

                if ($garbageCollectorPostProcessor instanceof GarbageCollectorPostProcessor) {
                    $garbageCollectorPostProcessor->postProcessGarbageCollector($table,
                        $uid);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($garbageCollectorPostProcessor) . ' must implement interface ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor',
                        1345807460
                    );
                }
            }
        }
    }

    /**
     * Tracks down index documents belonging to a particular page and
     * removes them from the index and the Index Queue.
     *
     * @param string $table The record's table name.
     * @param integer $uid The record's uid.
     */
    protected function collectPageGarbage($table, $uid)
    {
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');

        switch ($table) {
            case 'tt_content':
                $contentElement = BackendUtility::getRecord('tt_content', $uid,
                    'uid, pid', '', false);

                $table = 'pages';
                $uid = $contentElement['pid'];

                $this->deleteIndexDocuments($table, $uid);
                // only a content element was removed, now update/re-index the page
                $indexQueue->updateItem($table, $uid);
                break;
            case 'pages_language_overlay':
                $pageOverlayRecord = BackendUtility::getRecord('pages_language_overlay',
                    $uid, 'uid, pid', '', false);

                $table = 'pages';
                $uid = $pageOverlayRecord['pid'];

                $this->deleteIndexDocuments($table, $uid);
                // only a page overlay was removed, now update/re-index the page
                $indexQueue->updateItem($table, $uid);
                break;
            case 'pages':

                $this->deleteIndexDocuments($table, $uid);
                $indexQueue->deleteItem($table, $uid);

                break;
        }
    }

    /**
     * @param $table
     * @param $uid
     * @param $changedFields
     * @param $indexQueue
     */
    protected function deleteSubpagesWhenExtendToSubpagesIsSet($table, $uid, $changedFields)
    {
        if (!$this->isRecursiveUpdateRequired($uid, $changedFields)) {
            return;
        }

        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        // get affected subpages when "extendToSubpages" flag was set
        $pagesToDelete = $this->getSubPageIds($uid);
        // we need to at least remove this page
        foreach ($pagesToDelete as $pageToDelete) {
            $this->deleteIndexDocuments($table, $pageToDelete);
            $indexQueue->deleteItem($table, $pageToDelete);
        }
    }

    /**
     * Deletes index documents for a given record identification.
     *
     * @param string $table The record's table name.
     * @param integer $uid The record's uid.
     */
    protected function deleteIndexDocuments($table, $uid)
    {
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');

        // record can be indexed for multiple sites
        $indexQueueItems = $indexQueue->getItems($table, $uid);
        foreach ($indexQueueItems as $indexQueueItem) {
            $site = $indexQueueItem->getSite();

            // a site can have multiple connections (cores / languages)
            $solrConnections = $connectionManager->getConnectionsBySite($site);
            foreach ($solrConnections as $solr) {
                $solr->deleteByQuery('type:' . $table . ' AND uid:' . intval($uid));
                $solr->commit(false, false, false);
            }
        }
    }

    /**
     * Tracks down index documents belonging to a particular record and
     * removes them from the index and the Index Queue.
     *
     * @param string $table The record's table name.
     * @param integer $uid The record's uid.
     */
    protected function collectRecordGarbage($table, $uid)
    {
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');

        $this->deleteIndexDocuments($table, $uid);
        $indexQueue->deleteItem($table, $uid);
    }


    // methods checking whether to trigger garbage collection

    /**
     * Hooks into TCE main and tracks page move commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param integer $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processCmdmap_postProcess(
        $command,
        $table,
        $uid,
        $value,
        DataHandler $tceMain
    ) {
        // workspaces: collect garbage only for LIVE workspace
        if ($command == 'move' && $table == 'pages' && $GLOBALS['BE_USER']->workspace == 0) {
            // TODO the below comment is not valid anymore, pid has been removed from doc ID
            // ...still needed?

            // must be removed from index since the pid changes and
            // is part of the Solr document ID
            $this->collectGarbage($table, $uid);

            // now re-index with new properties
            $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
            $indexQueue->updateItem($table, $uid);
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
    public function processDatamap_preProcessFieldArray(
        $incomingFields,
        $table,
        $uid,
        DataHandler $tceMain
    ) {
        if (!is_int($uid)) {
            // a newly created record, skip
            return;
        }

        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: collect garbage only for LIVE workspace
            return;
        }

        $visibilityAffectingFields = $this->getVisibilityAffectingFieldsByTable($table);

        if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])
            && array_key_exists('fe_group',
                $GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])
        ) {
            $record = BackendUtility::getRecord(
                $table,
                $uid,
                $visibilityAffectingFields,
                '',
                false
            );
            $record = $this->normalizeFrontendGroupField($table, $record);

            // keep previous state of important fields for later comparison
            $this->trackedRecords[$table][$uid] = $record;
        }
    }

    /**
     * Compiles a list of visibility affecting fields of a table so that it can
     * be used in SQL queries.
     *
     * @param string $table Table name to retrieve visibility affecting fields for
     * @return string Comma separated list of field names that affect the visibility of a record on the website
     */
    protected function getVisibilityAffectingFieldsByTable($table)
    {
        static $visibilityAffectingFields;

        if (!isset($visibilityAffectingFields[$table])) {
            // we always want to get the uid and pid although they do not affect visibility
            $fields = array('uid', 'pid');
            if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns'])) {
                $fields = array_merge($fields,
                    $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']);
            }

            if (isset($GLOBALS['TCA'][$table]['ctrl']['delete'])) {
                $fields[] = $GLOBALS['TCA'][$table]['ctrl']['delete'];
            }

            if ($table == 'pages') {
                $fields[] = 'no_search';
                $fields[] = 'doktype';
            }

            $visibilityAffectingFields[$table] = implode(', ', $fields);
        }

        return $visibilityAffectingFields[$table];
    }

    /**
     * Makes sure that "empty" frontend group fields are always the same value.
     *
     * @param string $table The record's table name.
     * @param integer $record The record's uid.
     * @return array The cleaned record
     */
    protected function normalizeFrontendGroupField($table, $record)
    {
        if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroupsField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];

            if ($record[$frontendGroupsField] == '') {
                $record[$frontendGroupsField] = '0';
            }
        }

        return $record;
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
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        array $fields,
        DataHandler $tceMain
    ) {
        if ($status == 'new') {
            // a newly created record, skip
            return;
        }

        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: collect garbage only for LIVE workspace
            return;
        }

        $garbageCollectionRelevantFields = $this->getVisibilityAffectingFieldsByTable($table);

        $record = BackendUtility::getRecord($table, $uid,
            $garbageCollectionRelevantFields, '', false);
        $record = $this->normalizeFrontendGroupField($table, $record);

        if ($this->isHidden($table, $record)
            || ($this->isStartTimeInFuture($table,
                    $record) && $this->isMarkedAsIndexed($table, $record))
            || $this->hasFrontendGroupsRemoved($table, $record)
            || ($table == 'pages' && $this->isPageExcludedFromSearch($record))
            || ($table == 'pages' && !$this->isIndexablePageType($record))
        ) {
            $this->collectGarbage($table, $uid);

            if ($table == 'pages') {
                $this->deleteSubpagesWhenExtendToSubpagesIsSet($table, $uid, $fields);
            }
        }
    }

    /**
     * Checks whether a hidden field exists for the current table and if so
     * determines whether it is set on the current record.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return boolean True if the record is hidden, FALSE otherwise.
     */
    protected function isHidden($table, $record)
    {
        $hidden = false;

        if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'])) {
            $hiddenField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
            $hidden = (boolean)$record[$hiddenField];
        }

        return $hidden;
    }

    /**
     * Checks whether a start time field exists for the record's table and if so
     * determines if a time is set and whether that time is in the future,
     * making the record invisible on the website.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return boolean True if the record's start time is in the future, FALSE otherwise.
     */
    protected function isStartTimeInFuture($table, $record)
    {
        $startTimeInFuture = false;

        if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'])) {
            $startTimeField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['starttime'];
            $startTimeInFuture = $record[$startTimeField] > time();
        }

        return $startTimeInFuture;
    }

    /**
     * Checks whether the record is in the Index Queue and whether it has been
     * indexed already.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return boolean True if the record is marked as being indexed
     */
    protected function isMarkedAsIndexed($table, $record)
    {
        $indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
        return $indexQueue->containsIndexedItem($table, $record['uid']);
    }

    /**
     * Checks whether the a frontend group field exists for the record and if so
     * whether groups have been removed from accessing the record thus making
     * the record invisible to at least some people.
     *
     * @param string $table The table name.
     * @param array $record An array with record fields that may affect visibility.
     * @return boolean TRUE if frontend groups have been removed from access to the record, FALSE otherwise.
     */
    protected function hasFrontendGroupsRemoved($table, $record)
    {
        $frontendGroupsRemoved = false;

        if (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroupsField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];
            $previousGroups = $this->trackedRecords[$table][$record['uid']][$frontendGroupsField];

            $previousGroups = explode(',',
                (string)$this->trackedRecords[$table][$record['uid']][$frontendGroupsField]);
            $currentGroups = explode(',',
                (string)$record[$frontendGroupsField]);

            $removedGroups = array_diff($previousGroups, $currentGroups);

            $frontendGroupsRemoved = (boolean)count($removedGroups);
        }

        return $frontendGroupsRemoved;
    }

    /**
     * Checks whether the page has been excluded from searching.
     *
     * @param array $record An array with record fields that may affect visibility.
     * @return boolean True if the page has been excluded from searching, FALSE otherwise
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
     * @return boolean TRUE if the page can be indexed according to its page type, FALSE otherwise
     */
    protected function isIndexablePageType(array $record)
    {
        return Util::isAllowedPageType($record);
    }

    /**
     * Cleans an index from garbage entries.
     *
     * Was used to clean the index from expired documents/past endtime. Solr 4.8
     * introduced DocExpirationUpdateProcessor to do that job by itself.
     *
     * The method remains as a dummy for possible later cleanups and to prevent
     * things from breaking if others were using it.
     *
     * @param Site $site The site to clean indexes on
     * @param boolean $commitAfterCleanUp Whether to commit right after the clean up, defaults to TRUE
     * @return void
     */
    public function cleanIndex(Site $site, $commitAfterCleanUp = true)
    {
    }
}
