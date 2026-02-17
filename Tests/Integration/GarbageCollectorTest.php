<?php

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

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\ItemInterface;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration as ExtSolrExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This testcase is used to check if the GarbageCollector can delete garbage from the
 * solr server as expected
 */
class GarbageCollectorTest extends IntegrationTestBase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension',
    ];

    protected RecordMonitor $recordMonitor;
    protected DataHandler $dataHandler;
    protected Queue $indexQueue;
    protected GarbageCollector $garbageCollector;
    protected Indexer $indexer;
    protected ExtensionConfiguration $extensionConfiguration;
    protected EventQueueItemRepository $eventQueue;
    protected BackendUserAuthentication $backendUser;
    protected ExtSolrExtensionConfiguration $extSolrExtensionConfigurationObject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->extSolrExtensionConfigurationObject = new class ([]) extends ExtSolrExtensionConfiguration implements SingletonInterface {
            public function setConfiguration(array $configuration)
            {
                $this->configuration = $configuration;
            }
        };
        GeneralUtility::setSingletonInstance(
            ExtSolrExtensionConfiguration::class,
            $this->extSolrExtensionConfigurationObject,
        );

        $this->recordMonitor = GeneralUtility::makeInstance(RecordMonitor::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->garbageCollector = GeneralUtility::makeInstance(GarbageCollector::class);
        $this->indexer = GeneralUtility::makeInstance(Indexer::class);
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->eventQueue = GeneralUtility::makeInstance(EventQueueItemRepository::class);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        // fake that a backend user is logged in
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sites_setup_and_data_set/be_users.csv');
        $this->backendUser = $this->setUpBackendUser(1);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        unset($GLOBALS['TYPO3_CONF_VARS']);
        $this->extensionConfiguration->setAll([]);
        unset(
            $this->recordMonitor,
            $this->dataHandler,
            $this->indexQueue,
            $this->garbageCollector,
            $this->indexer,
            $this->extensionConfiguration,
            $this->extSolrExtensionConfigurationObject,
            $this->eventQueue,
            $this->backendUser,
            $GLOBALS['LANG'],
        );
        parent::tearDown();
    }

    protected function setExtensionsMonitoringType(int $monitoringType): void
    {
        $extSolrExtConf = $this->extensionConfiguration->get('solr');
        $extSolrExtConf['monitoringType'] = $monitoringType;
        $this->extensionConfiguration->set('solr', $extSolrExtConf);
        // @phpstan-ignore method.notFound
        $this->extSolrExtensionConfigurationObject->setConfiguration($extSolrExtConf);
    }

    protected function assertEmptyIndexQueue(): void
    {
        self::assertEquals(0, $this->indexQueue->getAllItemsCount(), 'Index queue is not empty as expected');
    }

    protected function assertNotEmptyIndexQueue(): void
    {
        self::assertGreaterThan(
            0,
            $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to be not empty.',
        );
    }

    protected function assertIndexQueueContainsItemAmount(int $amount): void
    {
        $itemsInQueue = $this->indexQueue->getAllItemsCount();
        self::assertEquals(
            $amount,
            $itemsInQueue,
            'Index queue contains ' . $itemsInQueue . ' but was expected to contain ' . $amount . ' items.',
        );
    }

    protected function assertEmptyEventQueue(): void
    {
        self::assertEquals(0, $this->eventQueue->count(), 'Event queue is not empty as expected');
    }

    protected function assertEventQueueContainsItemAmount(int $amount): void
    {
        $itemsInQueue = $this->eventQueue->count();
        self::assertEquals(
            $amount,
            $itemsInQueue,
            'Event queue contains ' . $itemsInQueue . ' but was expected to contain ' . $amount . ' items.',
        );
    }

    #[Test]
    public function queueItemStaysWhenOverlayIsSetToHidden(): void
    {
        $this->prepareQueueItemStaysWhenOverlayIsSetToHidden();

        // index queue not modified
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function queueItemStaysWhenOverlayIsSetToHiddenInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $this->prepareQueueItemStaysWhenOverlayIsSetToHidden();
        $this->assertEventQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases:
     * - queueItemStaysWhenOverlayIsSetToHidden
     * - queueItemStaysWhenOverlayIsSetToHiddenInDelayedProcessingMode
     */
    protected function prepareQueueItemStaysWhenOverlayIsSetToHidden(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/queue_item_stays_when_overlay_set_to_hidden.csv');

        $this->assertIndexQueueContainsItemAmount(1);

        $this->garbageCollector->processDatamap_afterDatabaseOperations('update', 'pages', 2, ['hidden' => 1], $this->dataHandler);
    }

    #[Test]
    public function canQueueAPageAndRemoveItWithTheGarbageCollector(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/subpage.csv');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 2, [], $dataHandler);

        // we expect that one item is now in the solr server
        $this->assertIndexQueueContainsItemAmount(1);

        $this->garbageCollector->collectGarbage('pages', 2);

        // finally we expect that the index is empty again
        $this->assertEmptyIndexQueue();
    }

    #[Test]
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet(): void
    {
        $this->prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet();

        // finally we expect that the index is empty again because the root page with "extendToSubPages" has been set to
        // hidden = 1
        $this->assertEmptyIndexQueue();
    }

    #[Test]
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $this->prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet();
        $this->assertEventQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertEmptyIndexQueue();
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases:
     * - canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet
     * - canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetInDelayedProcessingMode
     */
    protected function prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/page_hidden_and_extendtosubpages.csv');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        $this->indexQueue->updateItem('pages', 2);
        $this->indexQueue->updateItem('pages', 10);
        $this->indexQueue->updateItem('pages', 100);

        // we expected that three pages are now in the index
        $this->assertIndexQueueContainsItemAmount(3);

        // simulate the database change and build a faked changeset
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['hidden' => 1], ['uid' => 2]);

        $changeSet = ['hidden' => 1];

        $dataHandler = $this->dataHandler;
        $this->garbageCollector->processDatamap_afterDatabaseOperations('update', 'pages', 2, $changeSet, $dataHandler);
    }

    #[Test]
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages(): void
    {
        $this->prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages();

        // finally we expect that the index is empty again because the root page with "extendToSubPages" has been set to
        // hidden = 1
        $this->assertEmptyIndexQueue();
    }

    #[Test]
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpagesInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $this->prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages();
        $this->assertEventQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertEmptyIndexQueue();
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases:
     * - canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages
     * - canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpagesInDelayedProcessingMode
     */
    protected function prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages(): void
    {
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/page_hidden_and_extendtosubpages_multiple_subpages.csv');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        $this->indexQueue->updateItem('pages', 2);
        $this->indexQueue->updateItem('pages', 10);
        $this->indexQueue->updateItem('pages', 11);
        $this->indexQueue->updateItem('pages', 12);

        // we expected that three pages are now in the index
        $this->assertIndexQueueContainsItemAmount(4);

        // simulate the database change and build a faked changeset
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['hidden' => 1], ['uid' => 2]);
        $changeSet = ['hidden' => 1];

        $dataHandler = $this->dataHandler;
        $this->garbageCollector->processDatamap_afterDatabaseOperations('update', 'pages', 2, $changeSet, $dataHandler);
    }

    #[Test]
    public function canCollectGarbageIfPageTreeIsMoved(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_collect_garbage_if_page_tree_is_moved.csv');

        $this->assertEmptyIndexQueue();
        $this->addToQueueAndIndexRecord('pages', 10);
        $this->addToQueueAndIndexRecord('pages', 11);
        $this->addToQueueAndIndexRecord('pages', 12);
        $this->addToQueueAndIndexRecord('pages', 13);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(4);

        $this->dataHandler->start(
            [],
            ['pages' => [10 => ['move' => 2]]],
            $this->backendUser,
        );

        $this->dataHandler->process_cmdmap();
        $this->assertIndexQueueContainsItemAmount(4);
        $this->assertSolrContainsDocumentCount(0);
    }

    #[Test]
    public function canCollectGarbageIfPageTreeIsMovedToSysfolderWithDisabledOptionIncludeSubEntriesInSearch(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_collect_garbage_if_page_tree_is_moved.csv');

        $this->assertEmptyIndexQueue();
        $this->addToQueueAndIndexRecord('pages', 10);
        $this->addToQueueAndIndexRecord('pages', 11);
        $this->addToQueueAndIndexRecord('pages', 12);
        $this->addToQueueAndIndexRecord('pages', 13);
        $this->waitToBeVisibleInSolr();
        $this->assertIndexQueueContainsItemAmount(4);

        $this->dataHandler->start(
            [],
            ['pages' => [10 => ['move' => 4]]],
            $this->backendUser,
        );
        $this->dataHandler->process_cmdmap();
        $this->assertEmptyIndexQueue();
        $this->assertSolrContainsDocumentCount(0);
    }

    #[Test]
    public function canCollectGarbageIfPageTreeIsMovedButStaysOnSamePage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_collect_garbage_if_page_tree_is_moved.csv');

        $this->assertEmptyIndexQueue();
        $this->addToQueueAndIndexRecord('pages', 10);
        $this->addToQueueAndIndexRecord('pages', 11);
        $this->addToQueueAndIndexRecord('pages', 12);
        $this->addToQueueAndIndexRecord('pages', 13);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(4);

        $this->dataHandler->start(
            [],
            ['pages' => [10 => ['move' => -2]]],
            $this->backendUser,
        );

        $this->dataHandler->process_cmdmap();
        $this->assertIndexQueueContainsItemAmount(4);
        $this->assertSolrContainsDocumentCount(3);
    }

    #[Test]
    public function canCollectGarbageEvenIfNotInIndexQueue(): void
    {
        $this->prepareCanCollectGarbageEvenIfNotInIndexQueue();
        $this->assertIndexQueueContainsItemAmount(0);
        $this->assertSolrIsEmpty();
    }

    #[Test]
    public function canCollectGarbageEvenIfNotInIndexQueueInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $this->prepareCanCollectGarbageEvenIfNotInIndexQueue();
        $this->assertEventQueueContainsItemAmount(2);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(0);
        $this->assertSolrIsEmpty();
    }

    protected function prepareCanCollectGarbageEvenIfNotInIndexQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/subpage.csv');

        $this->cleanUpSolrServerAndAssertEmpty();
        $this->assertEmptyIndexQueue();
        $this->addToQueueAndIndexRecord('pages', 2);
        $this->assertIndexQueueContainsItemAmount(1);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(1);

        // flush cache after preparing the environment to be sure no wrong version is cached
        $cache = new TwoLevelCache('runtime');
        $cache->flush();

        // clear index queue, as we want to test if garbage collection works if record has no representation in queue
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_solr_indexqueue_item');
        $connection->truncate('tx_solr_indexqueue_item');

        $this->dataHandler->start(
            ['pages' => [2 => ['hidden' => 1]]],
            [],
            $this->backendUser,
        );
        $this->dataHandler->process_datamap();
    }

    #[Test]
    #[DataProvider('canCollectGarbageOfDeletedRecordEvenIfNotInIndexQueueDataProvider')]
    public function canCollectGarbageOfDeletedRecordEvenIfNotInIndexQueue(bool $forceHardDelete): void
    {
        $this->prepareCanCollectGarbageOfDeletedRecordEvenIfNotInIndexQueue($forceHardDelete);
        $this->assertIndexQueueContainsItemAmount(0);
        $this->assertSolrIsEmpty();
    }

    #[Test]
    #[DataProvider('canCollectGarbageOfDeletedRecordEvenIfNotInIndexQueueDataProvider')]
    public function canCollectGarbageOfDeletedRecordEvenIfNotInIndexQueueInDelayedProcessingMode(bool $forceHardDelete): void
    {
        $this->setExtensionsMonitoringType(1);
        $this->prepareCanCollectGarbageOfDeletedRecordEvenIfNotInIndexQueue($forceHardDelete);
        $this->assertEventQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(0);
        $this->assertSolrIsEmpty();
    }

    protected function prepareCanCollectGarbageOfDeletedRecordEvenIfNotInIndexQueue(bool $forceHardDelete): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/subpage.csv');

        $this->cleanUpSolrServerAndAssertEmpty();
        $this->assertEmptyIndexQueue();
        $this->addToQueueAndIndexRecord('pages', 2);
        $this->assertIndexQueueContainsItemAmount(1);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(1);

        // flush cache after preparing the environment to be sure no wrong version is cached
        $cache = new TwoLevelCache('runtime');
        $cache->flush();

        // clear index queue, as we want to test if garbage collection works if record has no representation in queue
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_solr_indexqueue_item');
        $connection->truncate('tx_solr_indexqueue_item');

        if ($forceHardDelete) {
            unset($GLOBALS['TCA']['pages']['ctrl']['delete']);
        }

        $this->dataHandler->start(
            [],
            ['pages' => [2 => ['delete' => 1 ]]],
            $this->backendUser,
        );
        $this->dataHandler->process_cmdmap();
    }

    public static function canCollectGarbageOfDeletedRecordEvenIfNotInIndexQueueDataProvider(): Traversable
    {
        yield 'Test soft delete' => [ false ];
        yield 'Test hard delete' => [ true ];
    }

    #[Test]
    #[DataProvider('canCollectMountPageGarbageDataProvider')]
    public function canCollectMountPageGarbage(
        int $monitoringType,
        array $dataMap,
        array $cmdMap,
        int $expectedQueueCount,
        int $expectedDocumentCount,
        int $expectedItemsToReindex = 0,
    ): void {
        $this->setExtensionsMonitoringType($monitoringType);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/mount_page_garbage.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->assertEmptyEventQueue();

        // index all queue items
        foreach ($this->indexQueue->getAllItems() as $item) {
            self::assertTrue($this->indexPageQueueItem($item), 'Queue item failed to be indexed.');
        }
        self::assertSolrContainsDocumentCount(2, 'Initial number of documents in index not as expected');

        $this->dataHandler->start(
            $dataMap,
            $cmdMap,
            $this->backendUser,
        );
        $this->dataHandler->process_datamap();
        $this->dataHandler->process_cmdmap();

        if ($monitoringType === 1) {
            $this->processEventQueue();
        }
        $this->waitToBeVisibleInSolr();

        $queueItems = $this->indexQueue->getAllItems();
        self::assertCount($expectedQueueCount, $queueItems, 'Total number of index queue items differs');
        $itemsToReindex = array_filter(
            $queueItems,
            static fn(Item $item): bool => $item->getState() === ItemInterface::STATE_PENDING,
        );
        self::assertCount($expectedItemsToReindex, $itemsToReindex, 'Number of items that need reindexing differs');
        self::assertSolrContainsDocumentCount($expectedDocumentCount, 'Final number of documents in index not as expected');
    }

    public static function canCollectMountPageGarbageDataProvider(): Traversable
    {
        yield 'collect garbage on mount point deletion (immediate processing)' => [
            'monitoringType' => 0,
            'dataMap' => [],
            'cmdMap' => ['pages' => [20 => ['delete' => 1]]],
            'expectedQueueCount' => 1,
            'expectedDocumentCount' => 1,
        ];
        yield 'collect garbage on mount point deletion (delayed processing)' => [
            'monitoringType' => 1,
            'dataMap' => [],
            'cmdMap' => ['pages' => [20 => ['delete' => 1]]],
            'expectedQueueCount' => 1,
            'expectedDocumentCount' => 1,
        ];
        yield 'collect garbage on mount point deactivation (immediate processing)' => [
            'monitoringType' => 0,
            'dataMap' => ['pages' => [20 => ['hidden' => 1]]],
            'cmdMap' => [],
            'expectedQueueCount' => 1,
            'expectedDocumentCount' => 1,
        ];
        yield 'collect garbage on mount point deactivation (delayed processing)' => [
            'monitoringType' => 1,
            'dataMap' => ['pages' => [20 => ['hidden' => 1]]],
            'cmdMap' => [],
            'expectedQueueCount' => 1,
            'expectedDocumentCount' => 1,
        ];
        yield 'collect garbage on mount point title update (immediate processing)' => [
            'monitoringType' => 0,
            'dataMap' => ['pages' => [20 => ['title' => 'new title']]],
            'cmdMap' => [],
            'expectedQueueCount' => 2,
            'expectedDocumentCount' => 2,
        ];
        yield 'collect garbage on mount point title update (delayed processing)' => [
            'monitoringType' => 1,
            'dataMap' => ['pages' => [20 => ['title' => 'new title']]],
            'cmdMap' => [],
            'expectedQueueCount' => 2,
            'expectedDocumentCount' => 2,
        ];
        yield 'collect garbage on mount point slug update (immediate processing)' => [
            'monitoringType' => 0,
            'dataMap' => ['pages' => [20 => ['slug' => '/new-mount-point-slug']]],
            'cmdMap' => [],
            'expectedQueueCount' => 2,
            'expectedDocumentCount' => 1,
            'expectedItemsToReindex' => 1,
        ];
        yield 'collect garbage on mount point slug update (delayed) processing)' => [
            'monitoringType' => 1,
            'dataMap' => ['pages' => [20 => ['slug' => '/new-mount-point-slug']]],
            'cmdMap' => [],
            'expectedQueueCount' => 2,
            'expectedDocumentCount' => 1,
            'expectedItemsToReindex' => 1,
        ];
    }

    #[Test]
    #[DataProvider('canCollectTranslatedMountPageGarbageDataProvider')]
    public function canCollectTranslatedMountPageGarbage(
        int $monitoringType,
        array $dataMap,
        array $cmdMap,
        int $expectedQueueCount,
        int $expectedDocumentCountEn,
        int $expectedDocumentCountDe,
        int $expectedItemsToReindex = 0,
    ): void {
        $this->setExtensionsMonitoringType($monitoringType);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/translated_mount_page_garbage.csv');
        $this->addTypoScriptToTemplateRecord(1, 'config.index_enable = 1');
        $this->cleanUpSolrServerAndAssertEmpty('core_en');
        $this->cleanUpSolrServerAndAssertEmpty('core_de');
        $this->assertEmptyEventQueue();

        // index all queue items
        foreach ($this->indexQueue->getAllItems() as $item) {
            self::assertTrue($this->indexPageQueueItem($item), 'Queue item failed to be indexed in default language.');
            self::assertTrue(
                $this->indexPageQueueItem($item, 1, 'core_de'),
                'Queue item failed to be indexed in german translation.',
            );
        }
        self::assertSolrContainsDocumentCount(2, 'Initial number of documents in english index not as expected', 'core_en');
        self::assertSolrContainsDocumentCount(2, 'Initial number of documents in german index not as expected', 'core_de');

        $this->dataHandler->start(
            $dataMap,
            $cmdMap,
            $this->backendUser,
        );
        $this->dataHandler->process_datamap();
        $this->dataHandler->process_cmdmap();

        if ($monitoringType === 1) {
            $this->processEventQueue();
        }
        $this->waitToBeVisibleInSolr();

        $queueItems = $this->indexQueue->getAllItems();
        self::assertCount($expectedQueueCount, $queueItems, 'Total number of index queue items differs');
        $itemsToReindex = array_filter(
            $queueItems,
            static fn(Item $item): bool => $item->getState() === ItemInterface::STATE_PENDING,
        );
        self::assertCount($expectedItemsToReindex, $itemsToReindex, 'Number of items that need reindexing differs');
        self::assertSolrContainsDocumentCount($expectedDocumentCountDe, 'Final number of documents in english index not as expected');
        self::assertSolrContainsDocumentCount($expectedDocumentCountDe, 'Final number of documents in german index not as expected');
    }

    public static function canCollectTranslatedMountPageGarbageDataProvider(): Traversable
    {
        yield 'collect garbage on mount point deletion (immediate processing)' => [
            'monitoringType' => 0,
            'dataMap' => [],
            'cmdMap' => ['pages' => [20 => ['delete' => 1]]],
            'expectedQueueCount' => 1,
            'expectedDocumentCountEn' => 1,
            'expectedDocumentCountDe' => 1,
        ];
        yield 'collect garbage on mount point deletion (delayed processing)' => [
            'monitoringType' => 1,
            'dataMap' => [],
            'cmdMap' => ['pages' => [20 => ['delete' => 1]]],
            'expectedQueueCount' => 1,
            'expectedDocumentCountEn' => 1,
            'expectedDocumentCountDe' => 1,
        ];
        yield 'collect garbage on mount point translation deletion (immediate processing)' => [
            'monitoringType' => 0,
            'dataMap' => [],
            'cmdMap' => ['pages' => [21 => ['delete' => 1]]],
            'expectedQueueCount' => 2,
            'expectedDocumentCountEn' => 1,
            'expectedDocumentCountDe' => 1,
            'expectedItemsToReindex' => 1,
        ];
        yield 'collect garbage on mount point translation deletion (delayed processing)' => [
            'monitoringType' => 0,
            'dataMap' => [],
            'cmdMap' => ['pages' => [21 => ['delete' => 1]]],
            'expectedQueueCount' => 2,
            'expectedDocumentCountEn' => 1,
            'expectedDocumentCountDe' => 1,
            'expectedItemsToReindex' => 1,
        ];
    }

    #[Test]
    public function canRemoveDeletedContentElement(): void
    {
        $this->prepareCanRemoveDeletedContentElement();

        // after applying the commands solr should be empty (because the page was removed from solr and queued for indexing)
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();

        // we expect the is one item in the indexQueue
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPages([1]);

        // now the content of the deleted content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove deleted content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    #[Test]
    public function canRemoveDeletedContentElementInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $this->prepareCanRemoveDeletedContentElement();
        $this->assertEventQueueContainsItemAmount(2);
        $this->processEventQueue();
        $this->assertEmptyEventQueue();
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();
    }

    /**
     * Prepares the test cases:
     * - canRemoveDeletedContentElement
     * - canRemoveDeletedContentElementInDelayedProcessingMode
     */
    protected function prepareCanRemoveDeletedContentElement(): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/indexed_content.csv');

        // we index a page with two content elements and expect solr contains the content of both
        $this->indexPages([1]);

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('will be removed!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');

        // we delete the second content element
        $cmd = ['tt_content' => [88 => ['delete' => 1 ]]];
        $this->dataHandler->start([], $cmd, $this->backendUser);
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
    }

    #[Test]
    public function canRemoveHiddenContentElement(): void
    {
        $data = ['tt_content' => ['88' => ['hidden' => 1]]];
        $this->prepareCanRemoveContentElementTests($data);

        // after applying the commands solr should be empty (because the page was removed from solr and queued for indexing)
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();

        // we expect the is one item in the indexQueue
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPages([1]);
        $this->waitToBeVisibleInSolr();

        // now the content of the deletec content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove hidden content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    #[Test]
    public function canRemoveHiddenContentElementInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $data = ['tt_content' => ['88' => ['hidden' => 1]]];
        $this->prepareCanRemoveContentElementTests($data);

        $this->assertEventQueueContainsItemAmount(2);
        $this->assertIndexQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
        self::assertNull($this->indexQueue->getItem(4711));
        $item = $this->indexQueue->getAllItems()[0];
        self::assertGreaterThan(1449151778, $item->getChanged());
        $this->assertEmptyEventQueue();
    }

    #[Test]
    public function canRemoveContentElementWithEndTimeSetToPast(): void
    {
        $timeStampInPast = time() - (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['endtime' => $timeStampInPast]]];
        $this->prepareCanRemoveContentElementTests($data);

        // we expect the is one item in the indexQueue
        $this->waitToBeVisibleInSolr();
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPages([1]);

        // now the content of the deleted content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove content hidden by endtime in past');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    #[Test]
    public function canRemoveContentElementWithEndTimeSetToPastInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $timeStampInPast = time() - (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['endtime' => $timeStampInPast]]];
        $this->prepareCanRemoveContentElementTests($data);

        $this->assertEventQueueContainsItemAmount(2);
        $this->assertIndexQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
        self::assertNull($this->indexQueue->getItem(4711));
        $item = $this->indexQueue->getAllItems()[0];
        self::assertGreaterThan(1449151778, $item->getChanged());
        $this->assertEmptyEventQueue();
    }

    #[Test]
    public function doesNotRemoveUpdatedContentElementWithNotSetEndTime(): void
    {
        $data = ['tt_content' => ['88' => ['bodytext' => 'Updated! Will stay after update!' ]]];
        $this->prepareCanRemoveContentElementTests($data, 'does_not_remove_updated_content_element_with_not_set_endtime.csv', [2]);

        // document should stay in the index, because endtime was not in past but empty
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('will stay! still present after update!', $solrContent, 'solr did not contain rendered page content, which is needed for test.');

        $this->waitToBeVisibleInSolr();

        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 2);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPages([2]);

        // now the content of the deleted content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('Updated! Will stay after update!', $solrContent, 'solr did not remove content hidden by endtime in past');
    }

    #[Test]
    public function doesNotRemoveUpdatedContentElementWithNotSetEndTimeInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $data = ['tt_content' => ['88' => ['bodytext' => 'Updated! Will stay after update!' ]]];
        $this->prepareCanRemoveContentElementTests($data, 'does_not_remove_updated_content_element_with_not_set_endtime.csv', [2]);

        $this->assertEventQueueContainsItemAmount(2);
        $this->assertIndexQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
        self::assertNull($this->indexQueue->getItem(4711));
        $item = $this->indexQueue->getAllItems()[0];
        self::assertGreaterThan(1449151778, $item->getChanged());
        $this->assertEmptyEventQueue();
    }

    #[Test]
    public function canRemoveContentElementWithStartDateSetToFuture(): void
    {
        $timestampInFuture = time() +  (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['starttime' => $timestampInFuture]]];
        $this->prepareCanRemoveContentElementTests($data);

        // we expect the is one item in the indexQueue
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPages([1]);

        // now the content of the deletec content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove content hidden by starttime in future');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    #[Test]
    public function canRemoveContentElementWithStartDateSetToFutureInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $timeStampInPast = time() - (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['endtime' => $timeStampInPast]]];
        $this->prepareCanRemoveContentElementTests($data);

        $this->assertEventQueueContainsItemAmount(2);
        $this->assertIndexQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
        self::assertNull($this->indexQueue->getItem(4711));
        $item = $this->indexQueue->getAllItems()[0];
        self::assertGreaterThan(1449151778, $item->getChanged());
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases:
     * - canRemoveHiddenContentElement
     * - canRemoveHiddenContentElementInDelayedProcessingMode
     * - canRemoveContentElementWithEndTimeSetToPast
     * - canRemoveContentElementWithEndTimeSetToPastInDelayedProcessingMode
     * - doesNotRemoveUpdatedContentElementWithNotSetEndTime
     * - doesNotRemoveUpdatedContentElementWithNotSetEndTimeInDelayedProcessingMode
     * - canRemoveContentElementWithStartDateSetToFuture
     * - canRemoveContentElementWithStartDateSetToFutureInDelayedProcessingMode
     */
    protected function prepareCanRemoveContentElementTests(array $dataMap, string $fixture = 'indexed_content.csv', array $indexPageIds = [1]): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $fixture);

        // we index a page with two content elements and expect solr contains the content of both
        $this->indexPages($indexPageIds);
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        if ($fixture === 'indexed_content.csv') {
            self::assertStringContainsString('will be removed!', $solrContent, 'Solr did not contain rendered page content');
        }
        self::assertStringContainsString('will stay!', $solrContent, 'Solr did not contain required page or content element content');

        // we hide the second content element
        $this->dataHandler->start($dataMap, [], $this->backendUser);
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
        $this->dataHandler->clear_cacheCmd('all');
    }

    #[Test]
    public function canRemovePageWhenPageIsHidden(): void
    {
        $dataMap = ['pages' => ['2' => ['hidden' => 1]]];
        $this->prepareCanRemovePagesTests($dataMap);

        $this->waitToBeVisibleInSolr();
        $this->assertIndexQueueContainsItemAmount(1);

        // we reindex all queue items
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $items = $this->indexQueue->getItemsToIndex($site);
        $pages = [];
        foreach ($items as $item) {
            $pages[] = $item->getRecordUid();
        }
        $this->indexPages($pages);
        $this->waitToBeVisibleInSolr();

        // now only one document should be left with the content of the first content element
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'Solr did not remove content from hidden page');
        self::assertStringContainsString('will stay!', $solrContent, 'Solr did not contain rendered page content');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Expected to have two documents in the index');
    }

    #[Test]
    public function canRemovePageWhenPageIsHiddenInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $dataMap = ['pages' => ['2' => ['hidden' => 1]]];

        $this->assertEmptyEventQueue();
        $this->prepareCanRemovePagesTests($dataMap);
        $this->assertIndexQueueContainsItemAmount(2);
        $this->processEventQueue();
        $this->assertEmptyEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function canRemovePageWhenPageIsDeleted(): void
    {
        $cmd = ['pages' => [2 => ['delete' => 1 ]]];
        $this->prepareCanRemovePagesTests([], $cmd);

        $this->waitToBeVisibleInSolr();
        $this->assertIndexQueueContainsItemAmount(1);

        // we reindex all queue items
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $items = $this->indexQueue->getItemsToIndex($site);
        $pages = [];
        foreach ($items as $item) {
            $pages[] = $item->getRecordUid();
        }
        $this->indexPages($pages);
        $this->waitToBeVisibleInSolr();

        // now only one document should be left with the content of the first content element
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove content from deleted page');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Expected to have two documents in the index');
    }

    #[Test]
    public function canRemovePageWhenPageIsDeletedInDelayedProcessingMode(): void
    {
        $this->setExtensionsMonitoringType(1);
        $cmdMap = ['pages' => [2 => ['delete' => 1 ]]];

        $this->assertEmptyEventQueue();
        $this->prepareCanRemovePagesTests([], $cmdMap);
        $this->assertIndexQueueContainsItemAmount(2);
        $this->processEventQueue();
        $this->assertEmptyEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function canRemovePageWhenContentElementOnAlreadyDeletedPageIsDeleted(): void
    {
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleted_page_and_content.csv');
        $cmdMap = ['tt_content' => [321 => ['delete' => 1 ]]];

        $this->dataHandler->start([], $cmdMap, $this->backendUser);
        $this->dataHandler->process_cmdmap();

        self::assertEquals(1, $this->indexQueue->getAllItemsCount(), 'Queue item count not as expected');

        // check queue directly as Queue wouldn't return invalid records
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $queueItemUid = $connection->select(['uid'], 'tx_solr_indexqueue_item')->fetchOne();
        self::assertEquals(234, $queueItemUid);
    }

    #[Test]
    public function canRemovePageWhenContentElementOnNonExistingPageIsDeleted(): void
    {
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleted_page_and_content.csv');
        $cmdMap = ['tt_content' => [432 => ['delete' => 1 ]]];

        $this->dataHandler->start([], $cmdMap, $this->backendUser);
        $this->dataHandler->process_cmdmap();

        // check queue directly as Queue wouldn't return invalid records
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $queueItemUid = $connection->select(['uid'], 'tx_solr_indexqueue_item')->fetchOne();
        self::assertEquals(123, $queueItemUid);
    }

    #[Test]
    public function canRemovePageWhenContentElementOnAlreadyDeletedSiteIsDeleted(): void
    {
        // simulate that site root is deleted
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        self::assertEquals(1, $connection->update('pages', ['deleted' => 1], ['uid' => 1]));

        $this->importCSVDataSet(__DIR__ . '/Fixtures/deleted_page_and_content.csv');
        $cmdMap = ['tt_content' => [321 => ['delete' => 1 ]]];

        $this->dataHandler->start([], $cmdMap, $this->backendUser);
        $this->dataHandler->process_cmdmap();

        // check queue directly as Queue wouldn't return invalid records
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $queueItemUid = $connection->select(['uid'], 'tx_solr_indexqueue_item')->fetchOne();
        self::assertEquals(234, $queueItemUid);
    }

    /**
     * Prepares the test cases:
     * - canRemovePageWhenPageIsHidden
     * - canRemovePageWhenPageIsHiddenInDelayedProcessingMode
     * - canRemovePageWhenPageIsDeleted
     * - canRemovePageWhenPageIsDeletedInDelayedProcessingMode
     */
    protected function prepareCanRemovePagesTests(array $dataMap, array $cmdMap = []): void
    {
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
        $this->addSimpleFrontendRenderingToTypoScriptRendering(1);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_remove_page.csv');

        // we index two pages and check that both are visible
        $this->indexPages([1, 2]);

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('will be removed!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('"numFound":2', $solrContent, 'Expected to have two documents in the index');

        // we hide the second page
        $this->dataHandler->start($dataMap, $cmdMap, $this->backendUser);
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
        $this->dataHandler->clear_cacheCmd('all');
    }

    #[Test]
    public function canTriggerHookAfterRecordDeletion(): void
    {
        $this->prepareCanTriggerHookAfterRecordDeletion();
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();

        // since our hook is a singleton we check here if it was called.
        /** @var TestGarbageCollectorPostProcessor $hook */
        $hook = GeneralUtility::makeInstance(TestGarbageCollectorPostProcessor::class);
        self::assertTrue($hook->isHookWasCalled());

        // reset the hooks
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'] = [];
    }

    #[Test]
    public function canTriggerHookAfterRecordDeletionInDelayedProcessingMode(): void
    {
        /** @var TestGarbageCollectorPostProcessor $hook */
        $hook = GeneralUtility::makeInstance(TestGarbageCollectorPostProcessor::class);

        $this->setExtensionsMonitoringType(1);
        $this->prepareCanTriggerHookAfterRecordDeletion();
        $this->assertEventQueueContainsItemAmount(1);
        self::assertFalse($hook->isHookWasCalled());
        $this->processEventQueue();
        self::assertTrue($hook->isHookWasCalled());
    }

    /**
     * Prepares the test cases:
     * - canTriggerHookAfterRecordDeletion
     * - canTriggerHookAfterRecordDeletionInDelayedProcessingMode
     */
    protected function prepareCanTriggerHookAfterRecordDeletion(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessGarbageCollector'][] = TestGarbageCollectorPostProcessor::class;
        $this->importCSVDataSet(__DIR__ . '/Fixtures/custom_record.csv');

        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    type = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }',
        );

        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();

        $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_foo', 111);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(1);

        // we hide the second page
        $cmd = ['tx_fakeextension_domain_model_foo' => [111 => ['delete' => 1 ]]];
        $this->dataHandler->start([], $cmd, $this->backendUser);
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
        $this->dataHandler->clear_cacheCmd('all');
    }

    protected function addToQueueAndIndexRecord(string $table, int $uid): bool
    {
        $result = false;
        // write an index queue item
        $updatedItems = $this->indexQueue->updateItem($table, $uid);

        self::assertEquals(1, $updatedItems);

        // run the indexer
        $items = $this->indexQueue->getItems($table, $uid);
        foreach ($items as $item) {
            $result = $this->indexer->index($item);
            if ($result === false) {
                break;
            }
        }

        return $result;
    }
}
