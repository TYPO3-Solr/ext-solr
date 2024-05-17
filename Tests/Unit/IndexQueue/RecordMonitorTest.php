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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\VersionSwappedEvent;
use ApacheSolrForTypo3\Solr\IndexQueue\RecordMonitor;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Testcase for the RecordMonitor class.
 */
class RecordMonitorTest extends SetUpUnitTestCase
{
    protected RecordMonitor $recordMonitor;

    /**
     * @var EventDispatcherInterface|MockObject
     */
    protected $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->recordMonitor = new RecordMonitor($this->eventDispatcherMock);

        $GLOBALS['BE_USER'] = $this->createMock(BackendUserAuthentication::class);
        $GLOBALS['BE_USER']->workspace = 0;
        GeneralUtility::addInstance(
            ExtensionConfiguration::class,
            $this->createMock(ExtensionConfiguration::class)
        );

        $rootlineUtilityMock = $this->createMock(RootlineUtility::class);
        $rootlineUtilityMock->method('get')->willReturn([]);
        GeneralUtility::addInstance(
            RootlineUtility::class,
            $rootlineUtilityMock
        );
        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['BE_USER'],
            $this->recordMonitor
        );
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    #[Test]
    public function processCmdmap_preProcessUHandlesDeletedContentElements(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->recordMonitor->processCmdmap_preProcess('delete', 'tt_content', 123);

        self::assertTrue($dispatchedEvent instanceof ContentElementDeletedEvent);
        self::assertEquals('tt_content', $dispatchedEvent->getTable());
        self::assertEquals(123, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processCmdmap_preProcessIgnoresDraftWorkspace(): void
    {
        $GLOBALS['BE_USER']->workspace = 1;
        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');
        $this->recordMonitor->processCmdmap_preProcess('delete', 'tt_content', 123);
    }

    #[Test]
    public function processCmdmap_postProcessUpdatesQueueItemForVersionSwapOfPageRecord(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->recordMonitor->processCmdmap_postProcess('version', 'pages', 4711, ['action' => 'swap']);

        self::assertTrue($dispatchedEvent instanceof VersionSwappedEvent);
        self::assertEquals('pages', $dispatchedEvent->getTable());
        self::assertEquals(4711, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processCmdmap_postProcessUpdatesQueueItemForVersionSwapOfRecord(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->recordMonitor->processCmdmap_postProcess('version', 'tx_foo_bar', 888, ['action' => 'swap']);

        self::assertTrue($dispatchedEvent instanceof VersionSwappedEvent);
        self::assertEquals('tx_foo_bar', $dispatchedEvent->getTable());
        self::assertEquals(888, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfPageRecord(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->recordMonitor->processCmdmap_postProcess('move', 'pages', 4711, []);

        self::assertTrue($dispatchedEvent instanceof RecordMovedEvent);
        self::assertEquals('pages', $dispatchedEvent->getTable());
        self::assertEquals(4711, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfPageRecordInDraftWorkspace(): void
    {
        $GLOBALS['BE_USER']->workspace = 1;

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');
        $this->recordMonitor->processCmdmap_postProcess('move', 'pages', 4711, []);
    }

    #[Test]
    public function processCmdmap_postProcessUpdatesQueueItemForMoveOfRecord(): void
    {
        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->recordMonitor->processCmdmap_postProcess('move', 'tx_foo_bar', 888, []);

        self::assertTrue($dispatchedEvent instanceof RecordMovedEvent);
        self::assertEquals('tx_foo_bar', $dispatchedEvent->getTable());
        self::assertEquals(888, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processDatamap_afterDatabaseOperationsUsesAlreadyResolvedNextAutoIncrementValueForNewStatus(): void
    {
        $dataHandlerMock = $this->createMock(DataHandler::class);

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });
        $this->recordMonitor->processDatamap_afterDatabaseOperations('new', 'tt_content', 4711, ['pid' => 1], $dataHandlerMock);

        self::assertTrue($dispatchedEvent instanceof RecordUpdatedEvent);
        self::assertEquals('tt_content', $dispatchedEvent->getTable());
        self::assertEquals(4711, $dispatchedEvent->getUid());
    }

    #[Test]
    public function processDatamap_afterDatabaseOperationsUsesNotYetResolvedNextAutoIncrementValueForNewStatus(): void
    {
        $newId = 'NEW1';
        $dataHandlerMock = $this->createMock(DataHandler::class);
        $dataHandlerMock->substNEWwithIDs[$newId] = 123;

        $dispatchedEvent = null;
        $this->eventDispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchedEvent) {
                $dispatchedEvent = func_get_arg(0);
            });

        $this->recordMonitor->processDatamap_afterDatabaseOperations('new', 'tt_content', $newId, ['pid' => 1], $dataHandlerMock);

        self::assertTrue($dispatchedEvent instanceof RecordUpdatedEvent);
        self::assertEquals('tt_content', $dispatchedEvent->getTable());
        self::assertEquals(123, $dispatchedEvent->getUid());
    }
}
