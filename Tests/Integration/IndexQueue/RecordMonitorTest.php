<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the record monitor
 *
 * @author Timo Schmidt
 */
class RecordMonitorTest extends IntegrationTest
{

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

    public function setUp()
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
        $this->recordMonitor = GeneralUtility::makeInstance(RecordMonitor::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
    }

    public function tearDown()
    {
        unset($this->recordMonitor, $this->dataHandler, $this->indexQueue);
        parent::tearDown();
    }

    /**
     * @return void
     */
    protected function assertEmptyIndexQueue()
    {
        $this->assertEquals(0, $this->indexQueue->getAllItemsCount(), 'Index queue is not empty as expected');
    }

    /**
     * @return void
     */
    protected function assertNotEmptyIndexQueue()
    {
        $this->assertGreaterThan(0, $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to be not empty.');
    }

    /**
     * @param $amount
     */
    protected function assertIndexQueueContainsItemAmount($amount)
    {
        $this->assertEquals($amount, $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to contain ' . (int)$amount . ' items.');
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
    public function canUpdateRootPageRecordWithoutSQLErrorFromMountPages()
    {
        $this->importDataSetFromFixture('update_mount_point_is_updating_the_mount_point_correctly.xml');

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

        $this->assertNotContains('You have an error in your SQL syntax', $output,
            'We expect no sql error during the update of a regular page root record');

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();
    }

    /**
     * Regression test for issue #48. Indexing of new records will crash if the name of the Indexing
     * Queue Configuration is different from tablename
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/48
     * @test
     */
    public function canUseCorrectIndexingConfigurationForANewNonPagesRecord()
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
            'tsstamp' => 1000000
        ];
        $this->dataHandler->substNEWwithIDs = ['NEW566a9eac309d8193936351' => 8];

        $this->importDataSetFromFixture('new_non_pages_record_is_using_correct_configuration_name.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('tx_fakeextension_domain_model_foo', 8);
        $this->assertSame(1, count($items));
        $this->assertSame('foo', $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration');
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenExtendToSubPagesWasSetAndHiddenFlagWasRemoved()
    {
        $this->importDataSetFromFixture('reindex_subpages_when_extendToSubpages_set_and_hidden_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = $this->getDatabaseConnection();
        $connection->updateArray('pages', ['uid' => 1], ['hidden' => 0]);
        $changeSet = ['hidden' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // we expect that all subpages of 1 and 1 its selft have been requeued but not more
        // pages with uid 1, 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(3);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenHiddenFlagIsSetAndExtendToSubPagesFlagWasRemoved()
    {
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_set_and_extendToSubpage_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = $this->getDatabaseConnection();
        $connection->updateArray('pages', ['uid' => 1], ['extendToSubpages' => 0]);
        $changeSet = ['extendToSubpages' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // we expect that all subpages of 1 have been requeued, but 1 not because it is still hidden
        // pages with uid 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function canQueueSubPagesWhenHiddenAndExtendToSubPagesFlagsWereRemoved()
    {
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_and_extendToSubpage_flags_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = $this->getDatabaseConnection();
        $connection->updateArray('pages', ['uid' => 1], ['extendToSubpages' => 0, 'hidden' => 0]);
        $changeSet = ['extendToSubpages' => 0, 'hidden' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // we expect that page 1 incl. subpages has been requeued
        // pages with uid 10, 11 and 100 should be in index
        $this->assertIndexQueueContainsItemAmount(3);
    }

    /**
     * @test
     */
    public function queueIsNotFilledWhenItemIsSetToHidden()
    {
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_set_and_extendToSubpage_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = $this->getDatabaseConnection();
        $connection->updateArray('pages', ['uid' => 1], ['hidden' => 1]);

        $changeSet = ['hidden' => 1];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // we assert that the index queue is still empty because the page was only set to hidden
        $this->assertEmptyIndexQueue();
    }

    /**
     * When a record without pid is processed an exception should be thrown.
     *
     * @test
     */
    public function logMessageIsCreatedWhenRecordWithoutPidIsCreated()
    {
        $loggerMock = $this->getMockBuilder(SolrLogManager::class)->setMethods([])->disableOriginalConstructor()->getMock();

        $expectedSeverity = SolrLogManager::WARNING;
        $expectedMessage = 'Record without valid pid was processed tx_fakeextension_domain_model_foo:NEW566a9eac309d8193936351';
        $loggerMock->expects($this->once())->method('log')->with($expectedSeverity, $expectedMessage);
        $this->recordMonitor->setLogger($loggerMock);

        // we expect that this exception is getting thrown, because a record without pid was updated

        // create fake extension database table and TCA
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        // create faked tce main call data
        $status = 'update';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = [
            'title' => 'testnews',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000
        ];

        $this->importDataSetFromFixture('exception_is_triggered_without_pid.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();
        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);
    }

    /**
     * This testcase checks, that a queue item will be removed when an unexisting record was updated
     *
     * @test
     */
    public function queueEntryIsRemovedWhenUnExistingRecordWasUpdated()
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
            'pid' => 1
        ];

        $this->importDataSetFromFixture('update_unexisting_item_will_remove_queue_entry.xml');

        // there should be one item in the queue.
        $this->assertIndexQueueContainsItemAmount(1);
        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        // the queue entry should be removed since the record itself does not exist
        $this->assertEmptyIndexQueue();
    }

    /**
     * @see https://github.com/TYPO3-Solr/ext-solr/issues/639
     * @test
     */
    public function canUseCorrectIndexingConfigurationForANewCustomPageTypeRecord()
    {
        $this->importDataSetFromFixture('can_use_correct_indexing_configuration_for_a_new_custom_page_type_record.xml');

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
            'tsstamp' => 1000000
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
        $this->assertSame(1, count($items));
        $this->assertSame(
            'custom_page_type',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     * @test
     */
    public function canQueueUpdatePagesWithCustomPageType()
    {
        $this->importDataSetFromFixture('can_use_correct_indexing_configuration_for_a_new_custom_page_type_record.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $connection = $this->getDatabaseConnection();
        $connection->updateArray('pages', ['uid' => 8], ['hidden' => 0]);

        $changeSet = ['hidden' => 0];

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 8, $changeSet, $dataHandler);

        // we expect to have an index queue item now
        $this->assertNotEmptyIndexQueue();

        // and we check that the record in the queue has the expected configuration name
        $items = $this->indexQueue->getItems('pages', 8);
        $this->assertSame(1, count($items));
        $this->assertSame(
            'custom_page_type',
            $items[0]->getIndexingConfigurationName(),
            'Item was queued with unexpected configuration'
        );
    }

    /**
     *
     *
     * @test
     */
    public function mountPointIsOnlyAddedOnceForEachTree()
    {
        $this->importDataSetFromFixture('mount_pages_are_added_once.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 40;
        $fields = [
            'title' => 'testpage',
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000,
            'pid' => 4
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        // we assert that the page is added twice, once for the original tree and once for the mounted tree
        $this->assertIndexQueueContainsItemAmount(2);
        /* @var $indexQueue Queue */
        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);
        // we assert that the page is added twice, once for the original tree and once for the mounted tree
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function localizedPageIsAddedToTheQueue()
    {
        $this->importDataSetFromFixture('localized_page_is_added_to_the_queue.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $uid = 2;
        $table = 'pages';
        $fields = [
            'title' => 'New Translated Rootpage',
            'l10n_parent' => 1,
            'pid' => 0
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);

        $this->assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        $this->assertSame('pages', $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName');
    }


    /**
     * @test
     */
    public function queueItemStaysWhenOverlayIsSetToHidden()
    {
        $this->importDataSetFromFixture('queue_entry_stays_when_overlay_set_to_hidden.xml');
        $this->assertIndexQueueContainsItemAmount(1);

        $status = 'update';
        $uid = 2;
        $fields = ['title' => 'New Translated Rootpage', 'pid' => 1, 'hidden' => 1];

        $table = 'pages';

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(1);

        $firstQueueItem = $this->indexQueue->getItem(1);
        $this->assertInstanceOf(Item::class, $firstQueueItem, 'Expect to get a queue item');
        $this->assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
        $this->assertSame('pages', $firstQueueItem->getIndexingConfigurationName(),
            'First queue item has unexpected indexingConfigurationName');
    }

    /**
     * @test
     */
    public function localizedPageIsNotAddedToTheQueueWhenL10ParentIsHidden()
    {
        $this->importDataSetFromFixture('localized_page_is_not_added_to_the_queue_when_parent_hidden.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $uid = 2;
        $fields = ['title' => 'New Translated Rootpage', 'pid' => 1];
        $table = 'pages';

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function pageIsQueueWhenContentElementIsChanged()
    {
        $this->importDataSetFromFixture('change_content_element.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'tt_content';
        $uid = 456;
        $fields = [
            'header' => 'New Content',
            'pid' => 1
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $firstQueueItem = $this->indexQueue->getItem(1);
        $this->assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
    }

    /**
     * @test
     */
    public function pageIsQueueWhenTranslatedContentElementIsChanged()
    {
        $this->importDataSetFromFixture('change_translated_content_element.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'tt_content';
        $uid = 9999;
        $fields = [
            'header' => 'New Content',
            'pid' => 1
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $firstQueueItem = $this->indexQueue->getItem(1);
        $this->assertSame('pages', $firstQueueItem->getType(), 'First queue item has unexpected type');
    }

    /**
     * @test
     */
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForTitle()
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 1;
        $fields = [
            'title' => 'Update updateRootPageWithRecursiveUpdateFieldsConfiguredForTitle'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(5);
    }

    /**
     * @test
     */
    public function updateSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle()
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 3;
        $fields = [
            'title' => 'Update updateSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle()
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForTitle'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRootPageWithRecursiveUpdateFieldsConfiguredForDokType()
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 1;
        $fields = [
            'subtitle' => 'Update updateRootPageWithRecursiveUpdateFieldsConfiguredForDokType'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType()
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 3;
        $fields = [
            'subtitle' => 'Update updateSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType()
    {
        $this->importDataSetFromFixture('update_page_with_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'subtitle' => 'Update updateSubSubChildPageWithRecursiveUpdateFieldsConfiguredForDokType'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRootPageWithoutRecursiveUpdateFieldsConfigured()
    {
        $this->importDataSetFromFixture('update_page_without_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 1;
        $fields = [
            'title' => 'Update updateRootPageWithoutRecursiveUpdateFieldsConfigured'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubChildPageWithoutRecursiveUpdateFieldsConfigured()
    {
        $this->importDataSetFromFixture('update_page_without_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 3;
        $fields = [
            'title' => 'Update updateSubChildPageWithoutRecursiveUpdateFieldsConfigured'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateSubSubChildPageWithoutRecursiveUpdateFieldsConfigured()
    {
        $this->importDataSetFromFixture('update_page_without_recursive_update_fields_configured.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateSubSubChildPageWithoutRecursiveUpdateFieldsConfigured'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

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
                'root' => 1
            ],
            'record-2' => [
                'uid' => 2,
                'root' => 111
            ]
        ];
    }

    /**
     * @dataProvider updateRecordOutsideSiteRootWithAdditionalWhereClauseDataProvider
     * @test
     */
    public function updateRecordOutsideSiteRootWithAdditionalWhereClause($uid, $root)
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot_with_additionalWhereClause.xml');

        $this->assertEmptyIndexQueue();

        // create faked tce main call data
        $status = 'update';
        $table = 'tx_fakeextension_domain_model_foo';
        $fields = [
            'title' => 'foo',
            'pid' => 2
        ];
        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(1);
        $firstQueueItem = $this->indexQueue->getItem(1);
        $this->assertSame($uid, $firstQueueItem->getRecordUid());
        $this->assertSame($root, $firstQueueItem->getRootPageUid());

    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRoot()
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot.xml');

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
            'pid' => 2
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRootReferencedInTwoSites()
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot_from_two_sites.xml');

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
            'pid' => 3
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function updateRecordOutsideSiteRootLocatedInOtherSite()
    {
        $this->importExtTablesDefinition('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePathByName('fake_extension_tca.php'));

        $this->importDataSetFromFixture('update_record_outside_siteroot_from_other_siteroot.xml');

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
            'pid' => 3
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields, $this->dataHandler);
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredDefault()
    {
        $this->importDataSetFromFixture('update_page_use_configuration_monitor_tables.xml');
        $this->assertEmptyIndexQueue();

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateRecordMonitoringTablesConfiguredDefault'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredNotForTableBeingUpdated()
    {
        $this->importDataSetFromFixture('update_page_use_configuration_monitor_tables.xml');
        $this->assertEmptyIndexQueue();

        $testConfig = [];
        $testConfig['useConfigurationMonitorTables'] = 'tt_content';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = $testConfig;

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateRecordMonitoringTablesConfiguredNotForTableBeingUpdated'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $testConfig = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = $testConfig;

        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function updateRecordMonitoringTablesConfiguredForTableBeingUpdated()
    {
        $this->importDataSetFromFixture('update_page_use_configuration_monitor_tables.xml');
        $this->assertEmptyIndexQueue();

        $testConfig = [];
        $testConfig['useConfigurationMonitorTables'] = 'pages, tt_content';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = $testConfig;

        $status = 'update';
        $table = 'pages';
        $uid = 5;
        $fields = [
            'title' => 'Update updateRecordMonitoringTablesConfiguredForTableBeingUpdated'
        ];

        $this->recordMonitor->processDatamap_afterDatabaseOperations($status, $table, $uid, $fields,
            $this->dataHandler);

        $testConfig = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = $testConfig;

        $this->assertIndexQueueContainsItemAmount(1);
    }

    /**
     * This testcase checks if we can create a new testpage on the root level without any errors.
     *
     * @test
     */
    public function canCreateSiteOneRootLevel()
    {
        $this->importDataSetFromFixture('can_create_new_page.xml');
        $this->setUpBackendUserFromFixture(1);

        $this->assertIndexQueueContainsItemAmount(0);
        $dataHandler = $this->getDataHandler();
        $dataHandler->start(['pages' => ['NEW' => ['hidden' => 0]]], []);
        $dataHandler->process_datamap();

        // the item is outside a siteroot so we should not have any queue entry
        $this->assertIndexQueueContainsItemAmount(0);
    }

    /**
     * This testcase checks if we can create a new testpage on the root level without any errors.
     *
     * @test
     */
    public function canCreateSubPageBelowSiteRoot()
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
}
