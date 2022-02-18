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
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Scheduler;

/**
 * This testcase is used to check if the GarbageCollector can delete garbage from the
 * solr server as expected
 *
 * @author Timo Schmidt
 */
class GarbageCollectorTest extends IntegrationTest
{
    /**
     * @var array
     */
    protected $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler',
    ];

    /**
     * @var RecordMonitor
     */
    protected $recordMonitor;

    /**
     * @var DataHandler
     */
    protected $dataHandler;

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var GarbageCollector
     */
    protected $garbageCollector;

    /**
     * @var Indexer
     */
    protected $indexer;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * @var EventQueueItemRepository
     */
    protected $eventQueue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->recordMonitor = GeneralUtility::makeInstance(RecordMonitor::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->garbageCollector = GeneralUtility::makeInstance(GarbageCollector::class);
        $this->indexer = GeneralUtility::makeInstance(Indexer::class);
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->eventQueue = GeneralUtility::makeInstance(EventQueueItemRepository::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
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
            $this->eventQueue
            );
        parent::tearDown();
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
            'Index queue is empty and was expected to be not empty.'
        );
    }

    /**
     * @param $amount
     */
    protected function assertIndexQueueContainsItemAmount($amount): void
    {
        $itemsInQueue = $this->indexQueue->getAllItemsCount();
        self::assertEquals(
            $amount,
            $itemsInQueue,
            'Index queue contains ' . $itemsInQueue . ' but was expected to contain ' . $amount . ' items.'
        );
    }

    protected function assertEmptyEventQueue(): void
    {
        self::assertEquals(0, $this->eventQueue->count(), 'Event queue is not empty as expected');
    }

    /**
     * @param int $amount
     */
    protected function assertEventQueueContainsItemAmount(int $amount): void
    {
        $itemsInQueue = $this->eventQueue->count();
        self::assertEquals(
            $amount,
            $itemsInQueue,
            'Event queue contains ' . $itemsInQueue . ' but was expected to contain ' . $amount . ' items.'
        );
    }

    /**
     * @test
     */
    public function queueItemStaysWhenOverlayIsSetToHidden(): void
    {
        $this->prepareQueueItemStaysWhenOverlayIsSetToHidden();

        // index queue not modified
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function queueItemStaysWhenOverlayIsSetToHiddenInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
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
        $this->importDataSetFromFixture('queue_item_stays_when_overlay_set_to_hidden.xml');

        $this->assertIndexQueueContainsItemAmount(1);

        $this->garbageCollector->processDatamap_afterDatabaseOperations('update', 'pages', 2, ['hidden' => 1], $this->dataHandler);
    }

    /**
     * @test
     */
    public function canQueueAPageAndRemoveItWithTheGarbageCollector(): void
    {
        $this->importDataSetFromFixture('can_queue_a_page_and_remove_it_with_the_garbage_collector.xml');

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

    /**
     * @test
     */
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet(): void
    {
        $this->prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet();

        // finally we expect that the index is empty again because the root page with "extendToSubPages" has been set to
        // hidden = 1
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
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
        $this->importDataSetFromFixture('can_collect_garbage_from_subPages_when_page_is_set_to_hidden_and_extendToSubpages_is_set.xml');

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

    /**
     * @test
     */
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages(): void
    {
        $this->prepareCanCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages();

        // finally we expect that the index is empty again because the root page with "extendToSubPages" has been set to
        // hidden = 1
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpagesInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
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
        $this->importDataSetFromFixture('can_collect_garbage_from_subPages_when_page_is_set_to_hidden_and_extendToSubpages_is_set_multiple_subpages.xml');

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

    /**
     * @test
     */
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
        $this->indexPageIds([1]);
        $this->waitToBeVisibleInSolr();

        // now the content of the deleted content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove deleted content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    /**
     * @test
     */
    public function canRemoveDeletedContentElementInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
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
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importDataSetFromFixture('can_remove_content_element.xml');

        $this->indexPageIds([1]);

        // we index a page with two content elements and expect solr contains the content of both
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('will be removed!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');

        // we delete the second content element
        $beUser = $this->fakeBEUser(1, 0);

        $cmd = ['tt_content' => [88 => ['delete' => 1 ]]];
        $this->dataHandler->start([], $cmd, $beUser);
        $this->dataHandler->stripslashes_values = 0;
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
    }

    /**
     * @test
     */
    public function canRemoveHiddenContentElement(): void
    {
        $data = ['tt_content' => ['88' => ['hidden' => 1]]];
        $this->prepareCanRemoveContentElementTests($data, []);

        // after applying the commands solr should be empty (because the page was removed from solr and queued for indexing)
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();

        // we expect the is one item in the indexQueue
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPageIds([1]);
        $this->waitToBeVisibleInSolr();

        // now the content of the deletec content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove hidden content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    /**
     * @test
     */
    public function canRemoveHiddenContentElementInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $data = ['tt_content' => ['88' => ['hidden' => 1]]];
        $this->prepareCanRemoveContentElementTests($data, []);

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
     * @test
     */
    public function canRemoveContentElementWithEndTimeSetToPast(): void
    {
        $timeStampInPast = time() - (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['endtime' => $timeStampInPast]]];
        $this->prepareCanRemoveContentElementTests($data, []);

        // after applying the commands solr should be empty (because the page was removed from solr and queued for indexing)
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();

        // we expect the is one item in the indexQueue
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPageIds([1]);
        $this->waitToBeVisibleInSolr();

        // now the content of the deletec content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove content hidden by endtime in past');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    /**
     * @test
     */
    public function canRemoveContentElementWithEndTimeSetToPastInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $timeStampInPast = time() - (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['endtime' => $timeStampInPast]]];
        $this->prepareCanRemoveContentElementTests($data, []);

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
     * @test
     */
    public function doesNotRemoveUpdatedContentElementWithNotSetEndTime(): void
    {
        $data = ['tt_content' => ['88' => ['bodytext' => 'Updated! Will stay after update!' ]]];
        $this->prepareCanRemoveContentElementTests($data, [], 'does_not_remove_updated_content_element_with_not_set_endtime.xml', [2]);

        // document should stay in the index, because endtime was not in past but empty
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('will stay! still present after update!', $solrContent, 'solr did not contain rendered page content, which is needed for test.');

        $this->waitToBeVisibleInSolr();

        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 2);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPageIds([2]);
        $this->waitToBeVisibleInSolr();

        // now the content of the deleted content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('Updated! Will stay after update!', $solrContent, 'solr did not remove content hidden by endtime in past');
    }

    /**
     * @test
     */
    public function doesNotRemoveUpdatedContentElementWithNotSetEndTimeInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $data = ['tt_content' => ['88' => ['bodytext' => 'Updated! Will stay after update!' ]]];
        $this->prepareCanRemoveContentElementTests($data, [], 'does_not_remove_updated_content_element_with_not_set_endtime.xml', [2]);

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
     * @test
     */
    public function canRemoveContentElementWithStartDateSetToFuture(): void
    {
        $timestampInFuture = time() +  (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['starttime' => $timestampInFuture]]];
        $this->prepareCanRemoveContentElementTests($data, []);

        // after applying the commands solr should be empty (because the page was removed from solr and queued for indexing)
        $this->waitToBeVisibleInSolr();
        $this->assertSolrIsEmpty();

        // we expect the is one item in the indexQueue
        $this->assertIndexQueueContainsItemAmount(1);
        $items = $this->indexQueue->getItems('pages', 1);
        self::assertSame(1, count($items));

        // we index this item
        $this->indexPageIds([1]);
        $this->waitToBeVisibleInSolr();

        // now the content of the deletec content element should be gone
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove content hidden by starttime in future');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
    }

    /**
     * @test
     */
    public function canRemoveContentElementWithStartDateSetToFutureInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $timeStampInPast = time() - (60 * 60 * 24);
        $data = ['tt_content' => ['88' => ['endtime' => $timeStampInPast]]];
        $this->prepareCanRemoveContentElementTests($data, []);

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
     *
     * @param array $dataMap
     * @param array $cmdMap
     * @param string $fixture
     * @param array $indexPageIds
     */
    protected function prepareCanRemoveContentElementTests(array $dataMap, array $cmdMap, $fixture = 'can_remove_content_element.xml', array $indexPageIds = [1]): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importDataSetFromFixture($fixture);

        $this->indexPageIds($indexPageIds);

        // we index a page with two content elements and expect solr contains the content of both
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        if ($fixture === 'can_remove_content_element.xml') {
            self::assertStringContainsString('will be removed!', $solrContent, 'Solr did not contain rendered page content');
        }
        self::assertStringContainsString('will stay!', $solrContent, 'Solr did not contain required page or content element content');

        // we hide the second content element
        $beUser = $this->fakeBEUser(1, 0);
        $this->dataHandler->start($dataMap, $cmdMap, $beUser);
        $this->dataHandler->stripslashes_values = 0;
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
        $this->dataHandler->clear_cacheCmd('all');
    }

    /**
     * @test
     */
    public function canRemovePageWhenPageIsHidden(): void
    {
        $dataMap = ['pages' => ['2' => ['hidden' => 1]]];
        $this->prepareCanRemovePagesTests($dataMap, []);

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
        $this->indexPageIds($pages);
        $this->waitToBeVisibleInSolr();

        // now only one document should be left with the content of the first content element
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'Solr did not remove content from hidden page');
        self::assertStringContainsString('will stay!', $solrContent, 'Solr did not contain rendered page content');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Expected to have two documents in the index');
    }

    /**
     * @test
     */
    public function canRemovePageWhenPageIsHiddenInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $dataMap = ['pages' => ['2' => ['hidden' => 1]]];

        $this->assertEmptyEventQueue();
        $this->prepareCanRemovePagesTests($dataMap, []);
        $this->assertIndexQueueContainsItemAmount(2);
        $this->processEventQueue();
        $this->assertEmptyEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
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
        $this->indexPageIds($pages);
        $this->waitToBeVisibleInSolr();

        // now only one document should be left with the content of the first content element
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringNotContainsString('will be removed!', $solrContent, 'solr did not remove content from deleted page');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Expected to have two documents in the index');
    }

    /**
     * @test
     */
    public function canRemovePageWhenPageIsDeletedInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $cmdMap = ['pages' => [2 => ['delete' => 1 ]]];

        $this->assertEmptyEventQueue();
        $this->prepareCanRemovePagesTests([], $cmdMap);
        $this->assertIndexQueueContainsItemAmount(2);
        $this->processEventQueue();
        $this->assertEmptyEventQueue();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * Prepares the test cases:
     * - canRemovePageWhenPageIsHidden
     * - canRemovePageWhenPageIsHiddenInDelayedProcessingMode
     * - canRemovePageWhenPageIsDeleted
     * - canRemovePageWhenPageIsDeletedInDelayedProcessingMode
     *
     * @param array $dataMap
     * @param array $cmdMap
     */
    protected function prepareCanRemovePagesTests(array $dataMap, array $cmdMap): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        $this->importDataSetFromFixture('can_remove_page.xml');

        $this->indexPageIds([1, 2]);

        // we index two pages and check that both are visible
        $this->waitToBeVisibleInSolr();

        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('will be removed!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('will stay!', $solrContent, 'solr did not contain rendered page content');
        self::assertStringContainsString('"numFound":2', $solrContent, 'Expected to have two documents in the index');

        // we hide the second page
        $beUser = $this->fakeBEUser(1, 0);

        $this->dataHandler->start($dataMap, $cmdMap, $beUser);
        $this->dataHandler->stripslashes_values = 0;
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
        $this->dataHandler->clear_cacheCmd('all');
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function canTriggerHookAfterRecordDeletionInDelayedProcessingMode(): void
    {
        /** @var TestGarbageCollectorPostProcessor $hook */
        $hook = GeneralUtility::makeInstance(TestGarbageCollectorPostProcessor::class);

        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
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

        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));
        $this->importDataSetFromFixture('can_delete_custom_record.xml');

        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    table = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }'
        );

        $this->cleanUpSolrServerAndAssertEmpty();
        $this->fakeLanguageService();

        // we hide the seconde page
        $beUser = $this->fakeBEUser(1, 0);

        $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_foo', 111);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(1);

        $cmd = ['tx_fakeextension_domain_model_foo' => [111 => ['delete' => 1 ]]];
        $this->dataHandler->start([], $cmd, $beUser);
        $this->dataHandler->stripslashes_values = 0;
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
        $this->dataHandler->clear_cacheCmd('all');
    }

    /**
     * @param string $table
     * @param int $uid
     * @return bool
     */
    protected function addToQueueAndIndexRecord($table, $uid): bool
    {
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

    /**
     * Prepares a LanguageService object
     */
    protected function fakeLanguageService(): void
    {
        /** @var $languageService  \TYPO3\CMS\Core\Localization\LanguageService */
        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $GLOBALS['LANG'] = $languageService;
    }

    /**
     * Triggers event queue processing
     */
    protected function processEventQueue(): void
    {
        /** @var EventQueueWorkerTask $task */
        $task = GeneralUtility::makeInstance(EventQueueWorkerTask::class);

        /** @var Scheduler $scheduler */
        $scheduler = GeneralUtility::makeInstance(Scheduler::class);
        $scheduler->executeTask($task);
    }
}
