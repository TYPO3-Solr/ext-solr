<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solrfal\Queue\Queue;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\FormProtection\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This testcase is used to check if the GarbageCollector can delete garbage from the
 * solr server as expected
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class GarbageCollectorTest extends IntegrationTest
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

    /**
     * @var GarbageCollector
     */
    protected $garbageCollector;

    public function setUp()
    {
        parent::setUp();
        $this->recordMonitor = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor');
        $this->dataHandler = GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
        $this->indexQueue = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\IndexQueue\Queue');
        $this->garbageCollector = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\GarbageCollector');
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
    protected function assertIndexQueryContainsItemAmount($amount)
    {
        $this->assertEquals($amount, $this->indexQueue->getAllItemsCount(),
            'Index queue is empty and was expected to contain '.(int) $amount.' items.');
    }

    /**
     * @test
     */
    public function canQueueAPageAndRemoveItWithTheGarbageCollector()
    {
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('can_queue_a_page_and_remove_it_with_the_garbage_collector.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        $dataHandler = $this->dataHandler;
        $this->recordMonitor->processDatamap_afterDatabaseOperations('update', 'pages', 1, array(), $dataHandler);

        // we expect that one item is now in the solr server
        $this->assertIndexQueryContainsItemAmount(1);

        $this->garbageCollector->collectGarbage('pages', 1);

        // finally we expect that the index is empty again
        $this->assertEmptyIndexQueue();
    }

    /**
     * @test
     */
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSet()
    {
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('can_collect_garbage_from_subPages_when_page_is_set_to_hidden_and_extendToSubpages_is_set.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        $this->indexQueue->updateItem('pages', 1);
        $this->indexQueue->updateItem('pages', 10);
        $this->indexQueue->updateItem('pages', 100);

        // we expected that three pages are now in the index
        $this->assertIndexQueryContainsItemAmount(3);

        // simulate the database change and build a faked changeset
        $database->exec_UPDATEquery('pages', 'uid=1', array('hidden' => 1));
        $changeSet = array('hidden' => 1);

        $dataHandler = $this->dataHandler;
        $this->garbageCollector->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // finally we expect that the index is empty again because the root page with "extendToSubPages" has been set to
        // hidden = 1
        $this->assertEmptyIndexQueue();
    }


    /**
     * @test
     */
    public function canCollectGarbageFromSubPagesWhenPageIsSetToHiddenAndExtendToSubPagesIsSetForMultipleSubpages()
    {
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;
        $this->importDataSetFromFixture('can_collect_garbage_from_subPages_when_page_is_set_to_hidden_and_extendToSubpages_is_set_multiple_subpages.xml');

        // we expect that the index queue is empty before we start
        $this->assertEmptyIndexQueue();

        $this->indexQueue->updateItem('pages', 1);
        $this->indexQueue->updateItem('pages', 10);
        $this->indexQueue->updateItem('pages', 11);
        $this->indexQueue->updateItem('pages', 12);

        // we expected that three pages are now in the index
        $this->assertIndexQueryContainsItemAmount(4);

        // simulate the database change and build a faked changeset
        $database->exec_UPDATEquery('pages', 'uid=1', array('hidden' => 1));
        $changeSet = array('hidden' => 1);

        $dataHandler = $this->dataHandler;
        $this->garbageCollector->processDatamap_afterDatabaseOperations('update', 'pages', 1, $changeSet, $dataHandler);

        // finally we expect that the index is empty again because the root page with "extendToSubPages" has been set to
        // hidden = 1
        $this->assertEmptyIndexQueue();
    }
}
