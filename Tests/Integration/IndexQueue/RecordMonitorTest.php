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

use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the record monitor
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
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
        $this->recordMonitor = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor');
        $this->dataHandler = GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Queue');
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
            'Index queue is empty and was expected to contain '.(int) $amount.' items.');
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
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('update_mount_point_is_updating_the_mount_point_correctly.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // @todo: detecting the sql error is a little bit ugly but it seems there is no other possibility right now
        ob_start();
        $this->recordMonitor->processCmdmap_postProcess(
            'version',
            'pages',
            1,
            array('action' => 'swap'),
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
        $this->importDumpFromFixture('fake_extension_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_foo'] = include($this->getFixturePath('fake_extension_tca.php'));

        // create faked tce main call data
        $status = 'new';
        $table = 'tx_fakeextension_domain_model_foo';
        $uid = 'NEW566a9eac309d8193936351';
        $fields = array(
            'title' => 'testnews',
            'pid' => 1,
            'starttime' => 1000000,
            'endtime' => 1100000,
            'tsstamp' => 1000000
        );
        $this->dataHandler->substNEWwithIDs = array('NEW566a9eac309d8193936351' => 8);

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
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('reindex_subpages_when_extendToSubpages_set_and_hidden_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $database->exec_UPDATEquery('pages', 'uid=1', array('hidden' => 0));
        $changeSet = array('hidden' => 0);

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
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_set_and_extendToSubpage_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $database->exec_UPDATEquery('pages', 'uid=1', array('extendToSubpages' => 0));
        $changeSet = array('extendToSubpages' => 0);

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // we expect that all subpages of 1 have been requeued, but 1 not because it is still hidden
        // pages with uid 10 and 100 should be in index, but 11 not
        $this->assertIndexQueueContainsItemAmount(2);
    }

    /**
     * @test
     */
    public function queueIsNotFilledWhenItemIsSetToHidden()
    {
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('reindex_subpages_when_hidden_set_and_extendToSubpage_removed.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        // simulate the database change and build a faked changeset
        $database->exec_UPDATEquery('pages', 'uid=1', array('hidden' => 1));
        $changeSet = array('hidden' => 1);

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // we assert that the index queue is still empty because the page was only set to hidden
        $this->assertEmptyIndexQueue();
    }
}
