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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\Statistic\QueueStatistic;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\Utility\DatabaseUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Indexing Queue. It allows us to decouple from frontend indexing and
 * reacting to changes faster.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Queue
{
    /**
     * @var RootPageResolver
     */
    protected $rootPageResolver;

    /**
     * @var ConfigurationAwareRecordService
     */
    protected $recordService;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager
     */
    protected $logger = null;

    /**
     * Queue constructor.
     * @param RootPageResolver|null $rootPageResolver
     * @param ConfigurationAwareRecordService|null $recordService
     */
    public function __construct(RootPageResolver $rootPageResolver = null, ConfigurationAwareRecordService $recordService = null)
    {
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->rootPageResolver = isset($rootPageResolver) ? $rootPageResolver : GeneralUtility::makeInstance(RootPageResolver::class);
        $this->recordService = isset($recordService) ? $recordService : GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
    }

    // FIXME some of the methods should be renamed to plural forms
    // FIXME singular form methods should deal with exactly one item only

    /**
     * Returns the timestamp of the last indexing run.
     *
     * @param int $rootPageId The root page uid for which to get
     *      the last indexed item id
     * @return int Timestamp of last index run.
     */
    public function getLastIndexTime($rootPageId)
    {
        $lastIndexTime = 0;

        $lastIndexedRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'indexed',
            'tx_solr_indexqueue_item',
            'root = ' . (int)$rootPageId,
            '',
            'indexed DESC',
            1
        );

        if ($lastIndexedRow[0]['indexed']) {
            $lastIndexTime = $lastIndexedRow[0]['indexed'];
        }

        return $lastIndexTime;
    }

    /**
     * Returns the uid of the last indexed item in the queue
     *
     * @param int $rootPageId The root page uid for which to get
     *      the last indexed item id
     * @return int The last indexed item's ID.
     */
    public function getLastIndexedItemId($rootPageId)
    {
        $lastIndexedItemId = 0;

        $lastIndexedItemRow = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid',
            'tx_solr_indexqueue_item',
            'root = ' . (int)$rootPageId,
            '',
            'indexed DESC',
            1
        );
        if ($lastIndexedItemRow[0]['uid']) {
            $lastIndexedItemId = $lastIndexedItemRow[0]['uid'];
        }

        return $lastIndexedItemId;
    }

    /**
     * Truncate and rebuild the tx_solr_indexqueue_item table. This is the most
     * complete way to force reindexing, or to build the Index Queue for the
     * first time. The Index Queue initialization is site-specific.
     *
     * @param Site $site The site to initialize
     * @param string $indexingConfigurationName Name of a specific
     *      indexing configuration
     * @return array An array of booleans, each representing whether the
     *      initialization for an indexing configuration was successful
     */
    public function initialize(Site $site, $indexingConfigurationName = '')
    {
        $indexingConfigurations = [];
        $initializationStatus = [];

        if (empty($indexingConfigurationName)) {
            $solrConfiguration = $site->getSolrConfiguration();
            $indexingConfigurations = $solrConfiguration->getEnabledIndexQueueConfigurationNames();
        } else {
            $indexingConfigurations[] = $indexingConfigurationName;
        }

        foreach ($indexingConfigurations as $indexingConfigurationName) {
            $initializationStatus[$indexingConfigurationName] = $this->initializeIndexingConfiguration(
                $site,
                $indexingConfigurationName
            );
        }

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueInitialization'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueInitialization'] as $classReference) {
                $indexQueueInitializationPostProcessor = GeneralUtility::getUserObj($classReference);

                if ($indexQueueInitializationPostProcessor instanceof InitializationPostProcessor) {
                    $indexQueueInitializationPostProcessor->postProcessIndexQueueInitialization(
                        $site,
                        $indexingConfigurations,
                        $initializationStatus
                    );
                } else {
                    throw new \UnexpectedValueException(
                        get_class($indexQueueInitializationPostProcessor) .
                        ' must implement interface ' . InitializationPostProcessor::class,
                        1345815561
                    );
                }
            }
        }

        return $initializationStatus;
    }

    /**
     * Initializes the Index Queue for a specific indexing configuration.
     *
     * @param Site $site The site to initialize
     * @param string $indexingConfigurationName name of a specific
     *      indexing configuration
     * @return bool TRUE if the initialization was successful, FALSE otherwise
     */
    protected function initializeIndexingConfiguration(
        Site $site,
        $indexingConfigurationName
    ) {
        // clear queue
        $this->deleteItemsBySite($site, $indexingConfigurationName);

        $solrConfiguration = $site->getSolrConfiguration();

        $tableToIndex = $solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);
        $initializerClass = $solrConfiguration->getIndexQueueInitializerClassByConfigurationName($indexingConfigurationName);

        $initializer = GeneralUtility::makeInstance($initializerClass);
        /** @var $initializer \ApacheSolrForTypo3\Solr\IndexQueue\Initializer\AbstractInitializer */
        $initializer->setSite($site);
        $initializer->setType($tableToIndex);
        $initializer->setIndexingConfigurationName($indexingConfigurationName);

        $indexConfiguration = $solrConfiguration->getIndexQueueConfigurationByName($indexingConfigurationName);
        $initializer->setIndexingConfiguration($indexConfiguration);

        return $initializer->initialize();
    }

    /**
     * Gets the indexing configuration to use for an item.
     * Sometimes, when there are multiple configurations for a certain item type
     * (table) it can be hard or even impossible to find which one to use
     * though.
     * Currently selects the first indexing configuration where the name matches
     * the itemType or where the configured tbale is the same as the itemType.
     *
     * !!! Might return incorrect results for complex configurations !!!
     * Try to set the indexingConfiguration directly when using the updateItem()
     * method in such situations.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param int $rootPageId The configuration's page tree's root page id.
     *      Optional, not needed for all types.
     * @return string The indexing configuration's name to use when indexing
     * @deprecated since 6.1 will be removed in 7.0
     * Use getIndexingConfigurationsByItem() now, which behaves
     * almost the same way but returns an array of configurations
     */
    protected function getIndexingConfigurationByItem(
        $itemType,
        $itemUid,
        $rootPageId = null
    ) {
        GeneralUtility::logDeprecatedFunction();
        $indexingConfigurationName = '';

        $configurations = $this->getIndexingConfigurationsByItem($itemType, $rootPageId);
        if (count($configurations) > 0) {
            $indexingConfigurationName = $configurations[0];
        }

        return $indexingConfigurationName;
    }

    /**
     * Gets the indexing configurations to use for an item.
     * Multiple configurations for a certain item type (table) might be available.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param int $rootPageId The configuration's page tree's root page id.
     *      Optional, not needed for all types.
     * @return array<string> The indexing configurations names to use when indexing
     * @deprecated since 6.1 will be removed in 7.0
     */
    protected function getIndexingConfigurationsByItem(
        $itemType,
        $rootPageId = null
    ) {
        GeneralUtility::logDeprecatedFunction();

        $possibleIndexingConfigurationNames = [];

        if (!is_null($rootPageId)) {
            // get configuration for the root's branch
            $solrConfiguration = Util::getSolrConfigurationFromPageId($rootPageId);
            $possibleIndexingConfigurationNames = $solrConfiguration->getIndexQueueConfigurationNamesByTableName($itemType);
        }

        return $possibleIndexingConfigurationNames;
    }

    /**
     * Marks an item as needing (re)indexing.
     *
     * Like with Solr itself, there's no add method, just a simple update method
     * that handles the adds, too.
     *
     * The method creates or updates the index queue items for all related rootPageIds.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param int $forcedChangeTime The change time for the item if set, otherwise
     *          value from getItemChangedTime() is used.
     */
    public function updateItem($itemType, $itemUid, $forcedChangeTime = 0)
    {
        $rootPageIds = $this->rootPageResolver->getResponsibleRootPageIds($itemType, $itemUid);
        foreach ($rootPageIds as $rootPageId) {
            $skipInvalidRootPage = $rootPageId === 0;
            if ($skipInvalidRootPage) {
                continue;
            }

            $solrConfiguration = Util::getSolrConfigurationFromPageId($rootPageId);
            $indexingConfiguration = $this->recordService->getIndexingConfigurationName($itemType, $itemUid, $solrConfiguration);
            $itemInQueueForRootPage = $this->containsItemWithRootPageId($itemType, $itemUid, $rootPageId);
            if ($itemInQueueForRootPage) {
                // update the existing queue item
                $this->updateExistingItem($itemType, $itemUid, $indexingConfiguration, $rootPageId, $forcedChangeTime);
            } else {
                // add the item since it's not in the queue yet
                $this->addNewItem($itemType, $itemUid, $indexingConfiguration, $rootPageId);
            }
        }
    }

    /**
     * Finds indexing errors for the current site
     *
     * @param Site $site
     * @return array Error items for the current site's Index Queue
     */
    public function getErrorsBySite(Site $site)
    {
        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, item_type, item_uid, errors',
            'tx_solr_indexqueue_item',
            'errors NOT LIKE "" AND root = ' . $site->getRootPageId()
        );
    }

    /**
     * Resets all the errors for all index queue items.
     *
     * @return mixed
     */
    public function resetAllErrors()
    {
        return $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_solr_indexqueue_item',
            'errors NOT LIKE ""',
            ['errors' => '']
        );
    }

    /**
     * Updates an existing queue entry by $itemType $itemUid and $rootPageId.
     *
     * @param string $itemType  The item's type, usually a table name.
     * @param int $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $indexingConfiguration The name of the related indexConfiguration
     * @param int $rootPageId The uid of the rootPage
     * @param int $forcedChangeTime The forced change time that should be used for updating
     */
    protected function updateExistingItem($itemType, $itemUid, $indexingConfiguration, $rootPageId, $forcedChangeTime)
    {
        // update if that item is in the queue already
        $changes = [
            'changed' => ($forcedChangeTime > 0) ? $forcedChangeTime : $this->getItemChangedTime($itemType, $itemUid)
        ];

        if (!empty($indexingConfiguration)) {
            $changes['indexing_configuration'] = $indexingConfiguration;
        }

        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType, 'tx_solr_indexqueue_item') .
            ' AND item_uid = ' . (int)$itemUid . ' AND root = ' . (int)$rootPageId,
            $changes);
    }

    /**
     * Adds an item to the index queue.
     *
     * Not meant for public use.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $indexingConfiguration The item's indexing configuration to use.
     *      Optional, overwrites existing / determined configuration.
     * @return void
     */
    private function addNewItem($itemType, $itemUid, $indexingConfiguration, $rootPageId)
    {
        $additionalRecordFields = '';
        if ($itemType == 'pages') {
            $additionalRecordFields = ', doktype, uid';
        }

        $record = $this->getRecordCached($itemType, $itemUid, $additionalRecordFields);

        if (empty($record) || ($itemType == 'pages' && !Util::isAllowedPageType($record, $indexingConfiguration))) {
            return;
        }

        $item = [
            'root' => $rootPageId,
            'item_type' => $itemType,
            'item_uid' => $itemUid,
            'changed' => $this->getItemChangedTime($itemType, $itemUid),
            'errors' => ''
        ];

        // make a backup of the current item
        $item['indexing_configuration'] = $indexingConfiguration;
        $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_solr_indexqueue_item', $item);
    }

    /**
     * Get record to be added in addNewItem
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param string $additionalRecordFields for sql-query
     *
     * @return array|NULL
     */
    protected function getRecordCached($itemType, $itemUid, $additionalRecordFields)
    {
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, 'cache_runtime');
        $cacheId = md5('Queue' . ':' . 'getRecordCached' . ':' . $itemType . ':' . $itemUid . ':' . 'pid' . $additionalRecordFields);

        $record = $cache->get($cacheId);
        if (empty($record)) {
            $record = BackendUtility::getRecord($itemType, $itemUid, 'pid' . $additionalRecordFields);
            $cache->set($cacheId, $record);
        }

        return $record;
    }

    /**
     * Determines the time for when an item should be indexed. This timestamp
     * is then stored in the changed column in the Index Queue.
     *
     * The changed timestamp usually is now - time(). For records which are set
     * to published at a later time, this timestamp is the start time. So if a
     * future start time has been set, that will be used to delay indexing
     * of an item.
     *
     * @param string $itemType The item's table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return int Timestamp of the item's changed time or future start time
     */
    protected function getItemChangedTime($itemType, $itemUid)
    {
        $itemTypeHasStartTimeColumn = false;
        $changedTimeColumns = $GLOBALS['TCA'][$itemType]['ctrl']['tstamp'];
        $startTime = 0;
        $pageChangedTime = 0;

        if (!empty($GLOBALS['TCA'][$itemType]['ctrl']['enablecolumns']['starttime'])) {
            $itemTypeHasStartTimeColumn = true;
            $changedTimeColumns .= ', ' . $GLOBALS['TCA'][$itemType]['ctrl']['enablecolumns']['starttime'];
        }
        if ($itemType == 'pages') {
            // does not carry time information directly, but needed to support
            // canonical pages
            $changedTimeColumns .= ', content_from_pid';
        }

        $record = BackendUtility::getRecord($itemType, $itemUid, $changedTimeColumns);
        $itemChangedTime = $record[$GLOBALS['TCA'][$itemType]['ctrl']['tstamp']];

        if ($itemTypeHasStartTimeColumn) {
            $startTime = $record[$GLOBALS['TCA'][$itemType]['ctrl']['enablecolumns']['starttime']];
        }

        if ($itemType == 'pages') {
            $record['uid'] = $itemUid;
            // overrule the page's last changed time with the most recent
            //content element change
            $pageChangedTime = $this->getPageItemChangedTime($record);
        }

        $localizationsChangedTime = $this->getLocalizableItemChangedTime($itemType, $itemUid);

        // if start time exists and start time is higher than last changed timestamp
        // then set changed to the future start time to make the item
        // indexed at a later time
        $changedTime = max(
            $itemChangedTime,
            $pageChangedTime,
            $localizationsChangedTime,
            $startTime
        );

        return $changedTime;
    }

    /**
     * Gets the most recent changed time of a page's content elements
     *
     * @param array $page Partial page record
     * @return int Timestamp of the most recent content element change
     */
    protected function getPageItemChangedTime(array $page)
    {
        if (!empty($page['content_from_pid'])) {
            // canonical page, get the original page's last changed time
            $pageContentLastChangedTime = $this->getPageItemChangedTime(['uid' => $page['content_from_pid']]);
        } else {
            $pageContentLastChangedTime = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                'MAX(tstamp) AS changed_time',
                'tt_content',
                'pid = ' . (int)$page['uid']
            );
            $pageContentLastChangedTime = $pageContentLastChangedTime['changed_time'];
        }

        return $pageContentLastChangedTime;
    }

    /**
     * Gets the most recent changed time for an item taking into account
     * localized records.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return int Timestamp of the most recent content element change
     */
    protected function getLocalizableItemChangedTime($itemType, $itemUid)
    {
        $localizedChangedTime = 0;

        if (isset($GLOBALS['TCA'][$itemType]['ctrl']['transOrigPointerField'])) {
            // table is localizable
            $translationOriginalPointerField = $GLOBALS['TCA'][$itemType]['ctrl']['transOrigPointerField'];

            $itemUid = intval($itemUid);
            $localizedChangedTime = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
                'MAX(tstamp) AS changed_time',
                $itemType,
                "uid = $itemUid OR $translationOriginalPointerField = $itemUid"
            );
            $localizedChangedTime = $localizedChangedTime['changed_time'];
        }

        return $localizedChangedTime;
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     */
    public function containsItem($itemType, $itemUid)
    {
        $itemIsInQueue = (boolean)$GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType,
                'tx_solr_indexqueue_item') .
            ' AND item_uid = ' . (int)$itemUid
        );

        return $itemIsInQueue;
    }

    /**
     * Checks whether the Index Queue contains a specific item.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @param integer $rootPageId
     * @return bool TRUE if the item is found in the queue, FALSE otherwise
     */
    public function containsItemWithRootPageId($itemType, $itemUid, $rootPageId)
    {
        $itemIsInQueue = (boolean)$GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType,
                'tx_solr_indexqueue_item') .
            ' AND item_uid = ' . (int)$itemUid . ' AND root = ' . (int)$rootPageId
        );

        return $itemIsInQueue;
    }

    /**
     * Checks whether the Index Queue contains a specific item that has been
     * marked as indexed.
     *
     * @param string $itemType The item's type, usually a table name.
     * @param string $itemUid The item's uid, usually an integer uid, could be a
     *      different value for non-database-record types.
     * @return bool TRUE if the item is found in the queue and marked as
     *      indexed, FALSE otherwise
     */
    public function containsIndexedItem($itemType, $itemUid)
    {
        $itemIsInQueue = (boolean)$GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
            'uid',
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType,
                'tx_solr_indexqueue_item') .
            ' AND item_uid = ' . (int)$itemUid .
            ' AND indexed > 0'
        );

        return $itemIsInQueue;
    }

    /**
     * Removes an item from the Index Queue.
     *
     * @param string $itemType The type of the item to remove, usually a table name.
     * @param int $itemUid The uid of the item to remove
     */
    public function deleteItem($itemType, $itemUid)
    {
        $uidList = [];

        // get the item uids to use them in the deletes afterwards
        $items = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid',
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType,
                'tx_solr_indexqueue_item') .
            ' AND item_uid = ' . intval($itemUid)
        );

        if (count($items)) {
            foreach ($items as $item) {
                $uidList[] = $item['uid'];
            }

            $GLOBALS['TYPO3_DB']->exec_DELETEquery(
                'tx_solr_indexqueue_item',
                'uid IN(' . implode(',', $uidList) . ')'
            );
            $GLOBALS['TYPO3_DB']->exec_DELETEquery(
                'tx_solr_indexqueue_indexing_property',
                'item_id IN(' . implode(',', $uidList) . ')'
            );
        }
    }

    /**
     * Removes all items of a certain type from the Index Queue.
     *
     * @param string $itemType The type of items to remove, usually a table name.
     */
    public function deleteItemsByType($itemType)
    {
        $uidList = [];

        // get the item uids to use them in the deletes afterwards
        $items = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid',
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr(
                $itemType,
                'tx_solr_indexqueue_item'
            )
        );

        if (count($items)) {
            foreach ($items as $item) {
                $uidList[] = $item['uid'];
            }

            $GLOBALS['TYPO3_DB']->exec_DELETEquery(
                'tx_solr_indexqueue_item',
                'uid IN(' . implode(',', $uidList) . ')'
            );
            $GLOBALS['TYPO3_DB']->exec_DELETEquery(
                'tx_solr_indexqueue_indexing_property',
                'item_id IN(' . implode(',', $uidList) . ')'
            );
        }
    }

    /**
     * Removes all items of a certain site from the Index Queue. Accepts an
     * optional parameter to limit the deleted items by indexing configuration.
     *
     * @param Site $site The site to remove items for.
     * @param string $indexingConfigurationName Name of a specific indexing
     *      configuration
     */
    public function deleteItemsBySite(
        Site $site,
        $indexingConfigurationName = ''
    ) {
        $rootPageConstraint = 'tx_solr_indexqueue_item.root = ' . $site->getRootPageId();

        $indexingConfigurationConstraint = '';
        if (!empty($indexingConfigurationName)) {
            $indexingConfigurationConstraint =
                ' AND tx_solr_indexqueue_item.indexing_configuration = \'' .
                $indexingConfigurationName . '\'';
        }

        DatabaseUtility::transactionStart();
        try {
            // reset Index Queue
            $result = $GLOBALS['TYPO3_DB']->exec_DELETEquery(
                'tx_solr_indexqueue_item',
                $rootPageConstraint . $indexingConfigurationConstraint
            );
            if (!$result) {
                throw new \RuntimeException(
                    'Failed to reset Index Queue for site ' . $site->getLabel(),
                    1412986560
                );
            }

            // reset Index Queue Properties
            $indexQueuePropertyResetQuery = '
                DELETE tx_solr_indexqueue_indexing_property.*
                FROM tx_solr_indexqueue_indexing_property
                INNER JOIN tx_solr_indexqueue_item
                    ON tx_solr_indexqueue_item.uid = tx_solr_indexqueue_indexing_property.item_id
                    AND ' .
                $rootPageConstraint .
                $indexingConfigurationConstraint;

            $result = $GLOBALS['TYPO3_DB']->sql_query($indexQueuePropertyResetQuery);
            if (!$result) {
                throw new \RuntimeException(
                    'Failed to reset Index Queue properties for site ' . $site->getLabel(),
                    1412986604
                );
            }

            DatabaseUtility::transactionCommit();
        } catch (\RuntimeException $e) {
            DatabaseUtility::transactionRollback();
        }
    }

    /**
     * Removes all items from the Index Queue.
     *
     */
    public function deleteAllItems()
    {
        $GLOBALS['TYPO3_DB']->exec_TRUNCATEquery('tx_solr_indexqueue_item', '');
    }

    /**
     * Gets a single Index Queue item by its uid.
     *
     * @param int $itemId Index Queue item uid
     * @return Item The request Index Queue item or NULL
     *      if no item with $itemId was found
     */
    public function getItem($itemId)
    {
        $item = null;

        $indexQueueItemRecord = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_solr_indexqueue_item',
            'uid = ' . intval($itemId)
        );

        if (count($indexQueueItemRecord) == 1) {
            $indexQueueItemRecord = $indexQueueItemRecord[0];
            $item = GeneralUtility::makeInstance(
                Item::class,
                $indexQueueItemRecord
            );
        }

        return $item;
    }

    /**
     * Gets Index Queue items by type and uid.
     *
     * @param string $itemType item type, usually  the table name
     * @param int $itemUid item uid
     * @return Item[] An array of items matching $itemType and $itemUid
     */
    public function getItems($itemType, $itemUid)
    {
        $indexQueueItemRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_solr_indexqueue_item',
            'item_type = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($itemType,
                'tx_solr_indexqueue_item') .
            ' AND item_uid = ' . intval($itemUid)
        );

        return $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
    }

    /**
     * Gets number of Index Queue items for a specific site / indexing configuration
     * optional parameter to limit the counted items by indexing configuration.
     *
     * @param Site $site The site to search for.
     * @param string $indexingConfigurationName name of a specific indexing
     *      configuration
     * @deprecated since 6.1 will be removed in 7.0 use getStatisticsBySite()->getTotalCount() please
     * @return int Number of items
     */
    public function getItemsCountBySite(Site $site, $indexingConfigurationName = '')
    {
        GeneralUtility::logDeprecatedFunction();
        return (int) $this->getStatisticsBySite($site, $indexingConfigurationName)->getTotalCount();
    }

    /**
     * Gets number of unprocessed Index Queue items for a specific site / indexing configuration
     * optional parameter to limit the counted items by indexing configuration.
     *
     * @param Site $site The site to search for.
     * @param string $indexingConfigurationName name of a specific indexing
     *      configuration
     * @deprecated since 6.1 will be removed in 7.0 use getStatisticsBySite()->getPendingCount() please
     * @return int Number of items.
     */
    public function getRemainingItemsCountBySite(Site $site, $indexingConfigurationName = '')
    {
        GeneralUtility::logDeprecatedFunction();
        return (int) $this->getStatisticsBySite($site, $indexingConfigurationName)->getPendingCount();
    }

    /**
     * Returns the number of items for all queues.
     *
     * @return int
     */
    public function getAllItemsCount()
    {
        return $this->getItemCount();
    }

    /**
     * @param string $where
     * @return int
     */
    private function getItemCount($where = '1=1')
    {
        /**  @var $db \TYPO3\CMS\Core\Database\DatabaseConnection */
        $db = $GLOBALS['TYPO3_DB'];

        return (int)$db->exec_SELECTcountRows('uid', 'tx_solr_indexqueue_item', $where);
    }

    /**
     * Extracts the number of pending, indexed and erroneous items from the
     * Index Queue.
     *
     * @param Site $site
     * @param string $indexingConfigurationName
     *
     * @return QueueStatistic
     */
    public function getStatisticsBySite(Site $site, $indexingConfigurationName = '')
    {
        $indexingConfigurationConstraint = $this->buildIndexConfigurationConstraint($indexingConfigurationName);
        $where = 'root = ' . (int)$site->getRootPageId() . $indexingConfigurationConstraint;

        $indexQueueStats = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'indexed < changed as pending,'
            . '(errors not like "") as failed,'
            . 'COUNT(*) as count',
            'tx_solr_indexqueue_item',
            $where,
            'pending, failed'
        );
            /** @var $statistic QueueStatistic */
        $statistic = GeneralUtility::makeInstance(QueueStatistic::class);

        foreach ($indexQueueStats as $row) {
            if ($row['failed'] == 1) {
                $statistic->setFailedCount((int) $row['count']);
            } elseif ($row['pending'] == 1) {
                $statistic->setPendingCount((int) $row['count']);
            } else {
                $statistic->setSuccessCount((int) $row['count']);
            }
        }

        return $statistic;
    }

    /**
     * Build a database constraint that limits to a certain indexConfigurationName
     *
     * @param string $indexingConfigurationName
     * @return string
     */
    protected function buildIndexConfigurationConstraint($indexingConfigurationName)
    {
        $indexingConfigurationConstraint = '';
        if (!empty($indexingConfigurationName)) {
            $indexingConfigurationConstraint = ' AND indexing_configuration = \'' . $indexingConfigurationName . '\'';
            return $indexingConfigurationConstraint;
        }
        return $indexingConfigurationConstraint;
    }

    /**
     * Gets $limit number of items to index for a particular $site.
     *
     * @param Site $site TYPO3 site
     * @param int $limit Number of items to get from the queue
     * @return Item[] Items to index to the given solr server
     */
    public function getItemsToIndex(Site $site, $limit = 50)
    {
        $itemsToIndex = [];

        // determine which items to index with this run
        $indexQueueItemRecords = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_solr_indexqueue_item',
            'root = ' . $site->getRootPageId() .
            ' AND changed > indexed' .
            ' AND changed <= ' . time() .
            ' AND errors = \'\'',
            '',
            'indexing_priority DESC, changed DESC, uid DESC',
            intval($limit)
        );
        if (!empty($indexQueueItemRecords)) {
            // convert queued records to index queue item objects
            $itemsToIndex = $this->getIndexQueueItemObjectsFromRecords($indexQueueItemRecords);
        }

        return $itemsToIndex;
    }

    /**
     * Creates an array of ApacheSolrForTypo3\Solr\IndexQueue\Item objects from an array of
     * index queue records.
     *
     * @param array $indexQueueItemRecords Array of plain index queue records
     * @return array Array of ApacheSolrForTypo3\Solr\IndexQueue\Item objects
     */
    protected function getIndexQueueItemObjectsFromRecords(
        array $indexQueueItemRecords
    ) {
        $indexQueueItems = [];
        $tableUids = [];
        $tableRecords = [];

        // grouping records by table
        foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
            $tableUids[$indexQueueItemRecord['item_type']][] = $indexQueueItemRecord['item_uid'];
        }

        // fetching records by table, saves us a lot of single queries
        foreach ($tableUids as $table => $uids) {
            $uidList = implode(',', $uids);
            $records = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                '*',
                $table,
                'uid IN(' . $uidList . ')',
                '', '', '', // group, order, limit
                'uid'
            );
            $tableRecords[$table] = $records;

            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessFetchRecordsForIndexQueueItem'])) {
                $params = ['table' => $table, 'uids' => $uids, 'tableRecords' => &$tableRecords];
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessFetchRecordsForIndexQueueItem'] as $reference) {
                    GeneralUtility::callUserFunction($reference, $params, $this);
                }
                unset($params);
            }
        }

        // creating index queue item objects and assigning / mapping
        // records to index queue items
        foreach ($indexQueueItemRecords as $indexQueueItemRecord) {
            if (isset($tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']])) {
                $indexQueueItems[] = GeneralUtility::makeInstance(
                    Item::class,
                    $indexQueueItemRecord,
                    $tableRecords[$indexQueueItemRecord['item_type']][$indexQueueItemRecord['item_uid']]
                );
            } else {
                $this->logger->log(
                    SolrLogManager::ERROR,
                    'Record missing for Index Queue item. Item removed.',
                    [
                        $indexQueueItemRecord
                    ]
                );
                $this->deleteItem($indexQueueItemRecord['item_type'],
                    $indexQueueItemRecord['item_uid']);
            }
        }

        return $indexQueueItems;
    }

    /**
     * Marks an item as failed and causes the indexer to skip the item in the
     * next run.
     *
     * @param int|Item $item Either the item's Index Queue uid or the complete item
     * @param string $errorMessage Error message
     */
    public function markItemAsFailed($item, $errorMessage = '')
    {
        if ($item instanceof Item) {
            $itemUid = $item->getIndexQueueUid();
        } else {
            $itemUid = (int)$item;
        }

        if (empty($errorMessage)) {
            // simply set to "TRUE"
            $errorMessage = '1';
        }

        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_solr_indexqueue_item',
            'uid = ' . $itemUid,
            [
                'errors' => $errorMessage
            ]
        );
    }

    /**
     * Sets the timestamp of when an item last has been indexed.
     *
     * @param Item $item
     */
    public function updateIndexTimeByItem(Item $item)
    {
        $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
            'tx_solr_indexqueue_item',
            'uid = ' . (int)$item->getIndexQueueUid(),
            ['indexed' => time()]
        );
    }
}
