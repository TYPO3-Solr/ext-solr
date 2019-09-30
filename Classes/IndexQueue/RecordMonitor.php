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

use ApacheSolrForTypo3\Solr\AbstractDataHandlerListener;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A class that monitors changes to records so that the changed record gets
 * passed to the index queue to update the according index document.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class RecordMonitor extends AbstractDataHandlerListener
{
    /**
     * Index Queue
     *
     * @var Queue
     */
    protected $indexQueue;

    /**
     * Mount Page Updater
     *
     * @var MountPagesUpdater
     */
    protected $mountPageUpdater;

    /**
     * TCA Service
     *
     * @var TCAService
     */
    protected $tcaService;

    /**
     * RootPageResolver
     *
     * @var RootPageResolver
     */
    protected $rootPageResolver;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * @var SolrLogManager
     */
    protected $logger = null;

    /**
     * RecordMonitor constructor.
     *
     * @param Queue|null $indexQueue
     * @param MountPagesUpdater|null $mountPageUpdater
     * @param TCAService|null $TCAService
     * @param RootPageResolver $rootPageResolver
     * @param PagesRepository|null $pagesRepository
     * @param SolrLogManager|null $solrLogManager
     * @param ConfigurationAwareRecordService|null $recordService
     */
    public function __construct(Queue $indexQueue = null, MountPagesUpdater $mountPageUpdater = null, TCAService $TCAService = null, RootPageResolver $rootPageResolver = null, PagesRepository $pagesRepository = null, SolrLogManager $solrLogManager = null, ConfigurationAwareRecordService $recordService = null)
    {
        parent::__construct($recordService);
        $this->indexQueue = $indexQueue ?? GeneralUtility::makeInstance(Queue::class);
        $this->mountPageUpdater = $mountPageUpdater ?? GeneralUtility::makeInstance(MountPagesUpdater::class);
        $this->tcaService = $TCAService ?? GeneralUtility::makeInstance(TCAService::class);
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
    }

    /**
     * @param SolrLogManager $logger
     */
    public function setLogger(SolrLogManager $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Holds the configuration when a recursive page queing should be triggered.
     *
     * @var array
     * @return array
     */
    protected function getUpdateSubPagesRecursiveTriggerConfiguration()
    {
        return [
            // the current page has the field "extendToSubpages" enabled and the field "hidden" was set to 0 => requeue subpages
            'extendToSubpageEnabledAndHiddenFlagWasRemoved' => [
                'currentState' =>  ['extendToSubpages' => '1'],
                'changeSet' => ['hidden' => '0']
            ],
            // the current page has the field "hidden" enabled and the field "extendToSubpages" was set to 0 => requeue subpages
            'hiddenIsEnabledAndExtendToSubPagesWasRemoved' => [
                'currentState' =>  ['hidden' => '1'],
                'changeSet' => ['extendToSubpages' => '0']
            ]
        ];
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
        /** @noinspection PhpUnusedParameterInspection */
        $value,
        DataHandler $tceMain
    ) {
        if ($command === 'delete' && $table === 'tt_content' && $GLOBALS['BE_USER']->workspace == 0) {
            // skip workspaces: index only LIVE workspace
            $pid = $this->getValidatedPid($tceMain, $table, $uid);
            $this->indexQueue->updateItem('pages', $pid, time());
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
    public function processCmdmap_postProcess($command, $table, $uid, $value, DataHandler $tceMain)
    {
        if ($this->isDraftRecord($table, $uid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        // track publish / swap events for records (workspace support)
        // command "version"
        if ($command === 'version' && $value['action'] === 'swap') {
            $this->applyVersionSwap($table, $uid, $tceMain);
        }

        // moving pages/records in LIVE workspace
        if ($command === 'move' && $GLOBALS['BE_USER']->workspace == 0) {
            if ($table === 'pages') {
                $this->applyPageChangesToQueue($uid);
            } else {
                $this->applyRecordChangesToQueue($table, $uid, $value);
            }

        }
    }

    /**
     * Apply's version swap to the IndexQueue.
     *
     * @param string $table
     * @param integer $uid
     * @param DataHandler $tceMain
     */
    protected function applyVersionSwap($table, $uid, DataHandler $tceMain)
    {
        $isPageRelatedRecord = $table === 'tt_content' || $table === 'pages';
        if($isPageRelatedRecord) {
            $uid = $table === 'tt_content' ? $this->getValidatedPid($tceMain, $table, $uid) : $uid;
            $this->applyPageChangesToQueue($uid);
        } else {
            $recordPageId = $this->getValidatedPid($tceMain, $table, $uid);
            $this->applyRecordChangesToQueue($table, $uid, $recordPageId);
        }
    }

    /**
     * Add's a page to the queue and updates mounts, when it is enabled, otherwise ensure that the page is removed
     * from the queue.
     *
     * @param integer $uid
     */
    protected function applyPageChangesToQueue($uid)
    {
        $solrConfiguration = $this->getSolrConfigurationFromPageId($uid);
        $record = $this->configurationAwareRecordService->getRecord('pages', $uid, $solrConfiguration);
        if (!empty($record) && $this->tcaService->isEnabledRecord('pages', $record)) {
            $this->mountPageUpdater->update($uid);
            $this->indexQueue->updateItem('pages', $uid);
        } else {
            // TODO should be moved to garbage collector
            $this->removeFromIndexAndQueueWhenItemInQueue('pages', $uid);
        }
    }

    /**
     * Add's a record to the queue if it is monitored and enabled, otherwise it removes the record from the queue.
     * 
     * @param string $table
     * @param integer $uid
     * @param integer $pid
     */
    protected function applyRecordChangesToQueue($table, $uid, $pid)
    {
        $solrConfiguration = $this->getSolrConfigurationFromPageId($pid);
        $isMonitoredTable = $solrConfiguration->getIndexQueueIsMonitoredTable($table);

        if ($isMonitoredTable) {
            $record = $this->configurationAwareRecordService->getRecord($table, $uid, $solrConfiguration);

            if (!empty($record) && $this->tcaService->isEnabledRecord($table, $record)) {
                $uid = $this->tcaService->getTranslationOriginalUidIfTranslated($table, $record, $uid);
                $this->indexQueue->updateItem($table, $uid);
            } else {
                // TODO should be moved to garbage collector
                $this->removeFromIndexAndQueueWhenItemInQueue($table, $uid);
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
    public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, DataHandler $tceMain) {
        $recordTable = $table;
        $recordUid = $uid;

        if ($this->skipMonitoringOfTable($table)) {
            return;
        }

        if ($status === 'new') {
            $recordUid = $tceMain->substNEWwithIDs[$recordUid];
        }
        if ($this->isDraftRecord($table, $recordUid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        try {
            $recordPageId = $this->getRecordPageId($status, $recordTable, $recordUid, $uid, $fields, $tceMain);

            // when a content element changes we need to updated the page instead
            if ($recordTable === 'tt_content') {
                $recordTable = 'pages';
                $recordUid = $recordPageId;
            }

            $this->processRecord($recordTable, $recordPageId, $recordUid, $fields);
        } catch (NoPidException $e) {
            $message = 'Record without valid pid was processed ' . $table . ':' . $uid;
            $this->logger->log(SolrLogManager::WARNING, $message);
        }
    }

    /**
     * Check if the provided table is explicitly configured for monitoring
     *
     * @param string $table
     * @return bool
     */
    protected function skipMonitoringOfTable($table)
    {
        static $configurationMonitorTables;

        if (empty($configurationMonitorTables)) {
            $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $configurationMonitorTables = $configuration->getIsUseConfigurationMonitorTables();
        }

        // No explicit configuration => all tables should be monitored
        if (empty($configurationMonitorTables)) {
            return false;
        }

        return !in_array($table, $configurationMonitorTables);
    }

    /**
     * Process the record located in processDatamap_afterDatabaseOperations
     *
     * @param string $recordTable The table the record belongs to
     * @param int $recordPageId pageid
     * @param mixed $recordUid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data
     */
    protected function processRecord($recordTable, $recordPageId, $recordUid, $fields)
    {
        $configurationPageId = $this->getConfigurationPageId($recordTable, $recordPageId, $recordUid);

        if ($configurationPageId === 0) {
            // when the monitored record doesn't belong to a solr configured root-page and no alternative
            // siteroot can be found this is not a relevant record
            return;
        }

        $solrConfiguration = $this->getSolrConfigurationFromPageId($configurationPageId);
        $isMonitoredRecord = $solrConfiguration->getIndexQueueIsMonitoredTable($recordTable);

        if (!$isMonitoredRecord) {
            // when it is a non monitored record, we can skip it.
            return;
        }

        $record = $this->configurationAwareRecordService->getRecord($recordTable, $recordUid, $solrConfiguration);

        if (empty($record)) {
            // TODO move this part to the garbage collector
            // check if the item should be removed from the index because it no longer matches the conditions
            $this->removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid);
            return;
        }

        // Clear existing index queue items to prevent mount point duplicates.
        // This needs to be done before the overlay handling, because handling an overlay record should
        // not trigger a deletion.
        $isTranslation = !empty($record['sys_language_uid']) && $record['sys_language_uid'] !== 0;
        if ($recordTable === 'pages' && !$isTranslation) {
            $this->indexQueue->deleteItem('pages', $recordUid);
        }

        // only update/insert the item if we actually found a record
        $isLocalizedRecord = $this->tcaService->isLocalizedRecord($recordTable, $record);
        $recordUid = $this->tcaService->getTranslationOriginalUidIfTranslated($recordTable, $record, $recordUid);

        if ($isLocalizedRecord && !$this->getIsTranslationParentRecordEnabled($recordTable, $recordUid)) {
            // we have a localized record without a visible parent record. Nothing to do.
            return;
        }

        if ($this->tcaService->isEnabledRecord($recordTable, $record)) {
            $this->indexQueue->updateItem($recordTable, $recordUid);
        }

        if ($recordTable === 'pages') {
            $this->doPagesPostUpdateOperations($fields, $recordUid);
        }
    }

    /**
     * This method is used to determine the pageId that should be used to retrieve the index queue configuration.
     *
     * @param string $recordTable
     * @param integer $recordPageId
     * @param integer $recordUid
     * @return integer
     */
    protected function getConfigurationPageId($recordTable, $recordPageId, $recordUid)
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($recordPageId);
        if ($this->rootPageResolver->getIsRootPageId($rootPageId)) {
            return $recordPageId;
        }

        $alternativeSiteRoots = $this->rootPageResolver->getAlternativeSiteRootPagesIds($recordTable, $recordUid, $recordPageId);
        $lastRootPage = array_pop($alternativeSiteRoots);
        return empty($lastRootPage) ? 0 : $lastRootPage;
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
        $l10nParentRecord = (array)BackendUtility::getRecord($recordTable, $recordUid, $tableEnableFields, '', false);
        return $this->tcaService->isEnabledRecord($recordTable, $l10nParentRecord);
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
        $this->mountPageUpdater->update($recordUid);

        if ($this->isRecursivePageUpdateRequired($recordUid, $fields)) {
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
     *
     * @return int
     */
    protected function getRecordPageId($status, $recordTable, $recordUid, $originalUid, array $fields, DataHandler $tceMain)
    {
        if ($recordTable === 'pages' && isset($fields['l10n_parent']) && intval($fields['l10n_parent']) > 0) {
            return $fields['l10n_parent'];
        }

        if ($status === 'update' && !isset($fields['pid'])) {
            $recordPageId = $this->getValidatedPid($tceMain, $recordTable, $recordUid);
            if (($recordTable === 'pages') && ($this->rootPageResolver->getIsRootPageId($recordUid))) {
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
        $garbageCollector = GeneralUtility::makeInstance(GarbageCollector::class);
        $garbageCollector->collectGarbage($recordTable, $recordUid);
    }

    /**
     * Removes record from the index queue and from the solr index when the item is in the queue.
     *
     * @param string $recordTable Name of table where the record lives
     * @param int $recordUid Id of record
     */
    protected function removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid)
    {
        if (!$this->indexQueue->containsItem($recordTable, $recordUid)) {
            return;
        }

        $this->removeFromIndexAndQueue($recordTable, $recordUid);
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
        $canonicalPages = $this->pagesRepository->findPageUidsWithContentsFromPid((int)$pageId);
        foreach ($canonicalPages as $page) {
            $this->indexQueue->updateItem('pages', $page['uid']);
        }
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
     * Checks if the record is a draft record.
     *
     * @param string $table
     * @param int $uid
     * @return bool
     */
    protected function isDraftRecord($table, $uid)
    {
        return Util::isDraftRecord($table, $uid);
    }

    /**
     * @param $pageId
     * @param bool $initializeTsfe
     * @param int $language
     * @return TypoScriptConfiguration
     */
    protected function getSolrConfigurationFromPageId($pageId, $initializeTsfe = false, $language = 0)
    {
        return Util::getSolrConfigurationFromPageId($pageId, $initializeTsfe, $language);
    }
}
