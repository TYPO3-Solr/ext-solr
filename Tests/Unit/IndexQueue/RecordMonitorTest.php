<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund
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

use Psr\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\VersionSwappedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;

/**
 * Testcase for the RecordMonitor class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RecordMonitorTest extends UnitTest
{
    /**
     * @var RecordMonitor
     */
    protected $recordMonitor;

    /**
     * @var EventDispatcherInterface|MockObject
     */
    protected $eventDispatcherMock;

    public function setUp(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->recordMonitor = new RecordMonitor($this->eventDispatcherMock);

        $GLOBALS['BE_USER'] = $this->createMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->workspace = 0;
    }

    public function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function processCmdmap_preProcessUHandlesDeletedContentElements(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processCmdmap_preProcess('delete', 'tt_content', 123);

        $this->assertTrue($dispatchedEvent instanceof ContentElementDeletedEvent);
        $this->assertEquals('tt_content', $dispatchedEvent->getTable());
        $this->assertEquals(123, $dispatchedEvent->getUid());
    }

    /**
     * @test
     */
    public function processCmdmap_preProcessIgnoresDraftWorkspace(): void
    {
        $GLOBALS['BE_USER']->workspace = 1;
        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');
        $this->recordMonitor->processCmdmap_preProcess('delete', 'tt_content', 123);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForVersionSwapOfPageRecord(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processCmdmap_postProcess('version', 'pages', 4711, ['action' => 'swap'], $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof VersionSwappedEvent);
        $this->assertEquals('pages', $dispatchedEvent->getTable());
        $this->assertEquals(4711, $dispatchedEvent->getUid());
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForVersionSwapOfRecord(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processCmdmap_postProcess('version', 'tx_foo_bar', 888, ['action' => 'swap'], $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof VersionSwappedEvent);
        $this->assertEquals('tx_foo_bar', $dispatchedEvent->getTable());
        $this->assertEquals(888, $dispatchedEvent->getUid());
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfPageRecord(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processCmdmap_postProcess('move', 'pages', 4711, [], $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof RecordMovedEvent);
        $this->assertEquals('pages', $dispatchedEvent->getTable());
        $this->assertEquals(4711, $dispatchedEvent->getUid());
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfPageRecordInDraftWorkspace(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $GLOBALS['BE_USER']->workspace = 1;

        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');
        $this->recordMonitor->processCmdmap_postProcess('move', 'pages', 4711, [], $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfRecord(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processCmdmap_postProcess('move', 'tx_foo_bar', 888, [], $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof RecordMovedEvent);
        $this->assertEquals('tx_foo_bar', $dispatchedEvent->getTable());
        $this->assertEquals(888, $dispatchedEvent->getUid());
    }

    /**
     * @test
     * For more infos, please refer https://github.com/TYPO3-Solr/ext-solr/pull/2836
     */
    public function processDatamap_afterDatabaseOperationsUsesAlreadyResolvedNextAutoIncrementValueForNewStatus(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processDatamap_afterDatabaseOperations('new', 'tt_content', 4711, ['pid' => 1], $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof RecordUpdatedEvent);
        $this->assertEquals('tt_content', $dispatchedEvent->getTable());
        $this->assertEquals(4711, $dispatchedEvent->getUid());
    }

    /**
     * @test
     * For more infos, please refer https://github.com/TYPO3-Solr/ext-solr/pull/2836
     */
    public function processDatamap_afterDatabaseOperationsUsesNotYetResolvedNextAutoIncrementValueForNewStatus(): void
    {
        $newId = 'NEW1';
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $dataHandlerMock->substNEWwithIDs[$newId] = 123;

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->recordMonitor->processDatamap_afterDatabaseOperations('new', 'tt_content', $newId, ['pid' => 1], $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof RecordUpdatedEvent);
        $this->assertEquals('tt_content', $dispatchedEvent->getTable());
        $this->assertEquals(123, $dispatchedEvent->getUid());
    }
}
