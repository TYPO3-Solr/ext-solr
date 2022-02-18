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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Records\Queue\EventQueueItemRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Scheduler;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;

/**
 * Testcase for the record monitor
 *
 * @author Timo Schmidt
 */
class RecordMonitorTest extends IntegrationTest
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
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->eventQueue = GeneralUtility::makeInstance(EventQueueItemRepository::class);
        $this->extensionConfiguration->set('solr', ['monitoringType' => 0]);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        $this->extensionConfiguration->setAll([]);
        unset(
            $this->recordMonitor,
            $this->dataHandler,
            $this->indexQueue,
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
     * @param int $amount
     */
    protected function assertIndexQueueContainsItemAmount(int $amount): void
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
     * Regression test for issue #155. Saving the root page causes sql errors:
     *
     * TYPO3\CMS\Core\Database\DatabaseConnection::exec_SELECTquery" (60 chars)
     * ERROR => "You have an error in your SQL syntax; check the manual that corresponds to y
     * our MariaDB server version for the right syntax to use near 'OR (mount_pid=1
     * AND mount_pid_ol=1)) AND doktype = 7 AND no_search = 0 AND pages' at line 1" (228 chars)
     *
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/155
     * @test
     */
    public function canUpdateRootPageRecordWithoutSQLErrorFromMountPages(): void
    {
        $this->markAsRisky();

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // @todo: detecting the sql error is a little bit ugly but it seems there is no other possibility right now
        ob_start();
        $this->recordMonitor->processCmdmap_postProcess(
            'version',
            'pages',
            1,
            ['action' => 'swap'],
            $this->dataHandler
        );

        $output = trim(ob_get_contents());
        ob_end_clean();

        self::assertStringNotContainsString(
            'You have an error in your SQL syntax',
            $output,
            'We expect no sql error during the update of a regular page root record'
        );

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();
    }

    /**
     * Regression test for issue #48. Indexing of new records will crash if the name of the Indexing
     * Queue Configuration is different from tablename
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/48
     * @test
     */
    public function canUseCorrectIndexingConfigurationForANewNonPagesRecord(): void
    {
        $this->prepareCanUseCorrectIndexingConfigurationForANewNonPagesRecord();

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('tx_fakeextension_domain_model_foo', 8);
        self::assertSame(1, count($items));
        self::assertSame(
            'foo',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * @test
     */
    public function canUseCorrectIndexingConfigurationForANewNonPagesRecordInDelayedProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareCanUseCorrectIndexingConfigurationForANewNonPagesRecord();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('tx_fakeextension_domain_model_foo', 8);
        self::assertSame(1, count($items));
        self::assertSame(
            'foo',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * @test
     */
    public function canIgnoreCorrectIndexingConfigurationForANewNonPagesRecordInNoProcessingMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 2]);
        $this->prepareCanUseCorrectIndexingConfigurationForANewNonPagesRecord();

        $this->assertEmptyIndexQueue();

        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases:
     * - canUseCorrectIndexingConfigurationForANewNonPagesRecord
     * - canUseCorrectIndexingConfigurationForANewNonPagesRecordInDelayedProcessingMode
     * - canIgnoreCorrectIndexingConfigurationForANewNonPagesRecordInNoProcessingMode
     */
    protected function prepareCanUseCorrectIndexingConfigurationForANewNonPagesRecord(): void
    {
        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        // create faked tce main call data
        $status = 'new';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = [
            'title' => 'testnews',
            'pid' => 1,
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
        ];
        $this->dataHandler->substNEWwithIDs = ['NEW566a9eac309d8193936351' => 8];

        $this->importDataSetFromFixture('new_non_pages_record_is_using_correct_configuration_name.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index {
                queue {
                    foo = 1
                    foo {
                        table = tx_fakeextension_domain_model_foo
                        fields.title = title
                    }
                }
            }
            '
        );

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved(): void
    {
        $this->prepareCanQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved();

        // we expect that all subpages of 1 and 1 its selft have been requeued but not more
        // pages with uid 1, 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(3);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemovedInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareCanQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        // we expect that all subpages of 1 and 1 its selft have been requeued but not more
        // pages with uid 1, 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(3);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases canQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved and
     * canQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemovedInDelayedMode
     */
    protected function prepareCanQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved(): void
    {
        $this->importDataSetFromFixture('reindex_subpages_when_extendToSubpages_set_and_hidden_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['hidden' => 0], ['uid' => 17]);
        $changeSet = ['hidden' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $dataHandler);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved(): void
    {
        $this->prepareCanQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved();

        // we expect that all subpages of 1 have been requeued, but 1 not because it is still hidden
        // pages with uid 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemovedInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareCanQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        // we expect that all subpages of 1 have been requeued, but 1 not because it is still hidden
        // pages with uid 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(2);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases canQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved and
     * canQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemovedInDelayedMode
     */
    protected function prepareCanQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved(): void
    {
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_set_and_extendToSubpage_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['extendToSubpages' => 0], ['uid' => 17]);
        $changeSet = ['extendToSubpages' => 0];

        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $this->dataHandler);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved(): void
    {
        $this->prepareCanQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved();

        // we expect that page 1 incl. subpages has been requeued
        // pages with uid 10, 11 and 100 should be in index
        $this->assertIndexQueueContainsItemAmount(3);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemovedInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareCanQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        // we expect that page 1 incl. subpages has been requeued
        // pages with uid 10, 11 and 100 should be in index
        $this->assertIndexQueueContainsItemAmount(3);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases canQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved and
     * canQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemovedInDelayedMode
     */
    protected function prepareCanQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved(): void
    {
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_and_extendToSubpage_flags_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['extendToSubpages' => 0, 'hidden' => 0], ['uid' => 17]);
        $changeSet = ['extendToSubpages' => 0, 'hidden' => 0];

        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $this->dataHandler);
    }

    /**
     * @test
     */
    public function queueIsNotFilledWhenItemIsSetToHidden(): void
    {
        $this->prepareQueueIsNotFilledWhenItemIsSetToHidden();

        // we assert that the index queue is still empty because the page was only set to hidden
        $this->assertEmptyIndexQueue();
    }
    /**
     * @test
     */
    public function queueIsNotFilledWhenItemIsSetToHiddenInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareQueueIsNotFilledWhenItemIsSetToHidden();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertEmptyIndexQueue();
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases queueIsNotFilledWhenItemIsSetToHidden and
     * queueIsNotFilledWhenItemIsSetToHiddenInDelayedMode
     */
    protected function prepareQueueIsNotFilledWhenItemIsSetToHidden(): void
    {
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_set_and_extendToSubpage_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['hidden' => 1], ['uid' => 17]);

        $changeSet = ['hidden' => 1];
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $this->dataHandler);
    }

    /**
     * When a record without pid is processed a warning should be logged
     *
     * @test
     */
    public function logMessageIsCreatedWhenRecordWithoutPidIsCreated(): void
    {
        $loggerMock = $this->getMockBuilder(SolrLogManager::class)
            ->onlyMethods([
                'log',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $expectedSeverity = SolrLogManager::WARNING;
        $expectedMessage = 'Record without valid pid was processed tt_content:123';
        $loggerMock->expects(self::once())->method('log')->with($expectedSeverity, $expectedMessage);

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->substNEWwithIDs['NEW566a9eac309d8193936351'] = 123;
        $dataUpdateHandler = GeneralUtility::makeInstance(
            DataUpdateHandler::class,
            GeneralUtility::makeInstance(ConfigurationAwareRecordService::class),
            GeneralUtility::makeInstance(FrontendEnvironment::class),
            GeneralUtility::makeInstance(TCAService::class),
            GeneralUtility::makeInstance(Queue::class),
            GeneralUtility::makeInstance(MountPagesUpdater::class),
            GeneralUtility::makeInstance(RootPageResolver::class),
            GeneralUtility::makeInstance(PagesRepository::class),
            $dataHandler,
            $loggerMock
        );
        GeneralUtility::addInstance(DataUpdateHandler::class, $dataUpdateHandler);

        // we expect that this exception is getting thrown, because a record without pid was updated

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        // create faked tce main call data
        $status = 'new';
        $table = 'tt_content';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = [
            'title' => 'testce',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
        ];

        $this->importDataSetFromFixture('exception_is_triggered_without_pid.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index {
                queue {
                    foo = 1
                    foo {
                        table = tx_fakeextension_domain_model_foo
                        fields.title = title
                    }
                }
            }
            '
        );

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $dataHandler
        );
    }

    /**
     * This testcase checks, that a queue item will be removed when an unexisting record was updated
     *
     * @test
     */
    public function queueEntryIsRemovedWhenUnExistingRecordWasUpdated(): void
    {
        $this->prepareQueueEntryIsRemovedWhenUnExistingRecordWasUpdated();
        // the queue entry should be removed since the record itself does not exist
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function queueEntryIsRemovedWhenUnExistingRecordWasUpdatedInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareQueueEntryIsRemovedWhenUnExistingRecordWasUpdated();

        $this->assertIndexQueueContainsItemAmount(1);
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertEmptyIndexQueue();
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases queueEntryIsRemovedWhenUnExistingRecordWasUpdated and
     * queueEntryIsRemovedWhenUnExistingRecordWasUpdatedInDelayedMode
     */
    protected function prepareQueueEntryIsRemovedWhenUnExistingRecordWasUpdated(): void
    {
        // create faked tce main call data
        $status = 'update';
        $table = 'pages';
        // unexisting uid
        $uid = 2;
        $fields = [
            'title' => 'testpage',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
            'pid' => 1,
        ];

        $this->importDataSetFromFixture('update_unexisting_item_will_remove_queue_entry.xml');

        // there should be one item in the queue.
        $this->assertIndexQueueContainsItemAmount(1);
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/639
     * @test
     */
    public function canUseCorrectIndexingConfigurationForANewCustomPageTypeRecord(): void
    {
        $this->importDataSetFromFixture('can_use_correct_indexing_configuration_for_a_new_custom_page_type_record.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index.queue {
                pages {
                    allowedPageTypes = 1,3,7
                }

                custom_page_type = 1
                custom_page_type {
                    initialization = ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page
                    allowedPageTypes = 130
                    indexer = ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer
                    table = pages
                    additionalWhereClause = doktype = 130 AND no_search = 0

                    fields {
                        pagetype_stringS = TEXT
                        pagetype_stringS {
                            value = Custom Page Type
                        }
                    }
                }
            }
            '
        );

        // create faked tce main call data
        $status = 'new';
        $table = 'pages';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = [
            'title' => 'test custom page type',
            'pid' => 1,
            'doktype' => 130,
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
        ];
        $this->dataHandler->substNEWwithIDs = ['NEW566a9eac309d8193936351' => 8];
        // $this->importDataSetFromFixture('new_pages_record_is_using_correct_configuration_name_for_custom_page_type.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('pages', 8);
        self::assertSame(1, count($items));
        self::assertSame(
            'custom_page_type',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * @test
     */
    public function canQueueUpdatePagesWithCustomPageType(): void
    {
        $this->prepareCanQueueUpdatePagesWithCustomPageType();

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('pages', 8);
        self::assertSame(1, count($items));
        self::assertSame(
            'custom_page_type',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * @test
     */
    public function canQueueUpdatePagesWithCustomPageTypeInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareCanQueueUpdatePagesWithCustomPageType();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertNotEmptyIndexQueue();
        $this->assertEmptyEventQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('pages', 8);
        self::assertSame(1, count($items));
        self::assertSame(
            'custom_page_type',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * Prepares the test cases canQueueUpdatePagesWithCustomPageType and
     * canQueueUpdatePagesWithCustomPageTypeInDelayedMode
     */
    protected function prepareCanQueueUpdatePagesWithCustomPageType(): void
    {
        $this->importDataSetFromFixture('can_use_correct_indexing_configuration_for_a_new_custom_page_type_record.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
             plugin.tx_solr.index.queue {
                custom_page_type = 1
                custom_page_type {
                    initialization = ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page
                    allowedPageTypes = 130
                    indexer = ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer
                    table = pages
                    additionalWhereClause = doktype = 130 AND no_search = 0

                    fields {
                        pagetype_stringS = TEXT
                        pagetype_stringS {
                            value = Custom Page Type
                        }
                    }
                }
            }'
        );

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['hidden' => 0], ['uid' => 8]);

        $changeSet = ['hidden' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 8, $changeSet, $dataHandler);
    }

    /**
     * @test
     */
    public function mountPointIsOnlyAddedOnceForEachTree(): void
    {
        $data = $this->prepareMountPointIsOnlyAddedOnceForEachTree();

        // we assert that the page is added twice, once for the original tree and once for the mounted tree
        $this->assertIndexQueueContainsItemAmount(2);

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $data['status'],
            $data['table'],
            $data['uid'],
            $data['fields'],
            $this->dataHandler
        );

        // we assert that the page is added twice, once for the original tree and once for the mounted tree
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function mountPointIsOnlyAddedOnceForEachTreeInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $data = $this->prepareMountPointIsOnlyAddedOnceForEachTree();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(2);
        $this->assertEmptyEventQueue();

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $data['status'],
            $data['table'],
            $data['uid'],
            $data['fields'],
            $this->dataHandler
        );
        $this->assertEventQueueContainsItemAmount(1);
        $this->processEventQueue();
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * Prepares the test cases mountPointIsOnlyAddedOnceForEachTree and
     * mountPointIsOnlyAddedOnceForEachTreeInDelayedMode
     *
     * @return array
     */
    protected function prepareMountPointIsOnlyAddedOnceForEachTree(): array
    {
        $this->importDataSetFromFixture('mount_pages_are_added_once.xml');
        $this->assertEmptyIndexQueue();

        $data = [
            'status' => 'update',
            'table' => 'pages',
            'uid' => 40,
            'fields' => [
                'title' => 'testpage',
                'starttime' => 1000000,
                'endtime' => 1100000,
                'tsstamp' => 1000000,
                'pid' => 4,
            ],
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $data['status'],
            $data['table'],
            $data['uid'],
            $data['fields'],
            $this->dataHandler
        );

        return $data;
    }

    /**
     * @test
     */
    public function localizedPageIsAddedToTheQueue(): void
    {
        $this->prepareLocalizedPageIsAddedToTheQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);

        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        self::assertSame(
            'pages',
            $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName'
        );
    }

    /**
     * @test
     */
    public function localizedPageIsAddedToTheQueueInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareLocalizedPageIsAddedToTheQueue();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);

        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        self::assertSame(
            'pages',
            $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName'
        );
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases localizedPageIsAddedToTheQueue and
     * localizedPageIsAddedToTheQueueInDelayedMode
     */
    protected function prepareLocalizedPageIsAddedToTheQueue(): void
    {
        $this->importDataSetFromFixture('localized_page_is_added_to_the_queue.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $uid = 2;
        $table = 'pages';
        $fields = [
            'title' => 'New Translated Rootpage',
            'l10n_parent' => 1,
            'pid' => 0,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function queueItemStaysWhenOverlayIsSetToHidden(): void
    {
        $this->importDataSetFromFixture('queue_entry_stays_when_overlay_set_to_hidden.xml');
        $this->assertIndexQueueContainsItemAmount(1);

        $status = 'update';
        $uid = 2;
        $fields = ['title' => 'New Translated Rootpage', 'pid' => 1, 'hidden' => 1];

        $table = 'pages';

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
        $this->assertIndexQueueContainsItemAmount(1);

        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertInstanceOf(Item::class, $firstQueueItem, 'Expect to get a queue item');
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        self::assertSame(
            'pages',
            $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName'
        );
    }

    /**
     * @test
     */
    public function localizedPageIsNotAddedToTheQueueWhenL10ParentIsHidden(): void
    {
        $this->importDataSetFromFixture('localized_page_is_not_added_to_the_queue_when_parent_hidden.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $uid = 2;
        $fields = ['title' => 'New Translated Rootpage', 'pid' => 1];
        $table = 'pages';

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function pageIsQueuedWhenContentElementIsChanged(): void
    {
        $this->importDataSetFromFixture('change_content_element.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'tt_content';
        $uid = 456;
        $fields = [
            'header' => 'New Content',
            'pid' => 1,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
    }

    /**
     * @test
     */
    public function pageIsQueuedWhenTranslatedContentElementIsChanged(): void
    {
        $this->preparePageIsQueuedWhenTranslatedContentElementIsChanged();
        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
    }

    /**
     * @test
     */
    public function pageIsQueuedWhenTranslatedContentElementIsChangedInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->preparePageIsQueuedWhenTranslatedContentElementIsChanged();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases pageIsQueuedWhenTranslatedContentElementIsChanged and
     * pageIsQueuedWhenTranslatedContentElementIsChangedInDelayedMode
     */
    protected function preparePageIsQueuedWhenTranslatedContentElementIsChanged(): void
    {
        $this->importDataSetFromFixture('change_translated_content_element.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'tt_content';
        $uid = 9999;
        $fields = [
            'header' => 'New Content',
            'pid' => 1,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForTitle();
        $this->assertIndexQueueContainsItemAmount(5);
    }

    /**
     * @test
     */
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForTitleInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForTitle();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(5);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases updateRootPageWithRecursiveUpdateFieldsConfiguredForTitle and
     * updateRootPageWithRecursiveUpdateFieldsConfiguredForTitleInDelayedMode
     */
    protected function prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title'
        );
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 1;
        $fields = [
            'title' => 'Update updateRootPageWithRecursiveUpdateFieldsConfiguredForTitle',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function updateSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title'
        );
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 3;
        $fields = [
            'title' => 'Update updateSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->prepareUpdateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitleInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareUpdateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle and
     * updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitleInDelayedMode
     */
    protected function prepareUpdateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title'
        );
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForDokType();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForDokTypeInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForDokType();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases updateRootPageWithRecursiveUpdateFieldsConfiguredForDokType and
     * updateRootPageWithRecursiveUpdateFieldsConfiguredForDokTypeInDelayedMode
     */
    protected function prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = doktype'
        );
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 1;
        $fields = [
            'subtitle' => 'Update updateRootPageWithRecursiveUpdateFieldsConfiguredForDokType',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function updateSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = doktype'
        );
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 3;
        $fields = [
            'subtitle' => 'Update updateSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = doktype'
        );
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'subtitle' => 'Update updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRootPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->importDataSetFromFixture('update_page_without_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 1;
        $fields = [
            'title' => 'Update updateRootPageWithoutRecursiveUpdateFieldsConfigured',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubChildPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->prepareUpdateSubChildPageWithoutRecursiveUpdateFieldsConfigured();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubChildPageWithoutRecursiveUpdateFieldsConfiguredInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareUpdateSubChildPageWithoutRecursiveUpdateFieldsConfigured();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases updateSubChildPageWithoutRecursiveUpdateFieldsConfigured and
     * updateSubChildPageWithoutRecursiveUpdateFieldsConfiguredInDelayedMode
     */
    protected function prepareUpdateSubChildPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->importDataSetFromFixture('update_page_without_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 3;
        $fields = [
            'title' => 'Update updateSubChildPageWithoutRecursiveUpdateFieldsConfigured',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->importDataSetFromFixture('update_page_without_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateSubSubChildPageWithoutRecursiveUpdateFieldsConfigured',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @return array
     */
    public function updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider(): array
    {
        return [
            'record-1' => [
                'uid' => 1,
                'root' => 1,
            ],
            'record-2' => [
                'uid' => 2,
                'root' => 111,
            ],
        ];
    }

    /**
     * @dataProvider updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider
     * @test
     *
     * @param int $uid
     * @param int $root
     */
    public function updateRecordOutsideSiteRootWithAdditionalWhereClause(int $uid, int $root): void
    {
        $this->prepareUpdateRecordOutsideSiteRootWithAdditionalWhereClause($uid);
        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame($uid, $firstQueueItem->getRecordUid());
        self::assertSame($root, $firstQueueItem->getRootPageUid());
    }

    /**
     * @dataProvider updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider
     * @test
     *
     * @param int $uid
     * @param int $root
     */
    public function updateRecordOutsideSiteRootWithAdditionalWhereClauseInDelayedMode(int $uid, int $root): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareUpdateRecordOutsideSiteRootWithAdditionalWhereClause($uid);

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame($uid, $firstQueueItem->getRecordUid());
        self::assertSame($root, $firstQueueItem->getRootPageUid());
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases updateRecordOutsideSiteRootWithAdditionalWhereClause and
     * updateRecordOutsideSiteRootWithAdditionalWhereClauseInDelayedMode
     *
     * @param int $uid
     */
    protected function prepareUpdateRecordOutsideSiteRootWithAdditionalWhereClause(int $uid): void
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot_with_additionalWhereClause.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 2
                    additionalWhereClause = uid=1
                    table = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }'
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 2
                    additionalWhereClause = uid=2
                    table = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }'
        );

        $this->assertEmptyIndexQueue();

        // create faked tce main call data
        $status = 'update';
        $table = 'tx_fakeextension_domain_model_foo';
        $fields = [
            'title' => 'foo',
            'pid' => 2,
        ];
        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRoot(): void
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 2
                    table = tx_fakeextension_domain_model_foo
                    fields {
                        title = title
                    }
                }
            }'
        );

        $this->assertEmptyIndexQueue();

        // create faked tce main call data
        $status = 'update';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 8;
        $fields = [
            'title' => 'i am outside the site root',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
            'pid' => 2,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRootReferencedInTwoSites(): void
    {
        $this->prepareUpdateRecordOutsideSiteRootReferencedInTwoSites();
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRootReferencedInTwoSitesInDelayedMode(): void
    {
        $this->extensionConfiguration->set('solr', ['monitoringType' => 1]);
        $this->prepareUpdateRecordOutsideSiteRootReferencedInTwoSites();

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);

        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(2);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases updateRecordOutsideSiteRootReferencedInTwoSites and
     * updateRecordOutsideSiteRootReferencedInTwoSitesInDelayedMode
     */
    protected function prepareUpdateRecordOutsideSiteRootReferencedInTwoSites(): void
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot_from_two_sites.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    table = tx_fakeextension_domain_model_foo
                    fields {
                        title = title
                    }
                }
            }'
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    table = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }'
        );

        $this->assertEmptyIndexQueue();

        // create faked tce main call data
        $status = 'update';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 8;
        $fields = [
            'title' => 'i am outside the site root and referenced in two sites',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
            'pid' => 3,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRootLocatedInOtherSite(): void
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot_from_other_siteroot.xml');
        $this->addTypoScriptToTemplateRecord(
            1,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    table = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }'
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    table = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }'
        );

        $this->assertEmptyIndexQueue();

        // create faked tce main call data
        $status = 'update';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 8;
        $fields = [
            'title' => 'i am in siteroot b but references also in siteroot a',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
            'pid' => 3,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredDefault(): void
    {
        $this->importDataSetFromFixture('update_page_use_configuration_monitor_tables.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateRecordMonitoringTablesConfiguredDefault',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredNotForTableBeingUpdated(): void
    {
        $this->prepareUpdateRecordMonitoringTablesTests(0, 'tt_content');
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredNotForTableBeingUpdatedInDelayedMode(): void
    {
        $this->prepareUpdateRecordMonitoringTablesTests(1, 'tt_content');

        $this->assertEmptyIndexQueue();
        $this->assertEmptyEventQueue();
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredForTableBeingUpdated(): void
    {
        $this->prepareUpdateRecordMonitoringTablesTests(0, 'pages, tt_content');
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredForTableBeingUpdatedInDelayedMode(): void
    {
        $this->assertEmptyIndexQueue();
        $this->prepareUpdateRecordMonitoringTablesTests(1, 'pages, tt_content');

        $this->assertEmptyIndexQueue();
        $this->assertEventQueueContainsItemAmount(1);
        $this->processEventQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases:
     * - updateRecordMonitoringTablesConfiguredNotForTableBeingUpdated
     * - updateRecordMonitoringTablesConfiguredNotForTableBeingUpdatedInDelayedModed
     * - updateRecordMonitoringTablesConfiguredForTableBeingUpdated
     * - updateRecordMonitoringTablesConfiguredForTableBeingUpdatedInDelayedModed
     *
     * @param int $monitoringType
     * @param string $useConfigurationMonitorTables
     */
    protected function prepareUpdateRecordMonitoringTablesTests(int $monitoringType, string $useConfigurationMonitorTables): void
    {
        $this->importDataSetFromFixture('update_page_use_configuration_monitor_tables.xml');
        $this->assertEmptyIndexQueue();

        $this->extensionConfiguration->set(
            'solr',
            ['monitoringType' => $monitoringType, 'useConfigurationMonitorTables' => $useConfigurationMonitorTables]
        );

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Lorem ipsum dolor sit amet',
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler
        );
    }

    /**
     * This testcase checks if we can create a new testpage on the root level without any errors.
     *
     * @test
     * @throws TestingFrameworkCoreException
     */
    public function canCreateSiteOneRootLevel(): void
    {
        $this->importDataSetFromFixture('can_create_new_page.xml');
        $this->setUpBackendUserFromFixture(1);

        $this->assertIndexQueueContainsItemAmount(0);
        $dataHandler = $this->getDataHandler();
        $dataHandler->start(['pages' => ['NEW' => ['hidden' => 0, 'pid' => 0]]], []);
        $dataHandler->process_datamap();

        // the item is outside a siteroot so we should not have any queue entry
        $this->assertIndexQueueContainsItemAmount(0);
    }

    /**
     * This testcase checks if we can create a new testpage on the root level without any errors.
     *
     * @test
     */
    public function canCreateSubPageBelowSiteRoot(): void
    {
        $this->importDataSetFromFixture('can_create_new_page.xml');
        $this->setUpBackendUserFromFixture(1);

        $this->assertIndexQueueContainsItemAmount(0);
        $dataHandler = $this->getDataHandler();
        $dataHandler->start(['pages' => ['NEW' => ['hidden' => 0, 'pid' => 1]]], []);
        $dataHandler->process_datamap();

        // we should have one item in the solr queue
        $this->assertIndexQueueContainsItemAmount(1);
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
