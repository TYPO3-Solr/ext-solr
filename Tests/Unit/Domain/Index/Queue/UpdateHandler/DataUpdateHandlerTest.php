<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler;

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

use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\DBAL\Driver\Statement;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;

/**
 * Testcase for the DataUpdateHandler class.
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class DataUpdateHandlerTest extends AbstractUpdateHandlerTest
{
    private const DUMMY_PAGE_ID = 10;

    /**
     * @var DataUpdateHandler
     */
    protected $dataUpdateHandler;

    /**
     * @var MountPagesUpdater|MockObject
     */
    protected $mountPagesUpdaterMock;

    /**
     * @var RootPageResolver|MockObject
     */
    protected $rootPageResolverMock;

    /**
     * @var PagesRepository|MockObject
     */
    protected $pagesRepositoryMock;

    /**
     * @var DataHandler|MockObject
     */
    protected $dataHandlerMock;

    /**
     * @var SolrLogManager|MockObject
     */
    protected $solrLogManagerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->mountPagesUpdaterMock = $this->createMock(MountPagesUpdater::class);
        $this->rootPageResolverMock = $this->createMock(RootPageResolver::class);
        $this->pagesRepositoryMock = $this->createMock(PagesRepository::class);
        $this->dataHandlerMock = $this->createMock(DataHandler::class);
        $this->loggerMock = $this->createMock(SolrLogManager::class);

        $this->dataUpdateHandler = new DataUpdateHandler(
            $this->recordServiceMock,
            $this->frontendEnvironmentMock,
            $this->tcaServiceMock,
            $this->indexQueueMock,
            $this->mountPagesUpdaterMock,
            $this->rootPageResolverMock,
            $this->pagesRepositoryMock,
            $this->dataHandlerMock,
            $this->loggerMock
        );

        $this->dataHandlerMock
            ->expects($this->any())
            ->method('getPID')
            ->willReturn(self::DUMMY_PAGE_ID);
    }

    /**
     * Init the rootPageResolverMock to simulate a valid
     * root page (self::DUMMY_PAGE_ID)
     */
    protected function initRootPageResolverforValidDummyRootPage(): void
    {
        $this->rootPageResolverMock
            ->expects($this->any())
            ->method('getRootPageId')
            ->willReturn(self::DUMMY_PAGE_ID);

        $this->rootPageResolverMock
            ->expects($this->any())
            ->method('getIsRootPageId')
            ->willReturn(true);

        $this->rootPageResolverMock
            ->expects($this->any())
            ->method('getResponsibleRootPageIds')
            ->willReturn([self::DUMMY_PAGE_ID]);
    }

    /**
     * @test
     */
    public function handleContentElementUpdateTriggersSinglePageProcessing(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page on which dummy ce is placed',
            'sys_language_uid' => 0
        ];

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            );

        $this->indexQueueMock
            ->expects($this->once())
            ->method('deleteItem')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            );

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('pages')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isLocalizedRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'pages',
                $dummyPageRecord,
                $dummyPageRecord['uid']
            )
            ->willReturn($dummyPageRecord['uid']);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(true);

        $this->inject($this->dataUpdateHandler, 'updateSubPagesRecursiveTriggerConfiguration', []);
        $this->dataUpdateHandler->handleContentElementUpdate(123);
    }

    /**
     * @test
     */
    public function handleContentElementUpdateTriggersInvalidPageProcessing(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'invalid page on which dummy ce is placed',
            'sys_language_uid' => 0
        ];

        $garbageHandlerMock = $this->createMock(GarbageHandler::class);
        $garbageHandlerMock
            ->expects($this->once())
            ->method('collectGarbage')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            );
        GeneralUtility::addInstance(GarbageHandler::class, $garbageHandlerMock);

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('containsItem')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            )
            ->willReturn(true);

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('pages')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn([]);

        $this->inject($this->dataUpdateHandler, 'updateSubPagesRecursiveTriggerConfiguration', []);
        $this->dataUpdateHandler->handleContentElementUpdate(123);
    }

    /**
     * @test
     */
    public function handleContentElementDeletionTriggersPageUpdate(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $contextMock = $this->createMock(Context::class);
        $contextMock->expects($this->any())->method('getPropertyFromAspect')->willReturn(1641472388);
        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with(
                'pages',
                self::DUMMY_PAGE_ID,
                1641472388
            )
            ->willReturn(true);

        $this->dataUpdateHandler->handleContentElementDeletion(123);
    }

    /**
     * @test
     */
    public function handlePageUpdateTriggersSinglePageProcessing(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0
        ];

        $this->initBasicPageUpdateExpectations($dummyPageRecord);

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            );

        $this->inject($this->dataUpdateHandler, 'updateSubPagesRecursiveTriggerConfiguration', []);
        $this->dataUpdateHandler->handlePageUpdate($dummyPageRecord['uid']);
    }

    /**
     * Init basic page update expectations
     *
     * @param array $dummyPageRecord
     */
    protected function initBasicPageUpdateExpectations(array $dummyPageRecord): void
    {
        $this->indexQueueMock
            ->expects($this->once())
            ->method('deleteItem')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            );

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('pages')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isLocalizedRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'pages',
                $dummyPageRecord,
                $dummyPageRecord['uid']
            )
            ->willReturn($dummyPageRecord['uid']);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(true);
    }

    /**
     * @test
     */
    public function handlePageUpdateTriggersRecursivePageProcessing(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
            'extendToSubpages' => 1
        ];

        $GLOBALS['TCA']['pages'] = ['columns' => []];

        $this->queryGeneratorMock
            ->expects($this->any())
            ->method('getTreeList')
            ->willReturn($dummyPageRecord['uid'] . ',100,200');

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects($this->once())->method('fetch')->willReturn($dummyPageRecord);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects($this->once())->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects($this->once())->method('execute')->willReturn($statementMock);

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->any())->method('getExpressionBuilder')->willReturn($this->createMock(ExpressionBuilder::class));

        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $connectionPoolMock->expects($this->once())->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        $connectionPoolMock->expects($this->once())->method('getConnectionForTable')->willReturn($connectionMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $this->initBasicPageUpdateExpectations($dummyPageRecord);

        $this->indexQueueMock
            ->expects($this->exactly(3))
            ->method('updateItem')
            ->withConsecutive(
                [
                    'pages',
                    $dummyPageRecord['uid']
                ],
                [
                    'pages',
                    100
                ],
                [
                    'pages',
                    200
                ]
            );

        $this->dataUpdateHandler->handlePageUpdate($dummyPageRecord['uid'], ['hidden' => 0]);
    }

    /**
     * Tests if the processing of a page with no connection to a valid root page
     * triggers just the mount page updater
     *
     * @test
     */
    public function handlePageUpdateTriggersUnconnectedPageProcessing(): void
    {
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0
        ];

        $this->rootPageResolverMock
            ->expects($this->once())
            ->method('getAlternativeSiteRootPagesIds')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $dummyPageRecord['uid']
            )
            ->willReturn([]);

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->frontendEnvironmentMock
            ->expects($this->never())
            ->method('getSolrConfigurationFromPageId');

        $this->dataUpdateHandler->handlePageUpdate($dummyPageRecord['uid']);
    }

    /**
     * @test
     */
    public function handleRecordUpdateTriggersRecordProcessing(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID
        ];

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyRecord);

        $this->indexQueueMock
            ->expects($this->never())
            ->method('deleteItem');

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isLocalizedRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord
            )
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
                $dummyRecord['uid']
            )
            ->willReturn($dummyRecord['uid']);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord
            )
            ->willReturn(true);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid']
            );

        $this->dataUpdateHandler->handleRecordUpdate($dummyRecord['uid'], 'tx_foo_bar');
    }

    /**
     * Tests if the processing of a record that couldn't be found in database
     * triggers the removal from index and queue
     *
     * @test
     */
    public function handleRecordUpdateTriggersInvalidRecordProcessing(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID
        ];

        $garbageHandlerMock = $this->createMock(GarbageHandler::class);
        $garbageHandlerMock
            ->expects($this->once())
            ->method('collectGarbage')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid']
            );
        GeneralUtility::addInstance(GarbageHandler::class, $garbageHandlerMock);

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn([]);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('containsItem')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid']
            )
            ->willReturn(true);

        $this->dataUpdateHandler->handleRecordUpdate($dummyRecord['uid'], 'tx_foo_bar');
    }

    /**
     * @test
     */
    public function handleRecordUpdateTriggersMultipleRootPagesRecordProcessing(): void
    {
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID
        ];

        $this->rootPageResolverMock
            ->expects($this->once())
            ->method('getResponsibleRootPageIds')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid']
            )
            ->willReturn([self::DUMMY_PAGE_ID, 20]);

        $this->typoScriptConfigurationMock
            ->expects($this->exactly(2))
            ->method('getIndexQueueIsMonitoredTable')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->exactly(2))
            ->method('getRecord')
            ->willReturn($dummyRecord);

        $this->indexQueueMock
            ->expects($this->never())
            ->method('deleteItem');

        $this->tcaServiceMock
            ->expects($this->exactly(2))
            ->method('isLocalizedRecord')
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects($this->exactly(2))
            ->method('getTranslationOriginalUidIfTranslated')
            ->willReturn($dummyRecord['uid']);

        $this->tcaServiceMock
            ->expects($this->exactly(2))
            ->method('isEnabledRecord')
            ->willReturn(true);

        $this->indexQueueMock
            ->expects($this->exactly(2))
            ->method('updateItem');

        $this->dataUpdateHandler->handleRecordUpdate($dummyRecord['uid'], 'tx_foo_bar');
    }

    /**
     * @test
     */
    public function handleVersionSwapAppliesPageChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0
        ];

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(true);

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with('pages', $dummyPageRecord['uid']);

        $this->dataUpdateHandler->handleVersionSwap($dummyPageRecord['uid'], 'pages');
    }

    /**
     * @test
     */
    public function handleVersionSwapAppliesContentElementChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyRecordId = 123;
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0
        ];

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(true);

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with('pages', $dummyPageRecord['uid']);

        $this->dataUpdateHandler->handleVersionSwap($dummyRecordId, 'tt_content');
    }

    /**
     * @test
     */
    public function handleVersionSwapAppliesInvalidPageChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'invalid dummy page to be processed',
            'sys_language_uid' => 0
        ];

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn([]);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('containsItem')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            )
            ->willReturn(true);

        $garbageHandlerMock = $this->createMock(GarbageHandler::class);
        $garbageHandlerMock
            ->expects($this->once())
            ->method('collectGarbage')
            ->with(
                'pages',
                $dummyPageRecord['uid']
            );
        GeneralUtility::addInstance(GarbageHandler::class, $garbageHandlerMock);

        $this->dataUpdateHandler->handleVersionSwap($dummyPageRecord['uid'], 'pages');
    }

    /**
     * @test
     */
    public function handleVersionSwapAppliesRecordChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID
        ];

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord
            )
            ->willReturn(true);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
                $dummyRecord['uid']
            )
            ->willReturn($dummyRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with('tx_foo_bar', $dummyRecord['uid']);

        $this->dataUpdateHandler->handleVersionSwap($dummyRecord['uid'], 'tx_foo_bar');
    }

    /**
     * @test
     */
    public function handleVersionSwapAppliesInvalidRecordChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID
        ];

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
        ->expects($this->once())
        ->method('getRecord')
        ->with(
            'tx_foo_bar',
            $dummyRecord['uid'],
            $this->typoScriptConfigurationMock
        )
        ->willReturn([]);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('containsItem')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid']
            )
            ->willReturn(true);

        $garbageHandlerMock = $this->createMock(GarbageHandler::class);
        $garbageHandlerMock
            ->expects($this->once())
            ->method('collectGarbage')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid']
            );
        GeneralUtility::addInstance(GarbageHandler::class, $garbageHandlerMock);

        $this->dataUpdateHandler->handleVersionSwap($dummyRecord['uid'], 'tx_foo_bar');
    }


    /**
     * @test
     */
    public function handleMovedPageAppliesPageChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0
        ];

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord
            )
            ->willReturn(true);

        $this->mountPagesUpdaterMock
            ->expects($this->once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with('pages', $dummyPageRecord['uid']);

        $this->dataUpdateHandler->handleMovedPage($dummyPageRecord['uid']);
    }

    /**
     * @test
     */
    public function handleMovedRecordAppliesRecordChangesToQueue(): void
    {
        $this->initRootPageResolverforValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID
        ];

        $this->typoScriptConfigurationMock
            ->expects($this->once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects($this->once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock
            )
            ->willReturn($dummyRecord);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('isEnabledRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord
            )
            ->willReturn(true);

        $this->tcaServiceMock
            ->expects($this->once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
                $dummyRecord['uid']
            )
            ->willReturn($dummyRecord['uid']);

        $this->indexQueueMock
            ->expects($this->once())
            ->method('updateItem')
            ->with('tx_foo_bar', $dummyRecord['uid']);

        $this->dataUpdateHandler->handleMovedRecord($dummyRecord['uid'], 'tx_foo_bar');
    }
}
