<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022 Markus Friedrich <markus.friedrich@dkd.de>
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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;


use Psr\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\PageMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;

/**
 * Testcase for the GarbageCollector class.
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class GarbageCollectorTest extends UnitTest
{
    /**
     * @var GarbageCollector
     */
    protected $garbageCollector;

    /**
     * @var TCAService|MockObject
     */
    protected $tcaServiceMock;

    /**
     * @var EventDispatcherInterface|MockObject
     */
    protected $eventDispatcherMock;

    /**
     * @var GarbageHandler|MockObject
     */
    protected $garbageHandlerMock;

    public function setUp(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->tcaServiceMock = $this->createMock(TCAService::class);
        $this->garbageCollector = new GarbageCollector($this->tcaServiceMock, $this->eventDispatcherMock);

        $this->garbageHandlerMock = $this->createMock(GarbageHandler::class);
        GeneralUtility::addInstance(GarbageHandler::class, $this->garbageHandlerMock);

        $GLOBALS['BE_USER'] = $this->createMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->workspace = 0;
    }

    public function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        unset($GLOBALS['TCA']);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function processCmdmap_preProcessUHandlesRecordDeletion(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));
        $this->garbageCollector->processCmdmap_preProcess('delete', 'pages', 123, '', $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof RecordDeletedEvent);
        $this->assertEquals('pages', $dispatchedEvent->getTable());
        $this->assertEquals(123, $dispatchedEvent->getUid());
    }

    /**
     * @test
     */
    public function processCmdmap_preProcessIgnoresDraftWorkspace(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $GLOBALS['BE_USER']->workspace = 1;
        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');
        $this->garbageCollector->processCmdmap_preProcess('delete', 'pages', 123, '', $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessHandlesPageMovement(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));

        $this->garbageCollector->processCmdmap_postProcess('move', 'pages', 1011, '', $dataHandlerMock);

        $this->assertTrue($dispatchedEvent instanceof PageMovedEvent);
        $this->assertEquals('pages', $dispatchedEvent->getTable());
        $this->assertEquals(1011, $dispatchedEvent->getUid());
    }

    /**
     * @test
     */
    public function processCmdmap_postProcessIgnoresPageMovementInDraftWorkspace(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);

        $GLOBALS['BE_USER']->workspace = 1;
        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');

        $this->garbageCollector->processCmdmap_postProcess('move', 'pages', 1011, '', $dataHandlerMock);
    }

    /**
     * @test
     */
    public function processDatamap_preProcessFieldArrayStoresRecordData(): void
    {
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $dummyRecord = [
            'uid' => 123,
            'pid' => 1,
            'hidden' => 0,
            'fe_group' => '1,2'
        ];

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnableColumn')
            ->with(
                'tx_foo_bar',
                'fe_group'
            )
            ->willReturn(true);

        $this->garbageHandlerMock
            ->expects($this->once())
            ->method('getRecordWithFieldRelevantForGarbageCollection')
            ->with(
                'tx_foo_bar',
                123
            )
            ->willReturn($dummyRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('normalizeFrontendGroupField')
            ->with(
                'tx_foo_bar',
                $dummyRecord
            )
            ->willReturn($dummyRecord);

        $this->garbageCollector->processDatamap_preProcessFieldArray([], 'tx_foo_bar', 123, $dataHandlerMock);

        $objectReflection = new \ReflectionObject($this->garbageCollector);
        $property = $objectReflection->getProperty('trackedRecords');
        $property->setAccessible(true);
        $trackedRecords = $property->getValue($this->garbageCollector);

        $this->assertEquals($dummyRecord, $trackedRecords['tx_foo_bar'][123]);
    }

    /**
     * @test
     */
    public function processDatamap_afterDatabaseOperationsTriggersRecordGarbageCheck(): void
    {
        $GLOBALS['TCA']['tx_foo_bar']['ctrl']['enablecolumns']['fe_group'] = 'fe_group';
        $dataHandlerMock = $this->getDumbMock(DataHandler::class);
        $dummyRecord = [
            'uid' => 123,
            'pid' => 1,
            'hidden' => 0,
            'fe_group' => '1,2'
        ];
        $trackedRecords = [
            'tx_foo_bar' => [
                123 => $dummyRecord
            ]
        ];
        $this->inject($this->garbageCollector, 'trackedRecords', $trackedRecords);
        $dummyRecord['fe_group'] = '1';

        $this->garbageHandlerMock
            ->expects($this->once())
            ->method('getRecordWithFieldRelevantForGarbageCollection')
            ->with(
                'tx_foo_bar',
                123
            )
            ->willReturn($dummyRecord);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->will($this->returnCallback(function() use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            }));

        $this->garbageCollector->processDatamap_afterDatabaseOperations(
            'update',
            'tx_foo_bar',
            123,
            ['uid' => 123, 'pid' => 1, 'title' => 'test'],
            $dataHandlerMock
        );

        $this->assertTrue($dispatchedEvent instanceof RecordGarbageCheckEvent);
        $this->assertEquals('tx_foo_bar', $dispatchedEvent->getTable());
        $this->assertEquals(123, $dispatchedEvent->getUid());
        $this->assertTrue($dispatchedEvent->frontendGroupsRemoved());
    }

    /**
     * @test
     */
    public function collectGarbageTriggersGarbageCollection(): void
    {
        $this->garbageHandlerMock
            ->expects($this->once())
            ->method('collectGarbage')
            ->with(
                'pages',
                123
            );

        $this->garbageCollector->collectGarbage('pages', 123);
    }
}
