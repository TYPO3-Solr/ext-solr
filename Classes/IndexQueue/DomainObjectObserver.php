<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;


use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Event\BeforeDomainObjectObserverUpdate;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Util;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

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
     * Constructor
     */
    public function __construct(
        Queue $indexQueue = null,
        DomainObjectObserverRegistry $domainObjectObserverRegistry = null,
        TCAService $TCAService = null,
        RootPageResolver $rootPageResolver = null,
        ConfigurationAwareRecordService $recordService = null,
        FrontendEnvironment $frontendEnvironment = null,
        DataMapper $dataMapper = null
    )
    {
        $this->indexQueue = $indexQueue ?? GeneralUtility::makeInstance(Queue::class);
        $this->domainObjectObserverRegistry = $domainObjectObserverRegistry ?? GeneralUtility::makeInstance(DomainObjectObserverRegistry::class);
        $this->tcaService = $TCAService ?? GeneralUtility::makeInstance(TCAService::class);
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->configurationAwareRecordService = $recordService ?? GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->dataMapper = $dataMapper ?? $objectManager->get(DataMapper::class);
    }

    /**
     * Affects new and updated objects
     *
     * @param DomainObjectInterface $object
     */
    public function afterUpdateObject($object)
    {
        $forcedChangeTime = 0;

        // TODO: has to be changed when DI is enabled
        $eventDispatcher = GeneralUtility::getContainer()->get(EventDispatcherInterface::class);
        $event = $eventDispatcher->dispatch(
            new BeforeDomainObjectObserverUpdate($object, $forcedChangeTime)
        );
        $object = $event->getObject();
        $forcedChangeTime = $event->getForcedChangeTime();

        if (!$this->isDomainObjectRegistered($object)) {
            return;
        }

        $recordTable = $this->getTableName($object);
        $recordUid = $object->getUid();

        try {
            $rootPageIds = $this->rootPageResolver->getResponsibleRootPageIds($recordTable, $recordUid);
            if (empty($rootPageIds)) {
                $this->removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid);
                return;
            }
        } catch ( \InvalidArgumentException $e) {
            $this->removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid);
            return;
        }

        foreach ($rootPageIds as $configurationPageId) {
            $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($configurationPageId);

            if ($this->ignoreRecord($recordTable, $recordUid, $solrConfiguration)) {
                return;
            }

            $record = $this->configurationAwareRecordService->getRecord($recordTable, $recordUid,
                $solrConfiguration);

            if (empty($record)) {
                $this->removeFromIndexAndQueueWhenItemInQueue($recordTable, $recordUid);
                return;
            }

            //  run garbage first (e.g. important when the starttime changed)
            if ($this->tcaService->isHidden($recordTable, $record)
                || $this->isInvisibleByStartOrEndtime($recordTable, $record)
            ) {
                $this->removeFromIndexAndQueue($recordTable, $recordUid);
            }
            // then update or readd the item
            if ($this->tcaService->isEnabledRecord($recordTable, $record)) {
                $this->indexQueue->updateItem($recordTable, $recordUid, $forcedChangeTime);
            }
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

        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($recordPageId);

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
        $garbageCollector = GeneralUtility::makeInstance(\ApacheSolrForTypo3\Solr\GarbageCollector::class);
        $garbageCollector->collectGarbage($recordTable, $recordUid);
    }
    /**
     * TODO - copied from RecordMonitor
     *
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
}
