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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Exception\RootPageRecordNotFoundException;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Data update handler
 *
 * Handles update on potential relevant records e.g.
 * an update might require index queue updates
 */
class DataUpdateHandler extends AbstractUpdateHandler
{
    /**
     * List of fields in the update field array that
     * are required for processing
     *
     * Note: For pages all fields except l10n_diffsource are
     *       kept, as additional fields can be configured in
     *       TypoScript, see AbstractDataUpdateEvent->_sleep.
     */
    protected static array $requiredUpdatedFields = [
        'pid',
    ];

    /**
     * Configuration used to check if recursive updates are required
     *
     * Holds the configuration when a recursive page queuing should be triggered, while processing record
     * updates
     *
     * Note: The SQL transaction is already committed, so the current state covers only "non"-changed fields.
     */
    protected array $updateSubPagesRecursiveTriggerConfiguration = [
        // the current page has the both fields "extendToSubpages" and "hidden" set from 1 to 0 => requeue subpages
        'HiddenAndExtendToSubpageWereDisabled' => [
            'changeSet' => [
                'hidden' => '0',
                'extendToSubpages' => '0',
            ],
        ],
        // the current page has the field "extendToSubpages" enabled and the field "hidden" was set to 0 => requeue subpages
        'extendToSubpageEnabledAndHiddenFlagWasRemoved' => [
            'currentState' =>  ['extendToSubpages' => '1'],
            'changeSet' => ['hidden' => '0'],
        ],
        // the current page has the field "hidden" enabled and the field "extendToSubpages" was set to 0 => requeue subpages
        'hiddenIsEnabledAndExtendToSubPagesWasRemoved' => [
            'currentState' =>  ['hidden' => '1'],
            'changeSet' => ['extendToSubpages' => '0'],
        ],
        // the field "no_search_sub_entries" of current page was set to 0
        'no_search_sub_entriesFlagWasAdded' => [
            'changeSet' => ['no_search_sub_entries' => '0'],
        ],
    ];

    protected MountPagesUpdater $mountPageUpdater;

    protected RootPageResolver $rootPageResolver;

    protected ?PagesRepository $pagesRepository;

    protected SolrLogManager $logger;

    protected DataHandler $dataHandler;

    public function __construct(
        ConfigurationAwareRecordService $recordService,
        FrontendEnvironment $frontendEnvironment,
        TCAService $tcaService,
        Queue $indexQueue,
        MountPagesUpdater $mountPageUpdater,
        RootPageResolver $rootPageResolver,
        PagesRepository $pagesRepository,
        DataHandler $dataHandler,
        SolrLogManager $solrLogManager = null
    ) {
        parent::__construct($recordService, $frontendEnvironment, $tcaService, $indexQueue);

        $this->mountPageUpdater = $mountPageUpdater;
        $this->rootPageResolver = $rootPageResolver;
        $this->pagesRepository = $pagesRepository;
        $this->dataHandler = $dataHandler;
        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(
            SolrLogManager::class,
            __CLASS__
        );
    }

    /**
     * @Handle content element update
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws RootPageRecordNotFoundException
     */
    public function handleContentElementUpdate(int $uid, array $updatedFields = []): void
    {
        $pid = $updatedFields['pid'] ?? $this->getValidatedPid('tt_content', $uid);
        if ($pid === null) {
            return;
        }

        $this->processPageRecord($pid, (int)$pid, $updatedFields);
    }

    /**
     * Handles the deletion of a content element
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function handleContentElementDeletion(int $uid): void
    {
        // @TODO: Should be checked, is possibly unnecessary as
        //        also done via GarbageCollector & PageStrategy

        $pid = $this->getValidatedPid('tt_content', $uid);
        if ($pid === null) {
            return;
        }

        $this->indexQueue->updateItem('pages', $pid, Util::getExecutionTime());
    }

    /**
     * Handles page updates
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws RootPageRecordNotFoundException
     */
    public function handlePageUpdate(int $uid, array $updatedFields = []): void
    {
        if ($uid === 0) {
            return;
        }
        try {
            if (isset($updatedFields['l10n_parent']) && (int)($updatedFields['l10n_parent']) > 0) {
                $pid = $updatedFields['l10n_parent'];
            } elseif ($this->rootPageResolver->getIsRootPageId($uid)) {
                $pid = $uid;
            } else {
                $pid = $updatedFields['pid'] ?? $this->getValidatedPid('pages', $uid);
            }
        } catch (Throwable) {
            $pid = null;
        }

        if ($pid === null) {
            $this->removeFromIndexAndQueueWhenItemInQueue('pages', $uid);
            return;
        }

        $this->processPageRecord($uid, (int)$pid, $updatedFields);
    }

    /**
     * Handles record updates
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function handleRecordUpdate(int $uid, string $table): void
    {
        $rootPageIds = $this->getRecordRootPageIds($table, $uid);
        $this->processRecord($table, $uid, $rootPageIds);
    }

    /**
     * Handles a version swap
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function handleVersionSwap(int $uid, string $table): void
    {
        $isPageRelatedRecord = ($table === 'tt_content' || $table === 'pages');
        if ($isPageRelatedRecord) {
            $uid = ($table === 'tt_content' ? $this->getValidatedPid($table, $uid) : $uid);
            if ($uid === null) {
                return;
            }
            $this->applyPageChangesToQueue($uid);
        } else {
            $recordPageId = $this->getValidatedPid($table, $uid);
            if ($recordPageId === null) {
                return;
            }
            $this->applyRecordChangesToQueue($table, $uid, $recordPageId);
        }
    }

    /**
     * Handle page move
     *
     * @param int $uid
     * @param int|null $previousParentId
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function handleMovedPage(int $uid, ?int $previousParentId = null): void
    {
        $excludedPages = $this->pagesRepository->findAllPagesWithinNoSearchSubEntriesMarkedPages();
        if (in_array($uid, $excludedPages)) {
            return;
        }

        $this->applyPageChangesToQueue($uid);

        if ($previousParentId !== null) {
            $pageRecord = BackendUtility::getRecord('pages', $uid);
            if ($pageRecord !== null && (int)$pageRecord['pid'] !== $previousParentId) {
                $treePageIds = $this->getSubPageIds($uid);
                $this->updatePageIdItems($treePageIds);
            }
        }
    }

    /**
     * Handle record move
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function handleMovedRecord(int $uid, string $table): void
    {
        $pid = $this->getValidatedPid($table, $uid);
        if ($pid === null) {
            return;
        }

        $this->applyRecordChangesToQueue($table, $uid, $pid);
    }

    /**
     * Adds a page to the queue and updates mounts, when it is enabled, otherwise ensure that the page is removed
     * from the queue.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function applyPageChangesToQueue(int $uid): void
    {
        $solrConfiguration = $this->getSolrConfigurationFromPageId($uid);
        $record = $this->configurationAwareRecordService->getRecord('pages', $uid, $solrConfiguration);
        if (!empty($record) && $this->tcaService->isEnabledRecord('pages', $record)) {
            $this->mountPageUpdater->update($uid);
            $this->indexQueue->updateItem('pages', $uid);
        } else {
            $this->removeFromIndexAndQueueWhenItemInQueue('pages', $uid);
        }
    }

    /**
     * Adds a record to the queue if it is monitored and enabled, otherwise it removes the record from the queue.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function applyRecordChangesToQueue(string $table, int $uid, int $pid): void
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
     * Removes record from the index queue and from the solr index
     */
    protected function removeFromIndexAndQueue(string $recordTable, int $recordUid): void
    {
        $this->getGarbageHandler()->collectGarbage($recordTable, $recordUid);
    }

    /**
     * Removes record from the index queue and from the solr index when the item is in the queue.
     *
     * @throws DBALException
     */
    protected function removeFromIndexAndQueueWhenItemInQueue(string $recordTable, int $recordUid): void
    {
        if (!$this->indexQueue->containsItem($recordTable, $recordUid)) {
            return;
        }

        $this->removeFromIndexAndQueue($recordTable, $recordUid);
    }

    /**
     * @throws DBALException
     */
    protected function getSolrConfigurationFromPageId(int $pageId): TypoScriptConfiguration
    {
        return $this->frontendEnvironment->getSolrConfigurationFromPageId($pageId);
    }

    /**
     * Fetch record root page ids
     *
     * @return int[]
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function getRecordRootPageIds(string $recordTable, int $recordUid): array
    {
        try {
            $rootPageIds = $this->rootPageResolver->getResponsibleRootPageIds($recordTable, $recordUid);
        } catch (RootPageRecordNotFoundException $e) {
            $rootPageIds = [];
        }

        return $rootPageIds;
    }

    /**
     * Processes a page record
     *
     * Note: Also used if content element is updated, the page
     * of the content element is processed here
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws RootPageRecordNotFoundException
     */
    protected function processPageRecord(int $uid, int $pid, array $updatedFields = []): void
    {
        $configurationPageId = $this->getConfigurationPageId('pages', $pid, $uid);
        if ($configurationPageId === 0) {
            $this->mountPageUpdater->update($uid);
            return;
        }
        $rootPageIds = [$configurationPageId];

        $this->processRecord('pages', $uid, $rootPageIds);

        $this->updateCanonicalPages($uid);
        $this->mountPageUpdater->update($uid);

        $recursiveUpdateRequired = $this->isRecursivePageUpdateRequired($uid, $updatedFields);
        if ($recursiveUpdateRequired) {
            $treePageIds = $this->getSubPageIds($uid);
            $this->updatePageIdItems($treePageIds);
        }
    }

    /**
     * Process a record
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function processRecord(string $recordTable, int $recordUid, array $rootPageIds): void
    {
        if (empty($rootPageIds)) {
            $this->removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid);
            return;
        }

        foreach ($rootPageIds as $configurationPageId) {
            $site = $this->getSiteRepository()->getSiteByPageId($configurationPageId);
            if (!$site instanceof Site) {
                continue;
            }
            $solrConfiguration = $site->getSolrConfiguration();
            $isMonitoredRecord = $solrConfiguration->getIndexQueueIsMonitoredTable($recordTable);
            if (!$isMonitoredRecord) {
                // when it is a non monitored record, we can skip it.
                continue;
            }

            $record = $this->configurationAwareRecordService->getRecord($recordTable, $recordUid, $solrConfiguration);
            if (empty($record)) {
                // TODO move this part to the garbage collector
                // check if the item should be removed from the index because it no longer matches the conditions
                $this->removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid);
                continue;
            }
            // Clear existing index queue items to prevent mount point duplicates.
            // This needs to be done before the overlay handling, because handling an overlay record should
            // not trigger a deletion.
            $isTranslation = !empty($record['sys_language_uid']) && $record['sys_language_uid'] !== 0;
            if ($recordTable === 'pages' && !$isTranslation) {
                $this->indexQueue->deleteItem('pages', $recordUid);
            }

            // The pages localized record can not consist without l10n_parent, so apply "free-content-mode" on records only.
            if ($recordTable === 'pages' || !$site->hasFreeContentModeLanguages() || !in_array($record['sys_language_uid'], $site->getFreeContentModeLanguages())) {
                $recordUid = $this->tcaService->getTranslationOriginalUidIfTranslated($recordTable, $record, $recordUid);
            }

            // only update/insert the item if we actually found a record
            $isLocalizedRecord = $this->tcaService->isLocalizedRecord($recordTable, $record);

            if ($isLocalizedRecord && !$this->getIsTranslationParentRecordEnabled($recordTable, $recordUid)) {
                // we have a localized record without a visible parent record. Nothing to do.
                continue;
            }

            if ($this->tcaService->isEnabledRecord($recordTable, $record)) {
                $this->indexQueue->updateItem($recordTable, $recordUid);
            }
        }
    }

    /**
     * This method is used to determine the pageId that should be used to retrieve the index queue configuration.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws RootPageRecordNotFoundException
     */
    protected function getConfigurationPageId(string $recordTable, int $recordPageId, int $recordUid): int
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($recordPageId);
        $rootPageRecord = $this->getPagesRepository()->getPage($rootPageId);
        if (isset($rootPageRecord['sys_language_uid'])
            && (int)$rootPageRecord['sys_language_uid'] > 0
            && isset($rootPageRecord['l10n_parent'])
            && (int)$rootPageRecord['l10n_parent'] > 0
        ) {
            $rootPageId = $recordPageId = $rootPageRecord['l10n_parent'];
        }
        if ($this->rootPageResolver->getIsRootPageId($rootPageId)) {
            return $recordPageId;
        }

        $alternativeSiteRoots = $this->rootPageResolver->getAlternativeSiteRootPagesIds(
            $recordTable,
            $recordUid,
            $recordPageId
        );
        return (int)array_pop($alternativeSiteRoots);
    }

    /**
     * Checks if the parent record of the translated record is enabled.
     */
    protected function getIsTranslationParentRecordEnabled(string $recordTable, int $recordUid): bool
    {
        $l10nParentRecord = (array)BackendUtility::getRecord($recordTable, $recordUid, '*', '', false);
        return $this->tcaService->isEnabledRecord($recordTable, $l10nParentRecord);
    }

    /**
     * Applies the updateItem instruction on a collection of pageIds.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function updatePageIdItems(array $treePageIds): void
    {
        foreach ($treePageIds as $treePageId) {
            $this->indexQueue->updateItem('pages', $treePageId, time());
            $this->mountPageUpdater->update($treePageId);
        }
    }

    /**
     * Triggers Index Queue updates for other pages showing content from the
     * page currently being updated.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function updateCanonicalPages(int $pageId): void
    {
        $canonicalPages = $this->pagesRepository->findPageUidsWithContentsFromPid($pageId);
        foreach ($canonicalPages as $page) {
            $this->indexQueue->updateItem('pages', $page['uid']);
        }
    }

    /**
     * Retrieves the pid of a record, returns null if no pid could be found
     */
    protected function getValidatedPid(string $table, int $uid): ?int
    {
        $pid = $this->dataHandler->getPID($table, $uid);
        if ($pid === false) {
            $message = 'Record without valid pid was processed ' . $table . ':' . $uid;
            $this->logger->warning($message);
            return null;
        }

        return $pid;
    }

    protected function getGarbageHandler(): GarbageHandler
    {
        return GeneralUtility::makeInstance(GarbageHandler::class);
    }

    protected function getSiteRepository(): SiteRepository
    {
        return GeneralUtility::makeInstance(SiteRepository::class);
    }
}
