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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\PageMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\GarbageCollector;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionObject;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the GarbageCollector class.
 */
class GarbageCollectorTest extends SetUpUnitTestCase
{
    protected GarbageCollector $garbageCollector;
    protected TCAService|MockObject $tcaServiceMock;
    protected EventDispatcherInterface|MockObject $eventDispatcherMock;
    protected GarbageHandler|MockObject $garbageHandlerMock;

    protected function setUp(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->tcaServiceMock = $this->createMock(TCAService::class);
        $this->garbageCollector = new GarbageCollector($this->tcaServiceMock, $this->eventDispatcherMock);

        $this->garbageHandlerMock = $this->createMock(GarbageHandler::class);
        GeneralUtility::addInstance(GarbageHandler::class, $this->garbageHandlerMock);

        $GLOBALS['BE_USER'] = $this->createMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->workspace = 0;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        unset($GLOBALS['TCA']);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function processCmdmap_preProcessUHandlesRecordDeletion(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->garbageCollector->processCmdmap_preProcess('delete', 'pages', 123, '', $dataHandlerMock);

        self::assertTrue($dispatchedEvent instanceof RecordDeletedEvent);
        self::assertEquals('pages', $dispatchedEvent->getTable());
        self::assertEquals(123, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processCmdmap_preProcessIgnoresDraftWorkspace(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);

        $GLOBALS['BE_USER']->workspace = 1;
        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');
        $this->garbageCollector->processCmdmap_preProcess('delete', 'pages', 123, '', $dataHandlerMock);
    }

    #[Test]
    public function processCmdmap_postProcessHandlesPageMovement(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });

        $this->garbageCollector->processCmdmap_postProcess('move', 'pages', 1011, '', $dataHandlerMock);

        self::assertTrue($dispatchedEvent instanceof PageMovedEvent);
        self::assertEquals('pages', $dispatchedEvent->getTable());
        self::assertEquals(1011, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processCmdmap_postProcessIgnoresPageMovementInDraftWorkspace(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);

        $GLOBALS['BE_USER']->workspace = 1;
        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $this->garbageCollector->processCmdmap_postProcess('move', 'pages', 1011, '', $dataHandlerMock);
    }

    #[Test]
    public function processDatamap_preProcessFieldArrayStoresRecordData(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);
        $dummyRecord = [
            'uid' => 123,
            'pid' => 1,
            'hidden' => 0,
            'fe_group' => '1,2',
        ];

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnableColumn')
            ->with(
                'tx_foo_bar',
                'fe_group',
            )
            ->willReturn(true);

        $this->garbageHandlerMock
            ->expects(self::once())
            ->method('getRecordWithFieldRelevantForGarbageCollection')
            ->with(
                'tx_foo_bar',
                123,
            )
            ->willReturn($dummyRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('normalizeFrontendGroupField')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
            )
            ->willReturn($dummyRecord);

        $this->garbageCollector->processDatamap_preProcessFieldArray([], 'tx_foo_bar', 123, $dataHandlerMock);

        $objectReflection = new ReflectionObject($this->garbageCollector);
        $property = $objectReflection->getProperty('trackedRecords');
        $trackedRecords = $property->getValue($this->garbageCollector);

        self::assertEquals($dummyRecord, $trackedRecords['tx_foo_bar'][123]);
    }

    #[Test]
    public function processDatamap_afterDatabaseOperationsTriggersRecordGarbageCheck(): void
    {
        $GLOBALS['TCA']['tx_foo_bar']['ctrl']['enablecolumns']['fe_group'] = 'fe_group';
        $dataHandlerMock = $this->createMock(DataHandler::class);
        $dummyRecord = [
            'uid' => 123,
            'pid' => 1,
            'hidden' => 0,
            'fe_group' => '1,2',
        ];
        $trackedRecords = [
            'tx_foo_bar' => [
                123 => $dummyRecord,
            ],
        ];
        $this->inject($this->garbageCollector, 'trackedRecords', $trackedRecords);
        $dummyRecord['fe_group'] = '1';

        $this->garbageHandlerMock
            ->expects(self::once())
            ->method('getRecordWithFieldRelevantForGarbageCollection')
            ->with(
                'tx_foo_bar',
                123,
            )
            ->willReturn($dummyRecord);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });

        $this->garbageCollector->processDatamap_afterDatabaseOperations(
            'update',
            'tx_foo_bar',
            123,
            ['uid' => 123, 'pid' => 1, 'title' => 'test'],
            $dataHandlerMock,
        );

        self::assertTrue($dispatchedEvent instanceof RecordGarbageCheckEvent);
        self::assertEquals('tx_foo_bar', $dispatchedEvent->getTable());
        self::assertEquals(123, $dispatchedEvent->getUid());
        self::assertTrue($dispatchedEvent->frontendGroupsRemoved());
    }

    #[Test]
    public function collectGarbageTriggersGarbageCollection(): void
    {
        $this->garbageHandlerMock
            ->expects(self::once())
            ->method('collectGarbage')
            ->with(
                'pages',
                123,
            );

        $this->garbageCollector->collectGarbage('pages', 123);
    }
}
