<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Marc Bastian Heinrichs <mbh@mbh-software.de>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * A class that monitors Extbase domain objects for changing and removal so that the
 * changed record gets passed to the index queue to update the according index document
 * and invisible or deleted records are handled by the garbage collector.
 *
 * @package TYPO3
 * @subpackage solr
 */
class DomainObjectObserver
{

    /**
     * @var \ApacheSolrForTypo3\Solr\IndexQueue\Queue
     */
    protected $indexQueue;

    /**
     * @var \ApacheSolrForTypo3\Solr\IndexQueue\DomainObjectObserverRegistry
     */
    protected $domainObjectObserverRegistry;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper
     */
    protected $dataMapper;

    /**
     * @var array
     */
    static protected $classToTableNameMap = [];

    /**
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
     * Reference to the configuration manager
     *
     * @var ConfigurationAwareRecordService
     */
    protected $configurationAwareRecordService;

    /**
     * Unsupported tables
     *
     * @var array
     */
    protected $ignoredTableNames = ['pages', 'tt_content'];

    /**
     * @var Dispatcher
     */
    protected $signalSlotDispatcher;

    /**
     * inject the dataMapper
     *
     * @param \TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper $dataMapper
     * @return void
     */
    public function injectDataMapper(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper $dataMapper)
    {
        $this->dataMapper = $dataMapper;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->indexQueue = GeneralUtility::makeInstance(\ApacheSolrForTypo3\Solr\IndexQueue\Queue::class);
        $this->domainObjectObserverRegistry = GeneralUtility::makeInstance(\ApacheSolrForTypo3\Solr\IndexQueue\DomainObjectObserverRegistry::class);
        $this->tcaService = GeneralUtility::makeInstance(TCAService::class);
        $this->rootPageResolver = GeneralUtility::makeInstance(RootPageResolver::class);
        $this->configurationAwareRecordService = GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        $this->signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);
    }

    /**
     * @param DomainObjectInterface $object
     */
    public function afterInsertObject($object)
    {
        // not needed since the insert only set the uid of the record and all data fields are written by an update
        // call afterwards anyway.
    }

    /**
     * Affects new and updated objects
     *
     * @param DomainObjectInterface $object
     */
    public function afterUpdateObject($object)
    {
        $forcedChangeTime = 0;
        list($_, $object, $forcedChangeTime) = $this->signalSlotDispatcher->dispatch(__CLASS__, 'preUpdateCheck', [$this, $object, $forcedChangeTime]);

        if (!$this->isDomainObjectRegistered($object)) {
            return;
        }

        $recordTableName = $this->getTableName($object);
        $recordUid = $object->getUid();
        $recordPageId = $object->getPid();

        $configurationPageId = $this->getConfigurationPageId($recordTableName, $recordPageId, $recordUid);

        $solrConfiguration = Util::getSolrConfigurationFromPageId($configurationPageId);

        if ($this->ignoreRecord($recordTableName, $recordUid, $solrConfiguration)) {
            return;
        }

        $record = $this->configurationAwareRecordService->getRecord($recordTableName, $recordUid, $solrConfiguration);

        if (empty($record)) {
            if ($this->indexQueue->containsItem($recordTableName, $recordUid)) {
                $this->removeFromIndexAndQueue($recordTableName, $recordUid);
            }
            return;
        }

        //  run garbage first (e.g. important when the starttime changed)
        if ($this->tcaService->isHidden($recordTableName, $record)
            || $this->isInvisibleByStartOrEndtime($recordTableName, $record)
        ) {
            $this->removeFromIndexAndQueue($recordTableName, $recordUid);
        }
        // then update or readd the item
        if ($this->tcaService->isEnabledRecord($recordTableName, $record)) {
            $this->indexQueue->updateItem($recordTableName, $recordUid, $forcedChangeTime);
        }
    }

    /**
     * @param DomainObjectInterface $object
     */
    public function afterRemoveObject($object)
    {
        if (!$this->isDomainObjectRegistered($object)) {
            return;
        }

        $recordTableName = $this->getTableName($object);
        $recordUid = $object->getUid();
        $recordPageId = $object->getPid();

        $solrConfiguration = Util::getSolrConfigurationFromPageId($recordPageId);

        if ($this->ignoreRecord($recordTableName, $recordUid, $solrConfiguration)) {
            return;
        }

        $this->removeFromIndexAndQueue($recordTableName, $recordUid);
    }

    /**
     * @param $recordTableName
     * @param $recordUid
     * @param $solrConfiguration TypoScriptConfiguration
     * @return bool
     */
    protected function ignoreRecord($recordTableName, $recordUid, $solrConfiguration)
    {
        $ignore = false;
        if (in_array($recordTableName, $this->ignoredTableNames)) {
            $ignore = true;
        } elseif (Util::isDraftRecord($recordTableName, $recordUid)) {
            // skip workspaces: index only LIVE workspace
            $ignore = true;
        } else {
            $isMonitoredRecord = $solrConfiguration->getIndexQueueIsMonitoredTable($recordTableName);

            if (!$isMonitoredRecord) {
                // when it is a non monitored record, we can skip it.
                $ignore = true;
            }
        }
        return $ignore;
    }

    /**
     * @param $object
     * @return string
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    protected function getTableName($object)
    {
        $className = get_class($object);
        if (!isset(self::$classToTableNameMap[$className])) {
            // we rely on the datamap here, because it is already correct build in extbase backend
            // just before the signal is called
            $dataMap = $this->dataMapper->getDataMap($className);
            self::$classToTableNameMap[$className] = $dataMap->getTableName();
        }
        return self::$classToTableNameMap[$className];
    }

    /**
     * @param $object
     * @return bool
     */
    protected function isDomainObjectRegistered($object)
    {
        return $this->domainObjectObserverRegistry->isRegistered(get_class($object));
    }

    /**
     * TODO - copied and adapted (since pages and tt_content are not supported anyway) from GarbageCollector
     *
     * Check if a record is getting invisible due to changes in start or endtime. In addition it is checked that the related
     * queue item was marked as indexed.
     *
     * @param string $table
     * @param array $record
     * @return bool
     */
    protected function isInvisibleByStartOrEndtime($table, $record)
    {
        return ($this->tcaService->isStartTimeInFuture($table, $record) || $this->tcaService->isEndTimeInPast($table, $record)) &&
            $this->indexQueue->containsIndexedItem($table, $record['uid']);
    }

    /**
     * TODO - copied from RecordMonitor
     *
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
     * TODO - copied from RecordMonitor
     *
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
}
