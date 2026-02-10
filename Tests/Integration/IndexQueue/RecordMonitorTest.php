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
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\SkippedWithMessageException;
use Psr\Log\LogLevel;
use Traversable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the record monitor
 */
class RecordMonitorTest extends IntegrationTestBase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension',
    ];

    protected RecordMonitor $recordMonitor;
    protected DataHandler $dataHandler;
    protected Queue $indexQueue;
    protected ExtensionConfiguration $extensionConfiguration;
    protected EventQueueItemRepository $eventQueue;
    protected BackendUserAuthentication $backendUser;

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
        // fake that a backend user is logged in
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sites_setup_and_data_set/be_users.csv');
        $this->backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
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
            $this->eventQueue,
            $this->backendUser,
            $GLOBALS['LANG'],
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
            'Index queue is empty and was expected to be not empty.',
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

    /**
     * Regression test for issue #155. Saving the root page causes sql errors:
     *
     * TYPO3\CMS\Core\Database\DatabaseConnection::exec_SELECTquery" (60 chars)
     * ERROR => "You have an error in your SQL syntax; check the manual that corresponds to y
     * our MariaDB server version for the right syntax to use near 'OR (mount_pid=1
     * AND mount_pid_ol=1)) AND doktype = 7 AND no_search = 0 AND pages' at line 1" (228 chars)
     *
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/155
     */
    #[Test]
    public function canUpdateRootPageRecordWithoutSQLErrorFromMountPages(): void
    {
        throw new SkippedWithMessageException(
            'Skipping canUpdateRootPageRecordWithoutSQLErrorFromMountPages',
            9115338385,
        );

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // @todo: detecting the sql error is a little bit ugly but it seems there is no other possibility right now
        ob_start();
        $this->recordMonitor->processCmdmap_postProcess(
            'version',
            'pages',
            1,
            ['action' => 'swap'],
        );

        $output = trim(ob_get_contents());
        ob_end_clean();

        self::assertStringNotContainsString(
            'You have an error in your SQL syntax',
            $output,
            'We expect no sql error during the update of a regular page root record',
        );

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();
    }

    /**
     * Regression test for issue #48. Indexing of new records will crash if the name of the Indexing
     * Queue Configuration is different from tablename
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/48
     */
    #[Test]
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
            'Item was queued with unexpected configuration',
        );
    }

    #[Test]
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
            'Item was queued with unexpected configuration',
        );
    }

    #[Test]
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
        // create faked tce main call data
        $status = 'new';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = [
            'title' => 'testnews',
            'pid' => 1,
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tstamp' => 1000000,
        ];
        $this->dataHandler->substNEWwithIDs = ['NEW566a9eac309d8193936351' => 8];

        $this->importCSVDataSet(__DIR__ . '/Fixtures/new_non_pages_record_is_using_correct_configuration_name.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index {
                queue {
                    foo = 1
                    foo {
                        type = tx_fakeextension_domain_model_foo
                        fields.title = title
                    }
                }
            }
            ',
        );

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler,
        );
    }

    #[Test]
    public function canQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved(): void
    {
        $this->prepareCanQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved();

        // we expect that all subpages of 1 and 1 its selft have been requeued but not more
        // pages with uid 1, 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(3);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/reindex_subpages_when_extendToSubpages_set_and_hidden_removed.csv');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['hidden' => 0], ['uid' => 17]);
        $changeSet = ['hidden' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $dataHandler);
    }

    #[Test]
    public function canQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved(): void
    {
        $this->prepareCanQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved();

        // we expect that all subpages of 1 have been requeued, but 1 not because it is still hidden
        // pages with uid 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(2);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/reindex_subpages_when_hidden_set_and_extendToSubpage_removed.csv');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['extendToSubpages' => 0], ['uid' => 17]);
        $changeSet = ['extendToSubpages' => 0];

        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $this->dataHandler);
    }

    #[Test]
    public function canQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved(): void
    {
        $this->prepareCanQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved();

        // we expect that page 1 incl. subpages has been requeued
        // pages with uid 10, 11 and 100 should be in index
        $this->assertIndexQueueContainsItemAmount(3);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/reindex_subpages_when_hidden_and_extendToSubpage_flags_removed.csv');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName(ConnectionPool::DEFAULT_CONNECTION_NAME);
        $connection->update('pages', ['extendToSubpages' => 0, 'hidden' => 0], ['uid' => 17]);
        $changeSet = ['extendToSubpages' => 0, 'hidden' => 0];

        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 17, $changeSet, $this->dataHandler);
    }

    #[Test]
    public function queueIsNotFilledWhenItemIsSetToHidden(): void
    {
        $this->prepareQueueIsNotFilledWhenItemIsSetToHidden();

        // we assert that the index queue is still empty because the page was only set to hidden
        $this->assertEmptyIndexQueue();
    }
    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/reindex_subpages_when_hidden_set_and_extendToSubpage_removed.csv');

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
     */
    #[Test]
    public function logMessageIsCreatedWhenRecordWithoutPidIsCreated(): void
    {
        $loggerMock = $this->getMockBuilder(SolrLogManager::class)
            ->onlyMethods([
                'log',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $expectedSeverity = LogLevel::WARNING;
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
            $loggerMock,
        );
        GeneralUtility::addInstance(DataUpdateHandler::class, $dataUpdateHandler);

        // we expect that this exception is getting thrown, because a record without pid was updated

        // create faked tce main call data
        $status = 'new';
        $table = 'tt_content';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = [
            'title' => 'testce',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tstamp' => 1000000,
        ];

        $this->importCSVDataSet(__DIR__ . '/Fixtures/exception_is_triggered_without_pid.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index {
                queue {
                    foo = 1
                    foo {
                        type = tx_fakeextension_domain_model_foo
                        fields.title = title
                    }
                }
            }
            ',
        );

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $dataHandler,
        );
    }

    /**
     * This testcase checks, that a queue item will be removed when an unexisting record was updated
     */
    #[Test]
    public function queueEntryIsRemovedWhenUnExistingRecordWasUpdated(): void
    {
        $this->prepareQueueEntryIsRemovedWhenUnExistingRecordWasUpdated();
        // the queue entry should be removed since the record itself does not exist
        $this->assertEmptyIndexQueue();
    }

    #[Test]
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
            'tstamp' => 1000000,
            'pid' => 1,
        ];

        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_unexisting_item_will_remove_queue_entry.csv');

        // there should be one item in the queue.
        $this->assertIndexQueueContainsItemAmount(1);
        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $status,
            $table,
            $uid,
            $fields,
            $this->dataHandler,
        );
    }

    /**
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/639
     */
    #[Test]
    public function canUseCorrectIndexingConfigurationForANewCustomPageTypeRecord(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/custom_page_doktype.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
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
                    type = pages
                    additionalWhereClause = doktype = 130 AND no_search = 0

                    fields {
                        pagetype_stringS = TEXT
                        pagetype_stringS {
                            value = Custom Page Type
                        }
                    }
                }
            }
            ',
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
            'tstamp' => 1000000,
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
            $this->dataHandler,
        );

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('pages', 8);
        self::assertSame(1, count($items));
        self::assertSame(
            'custom_page_type',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration',
        );
    }

    #[Test]
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
            'Item was queued with unexpected configuration',
        );
    }

    #[Test]
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
            'Item was queued with unexpected configuration',
        );
    }

    /**
     * Prepares the test cases canQueueUpdatePagesWithCustomPageType and
     * canQueueUpdatePagesWithCustomPageTypeInDelayedMode
     */
    protected function prepareCanQueueUpdatePagesWithCustomPageType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/custom_page_doktype.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
             plugin.tx_solr.index.queue {
                custom_page_type = 1
                custom_page_type {
                    initialization = ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page
                    allowedPageTypes = 130
                    indexer = ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer
                    type = pages
                    additionalWhereClause = doktype = 130 AND no_search = 0

                    fields {
                        pagetype_stringS = TEXT
                        pagetype_stringS {
                            value = Custom Page Type
                        }
                    }
                }
            }',
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

    #[Test]
    public function canHandePageTreeMovement(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_handle_page_tree_movement.csv');
        $this->assertEmptyIndexQueue();

        $this->dataHandler->start(
            [],
            ['pages' => [10 => ['move' => 2]]],
            $this->backendUser,
        );
        $this->dataHandler->process_cmdmap();
        $this->assertIndexQueueContainsItemAmount(4);
    }

    #[Test]
    public function canHandePageTreeMovementIfPageTreeIsMovedToSysfolderWithDisabledOptionIncludeSubEntriesInSearch(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_handle_page_tree_movement.csv');
        $this->assertEmptyIndexQueue();

        $this->dataHandler->start(
            [],
            ['pages' => [10 => ['move' => 4]]],
            $this->backendUser,
        );
        $this->dataHandler->process_cmdmap();
        $this->assertEmptyIndexQueue();
    }

    #[Test]
    public function canHandePageTreeMovementIfPageTreeIsMounted(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_handle_mounted_page_tree_movement.csv');
        $this->assertEmptyIndexQueue();

        $this->dataHandler->start(
            [],
            ['pages' => [10 => ['move' => 2]]],
            $this->backendUser,
        );
        $this->dataHandler->process_cmdmap();
        $this->assertIndexQueueContainsItemAmount(3);
    }

    #[Test]
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
            $this->dataHandler,
        );

        // we assert that the page is added twice, once for the original tree and once for the mounted tree
        $this->assertIndexQueueContainsItemAmount(2);
    }

    #[Test]
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
            $this->dataHandler,
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/mount_pages_are_added_once.csv');
        $this->assertEmptyIndexQueue();

        $data = [
            'status' => 'update',
            'table' => 'pages',
            'uid' => 40,
            'fields' => [
                'title' => 'testpage',
                'starttime' => 1000000,
                'endtime' => 1100000,
                'tstamp' => 1000000,
                'pid' => 4,
            ],
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            $data['status'],
            $data['table'],
            $data['uid'],
            $data['fields'],
            $this->dataHandler,
        );

        return $data;
    }

    #[Test]
    #[DataProvider('mountedPageIsUpdatedInQueueOnUpdateDataProvider')]
    public function mountedPageIsUpdatedInQueueOnUpdate(
        int $monitoringType,
        int $itemUid,
        int $itemsInEventQueue,
        array $dataMap,
        array $expectedResult,
    ): void {
        $this->extensionConfiguration->set('solr', ['monitoringType' => $monitoringType]);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/mount_page_updates.csv');

        $this->dataHandler->start($dataMap, [], $this->backendUser);
        $this->dataHandler->process_datamap();

        $this->assertEventQueueContainsItemAmount($itemsInEventQueue);
        if ($monitoringType === 1) {
            $this->processEventQueue();
        }

        $items = $this->indexQueue->getItems('pages', $itemUid);
        self::assertCount(count($expectedResult), $items, 'Index queue items not added/updated as expected.');

        foreach ($expectedResult as $itemCount => $itemData) {
            self::assertEquals($itemData['mountPointIdentifier'], $items[$itemCount]->getMountPointIdentifier());
            self::assertGreaterThan(0, $items[$itemCount]->getChanged());

            if (($itemData['mountPageSource'] ?? null) !== null) {
                self::assertEquals(
                    [
                        'mountPageSource' => $itemData['mountPageSource'],
                        'mountPageDestination' => $itemData['mountPageDestination'],
                        'isMountedPage' => 1,
                    ],
                    $items[$itemCount]->getIndexingProperties(),
                );
            }
        }
    }

    public static function mountedPageIsUpdatedInQueueOnUpdateDataProvider(): Traversable
    {
        $hideContentElementOnMountedPageInSysFolder = [
            'monitoringType' => 0,
            'itemUid' => 20,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'tt_content' => [
                    20 => ['hidden' => 1],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '20-30-1',
                    'mountPageSource' => '20',
                    'mountPageDestination' => '30',
                ],
            ],
        ];
        yield 'hide content element on mounted page in sys_folder (default monitoring)'
            => $hideContentElementOnMountedPageInSysFolder;

        yield 'hide content element on mounted page in sys_folder (delayed monitoring)' => array_merge(
            $hideContentElementOnMountedPageInSysFolder,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $enableContentElementOnMountedPageInSysFolder = [
            'monitoringType' => 0,
            'itemUid' => 20,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'tt_content' => [
                    21 => ['hidden' => 0],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '20-30-1',
                    'mountPageSource' => '20',
                    'mountPageDestination' => '30',
                ],
            ],
        ];
        yield 'enable content element on mounted page in sys_folder (default monitoring)'
            => $enableContentElementOnMountedPageInSysFolder;

        yield 'enable content element on mounted page in sys_folder (delayed monitoring)' => array_merge(
            $enableContentElementOnMountedPageInSysFolder,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $hideContentElementOnMountedPageOnRoot = [
            'monitoringType' => 0,
            'itemUid' => 21,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'tt_content' => [
                    22 => ['hidden' => 1],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '',
                ],
                1 => [
                    'mountPointIdentifier' => '21-31-1',
                    'mountPageSource' => '21',
                    'mountPageDestination' => '31',
                ],
            ],
        ];
        yield 'hide content element on mounted page on root (default monitoring)'
            => $hideContentElementOnMountedPageOnRoot;

        yield 'hide content element on mounted page on root (delayed monitoring)' => array_merge(
            $hideContentElementOnMountedPageOnRoot,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $updateMountedPageInSysFolder = [
            'monitoringType' => 0,
            'itemUid' => 20,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'pages' => [
                    20 => ['subtitle' => 'new subtitle'],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '20-30-1',
                    'mountPageSource' => '20',
                    'mountPageDestination' => '30',
                ],
            ],
        ];
        yield 'update mounted page in sys_folder (default monitoring)' => $updateMountedPageInSysFolder;

        yield 'update mounted page in sys_folder (delayed monitoring)' => array_merge(
            $updateMountedPageInSysFolder,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $updateMountedPageInRoot = [
            'monitoringType' => 0,
            'itemUid' => 21,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'pages' => [
                    21 => ['subtitle' => 'new subtitle'],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '',
                ],
                1 => [
                    'mountPointIdentifier' => '21-31-1',
                    'mountPageSource' => '21',
                    'mountPageDestination' => '31',
                ],
            ],
        ];
        yield 'update mounted page in root (default monitoring)' => $updateMountedPageInRoot;

        yield 'update mounted page in root (delayed monitoring)' => array_merge(
            $updateMountedPageInRoot,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $hideMountedPageInSysFolder = [
            'monitoringType' => 0,
            'itemUid' => 20,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'pages' => [
                    20 => ['hidden' => 1],
                ],
            ],
            'expectedResult' => [],
        ];
        yield 'hide mounted page in sys_folder (default monitoring)' => $hideMountedPageInSysFolder;

        yield 'hide mounted page in sys_folder (delayed monitoring)' => array_merge(
            $hideMountedPageInSysFolder,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $hideMountedPageInRoot = [
            'monitoringType' => 0,
            'itemUid' => 21,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'pages' => [
                    21 => ['hidden' => 1],
                ],
            ],
            'expectedResult' => [],
        ];
        yield 'hide mounted page in root (default monitoring)' => $hideMountedPageInRoot;

        yield 'hide mounted page in root (delayed monitoring)' => array_merge(
            $hideMountedPageInRoot,
            [
                'monitoringType' => 1,
                'itemsInEventQueue' => 2,
            ],
        );

        $hideContentElementOnAnotherMountedPageInSysFolderWhichIsInQueue = [
            'monitoringType' => 0,
            'itemUid' => 22,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'tt_content' => [
                    24 => ['hidden' => 1],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '22-32-1',
                    'mountPageSource' => '22',
                    'mountPageDestination' => '32',
                ],
            ],
        ];
        yield 'hide content element on another mounted page in sys_folder'
                . ', which is already in queue (default monitoring)'
            => $hideContentElementOnAnotherMountedPageInSysFolderWhichIsInQueue;

        yield 'hide content element on another mounted page in sys_folder'
                . ', which is already in queue (delayed monitoring)' => array_merge(
                    $hideContentElementOnAnotherMountedPageInSysFolderWhichIsInQueue,
                    [
                        'monitoringType' => 1,
                        'itemsInEventQueue' => 2,
                    ],
                );

        $enableContentElementOnMountedPageInSysFolderWhichIsInQueue = [
            'monitoringType' => 0,
            'itemUid' => 22,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'tt_content' => [
                    25 => ['hidden' => 0],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '22-32-1',
                    'mountPageSource' => '22',
                    'mountPageDestination' => '32',
                ],
            ],
        ];
        yield 'enable content element on another mounted page in sys_folder'
            . ', which is already in queue (default monitoring)'
            => $enableContentElementOnMountedPageInSysFolderWhichIsInQueue;

        yield 'enable content element on another mounted page in sys_folder'
            . ', which is already in queue (delayed monitoring)' => array_merge(
                $enableContentElementOnMountedPageInSysFolderWhichIsInQueue,
                [
                    'monitoringType' => 1,
                    'itemsInEventQueue' => 2,
                ],
            );

        $updateMountedPageInSysFolderWhichIsInQueue = [
            'monitoringType' => 0,
            'itemUid' => 22,
            'itemsInEventQueue' => 0,
            'dataMap' => [
                'pages' => [
                    22 => ['subtitle' => 'new subtitle'],
                ],
            ],
            'expectedResult' => [
                0 => [
                    'mountPointIdentifier' => '22-32-1',
                    'mountPageSource' => '22',
                    'mountPageDestination' => '32',
                ],
            ],
        ];
        yield 'update mounted page in sys_folder'
            . ', which is already in queue (default monitoring)' => $updateMountedPageInSysFolderWhichIsInQueue;

        yield 'update mounted page in sys_folder'
            . ', which is already in queue (delayed monitoring)' => array_merge(
                $updateMountedPageInSysFolderWhichIsInQueue,
                [
                    'monitoringType' => 1,
                    'itemsInEventQueue' => 2,
                ],
            );
    }

    #[Test]
    public function localizedPageIsAddedToTheQueue(): void
    {
        $this->prepareLocalizedPageIsAddedToTheQueue();

        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);

        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        self::assertSame(
            'pages',
            $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName',
        );
    }

    #[Test]
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
            'First queue item has unexpected indexingConfigurationName',
        );
        $this->assertEmptyEventQueue();
    }

    /**
     * Prepares the test cases localizedPageIsAddedToTheQueue and
     * localizedPageIsAddedToTheQueueInDelayedMode
     */
    protected function prepareLocalizedPageIsAddedToTheQueue(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/localized_page_is_added_to_the_queue.csv');
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
            $this->dataHandler,
        );
    }

    #[Test]
    public function queueItemStaysWhenOverlayIsSetToHidden(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/queue_entry_stays_when_overlay_set_to_hidden.csv');
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
            $this->dataHandler,
        );
        $this->assertIndexQueueContainsItemAmount(1);

        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertInstanceOf(Item::class, $firstQueueItem, 'Expect to get a queue item');
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        self::assertSame(
            'pages',
            $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName',
        );
    }

    #[Test]
    public function localizedPageIsNotAddedToTheQueueWhenL10ParentIsHidden(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/localized_page_with_hidden_parent.csv');
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
            $this->dataHandler,
        );
        $this->assertEmptyIndexQueue();
    }

    #[Test]
    public function pageIsQueuedWhenContentElementIsChanged(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/change_content_element.csv');
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
            $this->dataHandler,
        );

        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
    }

    #[Test]
    public function pageIsQueuedWhenTranslatedContentElementIsChanged(): void
    {
        $this->preparePageIsQueuedWhenTranslatedContentElementIsChanged();
        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/change_translated_content_element.csv');
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
            $this->dataHandler,
        );
    }

    #[Test]
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForTitle();
        $this->assertIndexQueueContainsItemAmount(5);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title',
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
            $this->dataHandler,
        );
    }

    #[Test]
    public function updateSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title',
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
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(2);
    }

    #[Test]
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle(): void
    {
        $this->prepareUpdateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title',
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
            $this->dataHandler,
        );
    }

    #[Test]
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->prepareUpdateRootPageWithRecursiveUpdateFieldsConfiguredForDokType();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = doktype',
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
            $this->dataHandler,
        );
    }

    #[Test]
    public function updateSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = doktype',
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
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            'plugin.tx_solr.index.queue.pages.recursiveUpdateFields = doktype',
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
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function updateRootPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
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
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function updateSubChildPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->prepareUpdateSubChildPageWithoutRecursiveUpdateFieldsConfigured();
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
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
            $this->dataHandler,
        );
    }

    #[Test]
    public function updateSubSubChildPageWithoutRecursiveUpdateFieldsConfigured(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
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
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    public static function updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider(): Traversable
    {
        yield 'record-1' => [
            'uid' => 1,
            'root' => 1,
        ];
        yield 'record-2' => [
            'uid' => 2,
            'root' => 111,
        ];
    }

    /**
     * @param int $uid
     * @param int $root
     */
    #[DataProvider('updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider')]
    #[Test]
    public function updateRecordOutsideSiteRootWithAdditionalWhereClause(int $uid, int $root): void
    {
        $this->prepareUpdateRecordOutsideSiteRootWithAdditionalWhereClause($uid);
        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);
        self::assertSame($uid, $firstQueueItem->getRecordUid());
        self::assertSame($root, $firstQueueItem->getRootPageUid());
    }

    /**
     * @param int $uid
     * @param int $root
     */
    #[DataProvider('updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider')]
    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_record_outside_siteroot_with_additionalWhereClause.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 2
                    additionalWhereClause = uid=1
                    type = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }',
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 2
                    additionalWhereClause = uid=2
                    type = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }',
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

    #[Test]
    public function updateRecordOutsideSiteRoot(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_record_outside_siteroot.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 2
                    type = tx_fakeextension_domain_model_foo
                    fields {
                        title = title
                    }
                }
            }',
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
            'tstamp' => 1000000,
            'pid' => 2,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function updateRecordOutsideSiteRootReferencedInTwoSites(): void
    {
        $this->prepareUpdateRecordOutsideSiteRootReferencedInTwoSites();
        $this->assertIndexQueueContainsItemAmount(2);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_record_outside_siteroot_from_two_sites.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    type = tx_fakeextension_domain_model_foo
                    fields {
                        title = title
                    }
                }
            }',
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    type = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }',
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
            'tstamp' => 1000000,
            'pid' => 3,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
    }

    #[Test]
    public function updateRecordOutsideSiteRootLocatedInOtherSite(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_record_outside_siteroot_from_other_siteroot.csv');
        $this->addTypoScriptToTemplateRecord(
            1,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    type = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }',
        );
        $this->addTypoScriptToTemplateRecord(
            111,
            /* @lang TYPO3_TypoScript */
            '
            plugin.tx_solr.index.queue {
                foo = 1
                foo {
                    additionalPageIds = 3
                    type = tx_fakeextension_domain_model_foo
                    fields.title = title
                }
            }',
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
            'tstamp' => 1000000,
            'pid' => 3,
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(2);
    }

    #[Test]
    public function updateRecordMonitoringTablesConfiguredDefault(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
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
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
    public function updateRecordMonitoringTablesConfiguredNotForTableBeingUpdated(): void
    {
        $this->prepareUpdateRecordMonitoringTablesTests(0, 'tt_content');
        $this->assertEmptyIndexQueue();
    }

    #[Test]
    public function updateRecordMonitoringTablesConfiguredNotForTableBeingUpdatedInDelayedMode(): void
    {
        $this->prepareUpdateRecordMonitoringTablesTests(1, 'tt_content');

        $this->assertEmptyIndexQueue();
        $this->assertEmptyEventQueue();
    }

    #[Test]
    public function updateRecordMonitoringTablesConfiguredForTableBeingUpdated(): void
    {
        $this->prepareUpdateRecordMonitoringTablesTests(0, 'pages, tt_content');
        $this->assertIndexQueueContainsItemAmount(1);
    }

    #[Test]
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_subpages.csv');
        $this->assertEmptyIndexQueue();

        $this->extensionConfiguration->set(
            'solr',
            ['monitoringType' => $monitoringType, 'useConfigurationMonitorTables' => $useConfigurationMonitorTables],
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
            $this->dataHandler,
        );
    }

    /**
     * This testcase checks if we can create a new testpage on the root level without any errors.
     */
    #[Test]
    public function canCreateSiteOneRootLevel(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_can_create_new_page.csv');

        $this->assertIndexQueueContainsItemAmount(0);
        $dataHandler = $this->getDataHandler();
        $dataHandler->start(['pages' => ['NEW' => ['hidden' => 0, 'pid' => 0, 'title' => 'new subpage']]], []);
        $dataHandler->process_datamap();

        // the item is outside a siteroot, so we should not have any queue entry
        $this->assertIndexQueueContainsItemAmount(0);
    }

    /**
     * This testcase checks if we can create a new testpage on the root level without any errors.
     */
    #[Test]
    public function canCreateSubPageBelowSiteRoot(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/recordmonitor_can_create_new_page.csv');

        $this->assertIndexQueueContainsItemAmount(0);
        $dataHandler = $this->getDataHandler();
        $dataHandler->start(['pages' => ['NEW' => ['hidden' => 0, 'pid' => 1]]], []);
        $dataHandler->process_datamap();

        // we should have one item in the solr queue
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * Tests if updates on access restricted pages lead to index queue updates
     *
     * https://github.com/TYPO3-Solr/ext-solr/issues/3225
     */
    #[Test]
    public function canQueueAccessRestrictedPageOnPageUpdate(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/update_access_restricted_page.csv');
        $this->assertEmptyIndexQueue();

        $this->recordMonitor->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            2,
            [
                'title' => 'Access restricted page',
            ],
            $this->dataHandler,
        );

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * Returns the data handler
     *
     * @return DataHandler
     */
    protected function getDataHandler(): DataHandler
    {
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        /* @retrun  DataHandler */
        return GeneralUtility::makeInstance(DataHandler::class);
    }
}
