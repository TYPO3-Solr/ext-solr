<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\AbstractDataHandlerListener;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * A class that monitors changes to records so that the changed record gets
 * passed to the index queue to update the according index document.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class RecordMonitor extends AbstractDataHandlerListener
{

    /**
     * Solr TypoScript configuration
     *
     * @var TypoScriptConfiguration
     */
    protected $solrConfiguration;

    /**
     * Index Queue
     *
     * @var Queue
     */
    protected $indexQueue;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue');
    }

    /**
     * Holds the configuration when a recursive page queing should be triggered.
     *
     * @var array
     * @return array
     */
    protected function getUpdateSubPagesRecursiveTriggerConfiguration()
    {
        return array(
            // the current page has the field "extendToSubpages" enabled and the field "hidden" was set to 0 => requeue subpages
            'extendToSubpageEnabledAndHiddenFlagWasRemoved' => array(
                'currentState' =>  array('extendToSubpages' => '1'),
                'changeSet' => array('hidden' => '0')
            ),
            // the current page has the field "hidden" enabled and the field "extendToSubpages" was set to 0 => requeue subpages
            'hiddenIsEnabledAndExtendToSubPagesWasRemoved' => array(
                'currentState' =>  array('hidden' => '1'),
                'changeSet' => array('extendToSubpages' => '0')
            )
        );
    }

    /**
     * Hooks into TCE main and tracks record deletion commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value
     * @param DataHandler $tceMain TYPO3 Core Engine parent object
     */
    public function processCmdmap_preProcess(
        $command,
        $table,
        $uid,
        $value,
        DataHandler $tceMain
    ) {
        if ($command == 'delete' && $table == 'tt_content' && $GLOBALS['BE_USER']->workspace == 0) {
            // skip workspaces: index only LIVE workspace
            $this->indexQueue->updateItem('pages',
                $this->getValidatedPid($tceMain, $table, $uid),
                null,
                time()
            );
        }
    }

    /**
     * Hooks into TCE main and tracks workspace publish/swap events and
     * page move commands in LIVE workspace.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value
     * @param DataHandler $tceMain TYPO3 Core Engine parent object
     */
    public function processCmdmap_postProcess(
        $command,
        $table,
        $uid,
        $value,
        DataHandler $tceMain
    ) {
        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        // track publish / swap events for records (workspace support)
        // command "version"
        if ($command == 'version' && $value['action'] == 'swap') {
            switch ($table) {
                /** @noinspection PhpMissingBreakStatementInspection */
                case 'tt_content':
                    $uid = $this->getValidatedPid($tceMain, $table, $uid);
                    $table = 'pages';
                case 'pages':
                    $this->solrConfiguration = Util::getSolrConfigurationFromPageId($uid);
                    $record = $this->getRecord($table, $uid);

                    if (!empty($record) && $this->isEnabledRecord($table,
                            $record)
                    ) {
                        $this->updateMountPages($uid);

                        $this->indexQueue->updateItem($table, $uid);
                    } else {
                        // TODO should be moved to garbage collector
                        if ($this->indexQueue->containsItem($table, $uid)) {
                            $this->removeFromIndexAndQueue($table, $uid);
                        }
                    }
                    break;
                default:
                    $recordPageId = $this->getValidatedPid($tceMain, $table, $uid);
                    $this->solrConfiguration = Util::getSolrConfigurationFromPageId($recordPageId);
                    $isMonitoredTable = $this->solrConfiguration->getIndexQueueIsMonitoredTable($table);

                    if ($isMonitoredTable) {
                        $record = $this->getRecord($table, $uid);

                        if (!empty($record) && $this->isEnabledRecord($table,
                                $record)
                        ) {
                            if (Util::isLocalizedRecord($table, $record)) {
                                // if it's a localization overlay, update the original record instead
                                $uid = $record[$GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']];
                            }

                            $configurationName = $this->getIndexingConfigurationName($table,
                                $uid);
                            $this->indexQueue->updateItem($table, $uid,
                                $configurationName);
                        } else {
                            // TODO should be moved to garbage collector
                            if ($this->indexQueue->containsItem($table, $uid)) {
                                $this->removeFromIndexAndQueue($table, $uid);
                            }
                        }
                    }
            }
        }

        if ($command == 'move' && $table == 'pages' && $GLOBALS['BE_USER']->workspace == 0) {
            // moving pages in LIVE workspace
            $this->solrConfiguration = Util::getSolrConfigurationFromPageId($uid);
            $record = $this->getRecord('pages', $uid);
            if (!empty($record) && $this->isEnabledRecord($table, $record)) {
                $this->indexQueue->updateItem('pages', $uid);
            } else {
                // check if the item should be removed from the index because it no longer matches the conditions
                if ($this->indexQueue->containsItem('pages', $uid)) {
                    $this->removeFromIndexAndQueue('pages', $uid);
                }
            }
        }
    }

    /**
     * Hooks into TCE Main and watches all record creations and updates. If it
     * detects that the new/updated record belongs to a table configured for
     * indexing through Solr, we add the record to the index queue.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data
     * @param DataHandler $tceMain TYPO3 Core Engine parent object
     * @return void
     */
    public function processDatamap_afterDatabaseOperations(
        $status,
        $table,
        $uid,
        array $fields,
        DataHandler $tceMain
    ) {
        $recordTable = $table;
        $recordUid = $uid;

        if ($status == 'new') {
            $recordUid = $tceMain->substNEWwithIDs[$recordUid];
        }
        if (Util::isDraftRecord($table, $recordUid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        $recordPageId = $this->getRecordPageId($status, $recordTable, $recordUid, $uid, $fields, $tceMain);

        // when a content element changes we need to updated the page instead
        if ($recordTable == 'tt_content') {
            $recordTable = 'pages';
            $recordUid = $recordPageId;
        }

        $this->solrConfiguration = Util::getSolrConfigurationFromPageId($recordPageId);
        $isMonitoredRecord = $this->solrConfiguration->getIndexQueueIsMonitoredTable($recordTable);

        if (!$isMonitoredRecord) {
            // when it is a non monitored record, we can skip it.
            return;
        }

        $record = $this->getRecord($recordTable, $recordUid);
        if (empty($record)) {
            // TODO move this part to the garbage collector
            // check if the item should be removed from the index because it no longer matches the conditions
            if ($this->indexQueue->containsItem($recordTable, $recordUid)) {
                $this->removeFromIndexAndQueue($recordTable, $recordUid);
            }
            return;
        }

        // only update/insert the item if we actually found a record
        $isLocalizedRecord = Util::isLocalizedRecord($recordTable, $record);
        if ($isLocalizedRecord) {
            // if it's a localization overlay, update the original record instead
            $recordUid = $record[$GLOBALS['TCA'][$recordTable]['ctrl']['transOrigPointerField']];
            if ($recordTable == 'pages_language_overlay') {
                $recordTable = 'pages';
            }
        }

        if ($isLocalizedRecord && !$this->getIsTranslationParentRecordEnabled($recordTable, $recordUid)) {
            // we have a localized record without a visible parent record. Nothing to do.
            return;
        }

        // Clear existing index queue items to prevent mount point duplicates.
        if ($recordTable == 'pages') {
            $this->indexQueue->deleteItem('pages', $recordUid);
        }

        if ($this->isEnabledRecord($recordTable, $record)) {
            $configurationName = null;
            if ($recordTable !== 'pages') {
                $configurationName = $this->getIndexingConfigurationName($recordTable, $recordUid);
            }

            $this->indexQueue->updateItem($recordTable, $recordUid, $configurationName);
        }

        if ($recordTable == 'pages') {
            $this->doPagesPostUpdateOperations($fields, $recordUid);
        }
    }

    /**
     * Checks if the parent record of the translated record is enabled.
     *
     * @param string $recordTable
     * @param integer $recordUid
     * @return bool
     */
    protected function getIsTranslationParentRecordEnabled($recordTable, $recordUid)
    {
        $tableEnableFields = implode(', ', $GLOBALS['TCA'][$recordTable]['ctrl']['enablecolumns']);
        $l10nParentRecord = BackendUtility::getRecord($recordTable, $recordUid, $tableEnableFields, '', false);
        return $this->isEnabledRecord($recordTable, $l10nParentRecord);
    }

    /**
     * Applies needed updates when a pages record was processed by the RecordMonitor.
     *
     * @param array $fields
     * @param int $recordUid
     */
    protected function doPagesPostUpdateOperations(array $fields, $recordUid)
    {
        $this->updateCanonicalPages($recordUid);
        $this->updateMountPages($recordUid);

        if ($this->isRecursiveUpdateRequired($recordUid, $fields)) {
            $treePageIds = $this->getSubPageIds($recordUid);
            $this->updatePageIdItems($treePageIds);
        }
    }

    /**
     * Determines the recordPageId (pid) of a record.
     *
     * @param string $status
     * @param string $recordTable
     * @param int $recordUid
     * @param int $originalUid
     * @param array $fields
     * @param DataHandler $tceMain
     * @return int
     */
    protected function getRecordPageId($status, $recordTable, $recordUid, $originalUid, array $fields, DataHandler $tceMain)
    {
        if ($status == 'update' && !isset($fields['pid'])) {
            $recordPageId = $this->getValidatedPid($tceMain, $recordTable, $recordUid);
            if ($recordTable == 'pages' && Util::isRootPage($recordUid)) {
                $recordPageId = $originalUid;
            }

            return $recordPageId;
        }

        return $fields['pid'];
    }

    /**
     * Applies the updateItem instruction on a collection of pageIds.
     *
     * @param array $treePageIds
     */
    protected function updatePageIdItems(array $treePageIds)
    {
        foreach ($treePageIds as $treePageId) {
            $this->indexQueue->updateItem('pages', $treePageId);
        }
    }

    /**
     * Removes record from the index queue and from the solr index
     *
     * @param string $recordTable Name of table where the record lives
     * @param int $recordUid Id of record
     */
    protected function removeFromIndexAndQueue($recordTable, $recordUid)
    {
        $garbageCollector = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\GarbageCollector');
        $garbageCollector->collectGarbage($recordTable, $recordUid);
    }

    /**
     * Retrieves a record, taking into account the additionalWhereClauses of the
     * Indexing Queue configurations.
     *
     * @param string $recordTable Table to read from
     * @param int $recordUid Id of the record
     * @return array Record if found, otherwise empty array
     */
    protected function getRecord($recordTable, $recordUid)
    {
        $record = array();
        $indexingConfigurations = $this->solrConfiguration->getEnabledIndexQueueConfigurationNames();

        foreach ($indexingConfigurations as $indexingConfigurationName) {
            $record = $this->getRecordWhenIndexConfigurationIsValid($recordTable, $recordUid, $indexingConfigurationName);
            if (!empty($record)) {
                // if we found a record which matches the conditions, we can continue
                break;
            }
        }

        return $record;
    }

    // Handle pages showing content from another page

    /**
     * Triggers Index Queue updates for other pages showing content from the
     * page currently being updated.
     *
     * @param int $pageId UID of the page currently being updated
     */
    protected function updateCanonicalPages($pageId)
    {
        $canonicalPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid',
            'pages',
            'content_from_pid = ' . $pageId
            . BackendUtility::deleteClause('pages')
        );

        foreach ($canonicalPages as $page) {
            $this->indexQueue->updateItem('pages', $page['uid']);
        }
    }

    // Mount Page Handling

    /**
     * Handles updates of the Index Queue in case a newly created or changed
     * page is part of a tree that is mounted into a another site.
     *
     * @param int $pageId Page Id (uid).
     */
    protected function updateMountPages($pageId)
    {
        // get the root line of the page, every parent page could be a Mount Page source
        /** @var $pageSelect PageRepository */
        $pageSelect = GeneralUtility::makeInstance(PageRepository::class);
        $rootLine = $pageSelect->getRootLine($pageId);

        $destinationMountProperties = $this->getDestinationMountPropertiesByRootLine($rootLine);

        if (!empty($destinationMountProperties)) {
            foreach ($destinationMountProperties as $destinationMount) {
                $this->addPageToMountingSiteIndexQueue($pageId,
                    $destinationMount);
            }
        }
    }

    /**
     * Finds Mount Pages that mount pages in a given root line.
     *
     * @param array $rootLine Root line of pages to check for usage as mount source
     * @return array Array of pages found to be mounting pages from the root line.
     */
    protected function getDestinationMountPropertiesByRootLine(array $rootLine)
    {
        $mountPages = array();

        $currentPage = array_shift($rootLine);
        $currentPageUid = (int)$currentPage['uid'];

        $rootLineParentPageIds = $this->getParentPageIdsFromRootLine($rootLine);

        if (empty($rootLine) && $currentPageUid === 0) {
            return $mountPages;
        }

        $pageQueryConditions = array();
        if (!empty($rootLineParentPageIds)) {
            $pageQueryConditions[] = '(mount_pid IN(' . implode(',', $rootLineParentPageIds) . '))';
        }

        if ($currentPageUid !== 0) {
            $pageQueryConditions[] = '(mount_pid=' . $currentPageUid . ' AND mount_pid_ol=1)';
        }
        $pageQueryCondition = implode(' OR ', $pageQueryConditions);

        $mountPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, uid AS mountPageDestination, mount_pid AS mountPageSource, mount_pid_ol AS mountPageOverlayed',
            'pages',
            '(' . $pageQueryCondition . ') AND doktype = 7 AND no_search = 0'
            . BackendUtility::deleteClause('pages')
        );

        return $mountPages;
    }

    /**
     * Extracts an array of pageIds from an rootline array.
     *
     * @param array $rootLine
     * @return array
     */
    protected function getParentPageIdsFromRootLine(array $rootLine)
    {
        $rootLineParentPageIds = [];
        if (empty($rootLine)) {
            // no rootline given
            return $rootLineParentPageIds;
        }

        foreach ($rootLine as $pageRecord) {
            $rootLineParentPageIds[] = $pageRecord['uid'];
            if ($pageRecord['is_siteroot']) {
                break;
            }
        }

        return $rootLineParentPageIds;
    }

    /**
     * Adds a page to the Index Queue of a site mounting the page.
     *
     * @param int $mountedPageId ID (uid) of the mounted page.
     * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
     */
    protected function addPageToMountingSiteIndexQueue(
        $mountedPageId,
        array $mountProperties
    ) {
        $mountingSite = Site::getSiteByPageId($mountProperties['mountPageDestination']);

        $pageInitializer = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Initializer\\Page');
        $pageInitializer->setSite($mountingSite);

        $pageInitializer->initializeMountedPage($mountProperties,
            $mountedPageId);
    }

    /**
     * Retrieves the pid of a record and throws an exception when getPid returns false.
     *
     * @param DataHandler $tceMain
     * @param string $table
     * @param integer $uid
     * @throws NoPidException
     * @return integer
     */
    protected function getValidatedPid(DataHandler $tceMain, $table, $uid)
    {
        $pid = $tceMain->getPID($table, $uid);
        if ($pid === false) {
            throw new NoPidException('Pid should not be false');
        }

        $pid = intval($pid);
        return $pid;
    }

    /**
     * Checks if a record is "enabled"
     *
     * A record is considered "enabled" if
     *  - it is not hidden
     *  - it is not deleted
     *  - as a page it is not set to be excluded from search
     *
     * @param string $table The record's table name
     * @param array $record The record to check
     * @return bool TRUE if the record is enabled, FALSE otherwise
     */
    protected function isEnabledRecord($table, $record)
    {
        $recordEnabled = true;

        if (
            (isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled']) && !empty($record[$GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled']]))
            ||
            (isset($GLOBALS['TCA'][$table]['ctrl']['delete']) && !empty($record[$GLOBALS['TCA'][$table]['ctrl']['delete']]))
            ||
            ($table == 'pages' && !empty($record['no_search']))
        ) {
            $recordEnabled = false;
        }

        return $recordEnabled;
    }

    /**
     * Retrieves the name of the  Indexing Queue Configuration for a record
     *
     * @param string $recordTable Table to read from
     * @param int $recordUid Id of the record
     * @return string Name of indexing configuration
     */
    protected function getIndexingConfigurationName($recordTable, $recordUid)
    {
        $name = $recordTable;
        $indexingConfigurations = $this->solrConfiguration->getEnabledIndexQueueConfigurationNames();
        foreach ($indexingConfigurations as $indexingConfigurationName) {
            if (!$this->solrConfiguration->getIndexQueueConfigurationIsEnabled($indexingConfigurationName)) {
                // ignore disabled indexing configurations
                continue;
            }

            $record = $this->getRecordWhenIndexConfigurationIsValid($recordTable, $recordUid, $indexingConfigurationName);
            if (!empty($record)) {
                $name = $indexingConfigurationName;
                // FIXME currently returns after the first configuration match
                break;
            }
        }

        return $name;
    }

    /**
     * This method return the record array if the table is valid for this indexingConfiguration.
     * Otherwise an empty array will be returned.
     *
     * @param string $recordTable
     * @param integer $recordUid
     * @param string $indexingConfigurationName
     * @return array
     */
    protected function getRecordWhenIndexConfigurationIsValid($recordTable, $recordUid, $indexingConfigurationName)
    {
        if (!$this->isValidTableForIndexConfigurationName($recordTable, $indexingConfigurationName)) {
            return [];
        }

        $recordWhereClause = $this->solrConfiguration->getIndexQueueAdditionalWhereClauseByConfigurationName($indexingConfigurationName);

        if ($recordTable === 'pages_language_overlay') {
            return $this->getPageOverlayRecordWhenParentIsAccessible($recordUid, $recordWhereClause);
        }

        return (array) BackendUtility::getRecord($recordTable, $recordUid, '*', $recordWhereClause);
    }

    /**
     * This method retrieves the pages_language_overlay record when the parent record is accessible
     * through the recordWhereClause
     *
     * @param int $recordUid
     * @param string $parentWhereClause
     * @return array
     */
    protected function getPageOverlayRecordWhenParentIsAccessible($recordUid, $parentWhereClause)
    {
        $overlayRecord = (array) BackendUtility::getRecord('pages_language_overlay', $recordUid, '*');
        $pageRecord = (array) BackendUtility::getRecord('pages', $overlayRecord['pid'], '*', $parentWhereClause);

        if (empty($pageRecord)) {
            return [];
        }

        return $overlayRecord;
    }

    /**
     * This method is used to check if a table is an allowed table for an index configuration.
     *
     * @param string $recordTable
     * @param string $indexingConfigurationName
     * @return boolean
     */
    protected function isValidTableForIndexConfigurationName($recordTable, $indexingConfigurationName)
    {
        $tableToIndex = $this->solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);

        $isMatchingTable = $tableToIndex === $recordTable;
        $isPagesPassedAndOverlayRequested = $tableToIndex === 'pages' && $recordTable === 'pages_language_overlay';

        if ($isMatchingTable || $isPagesPassedAndOverlayRequested) {
            return true;
        }

        return false;
    }
}
