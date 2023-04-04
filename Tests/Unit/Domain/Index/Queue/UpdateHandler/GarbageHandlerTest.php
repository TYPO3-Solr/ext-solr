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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\PageStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\RecordStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use Doctrine\DBAL\Statement;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the GarbageHandler class.
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class GarbageHandlerTest extends AbstractUpdateHandlerTest
{
    /**
     * @var GarbageHandler
     */
    protected $garbageHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->garbageHandler = new GarbageHandler(
            $this->recordServiceMock,
            $this->frontendEnvironmentMock,
            $this->tcaServiceMock,
            $this->indexQueueMock
        );
    }

    /**
     * @test
     */
    public function collectGarbageTriggersGarbageCollectionForPages(): void
    {
        $this->initGarbageCollectionExpectations(PageStrategy::class, 'pages', 123);
        $this->garbageHandler->collectGarbage('pages', 123);
    }

    /**
     * @test
     */
    public function collectGarbageTriggersGarbageCollectionForRecords(): void
    {
        $this->initGarbageCollectionExpectations(RecordStrategy::class, 'tx_foo_bar', 789);
        $this->garbageHandler->collectGarbage('tx_foo_bar', 789);
    }

    /**
     * Inits garbage collection expectations
     *
     * @param string $strategy Class name of strategy to expect
     * @param string $table
     * @param int $uid
     */
    protected function initGarbageCollectionExpectations(string $strategy, string $table, int $uid): void
    {
        $strategyMock = $this->createMock($strategy);
        GeneralUtility::addInstance($strategy, $strategyMock);

        $strategyMock
            ->expects(self::once())
            ->method('removeGarbageOf')
            ->with(
                $table,
                $uid
            );
    }

    /**
     * @test
     */
    public function handlePageMovementTriggersGarbageCollectionAndReindexing(): void
    {
        $this->initGarbageCollectionExpectations(PageStrategy::class, 'pages', 123);
        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with('pages', 123);

        $this->garbageHandler->handlePageMovement(123);
    }

    /**
     * @test
     */
    public function performRecordGarbageCheckTriggersRecordGarbageCollection(): void
    {
        $dummyRecord = [
            'uid' => 789,
            'title' => 'dummy record to collect garbage for',
            'hidden' => 1,
        ];

        $this->initGarbageCollectionExpectations(RecordStrategy::class, 'tx_foo_bar', $dummyRecord['uid']);

        $GLOBALS['TCA']['tx_foo_bar'] = ['columns' => []];
        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getVisibilityAffectingFieldsByTable')
            ->with('tx_foo_bar')
            ->willReturn('hidden,fe_group');

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(self::once())->method('fetch')->willReturn($dummyRecord);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects(self::once())->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects(self::once())->method('execute')->willReturn($statementMock);

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects(self::any())->method('getExpressionBuilder')->willReturn($this->createMock(ExpressionBuilder::class));

        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $connectionPoolMock->expects(self::once())->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('normalizeFrontendGroupField')
            ->with('tx_foo_bar', $dummyRecord)
            ->willReturn($dummyRecord);

        $this->garbageHandler->performRecordGarbageCheck($dummyRecord['uid'], 'tx_foo_bar', [], true);
    }

    /**
     * @test
     */
    public function performRecordGarbageCheckTriggersPageGarbageCollection(): void
    {
        $dummyPageRecord = [
            'uid' => 789,
            'title' => 'dummy record to collect garbage for',
            'hidden' => 1,
            'extendToSubpages' => 1,
        ];

        $this->initGarbageCollectionExpectations(PageStrategy::class, 'pages', 100);
        $this->initGarbageCollectionExpectations(PageStrategy::class, 'pages', 200);
        $this->initGarbageCollectionExpectations(PageStrategy::class, 'pages', $dummyPageRecord['uid']);

        $GLOBALS['TCA']['pages'] = ['columns' => []];
        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getVisibilityAffectingFieldsByTable')
            ->with('pages')
            ->willReturn('hidden,fe_group');

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(self::exactly(2))->method('fetch')->willReturn($dummyPageRecord);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects(self::exactly(2))->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects(self::exactly(2))->method('execute')->willReturn($statementMock);

        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects(self::any())->method('getExpressionBuilder')->willReturn($this->createMock(ExpressionBuilder::class));

        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $connectionPoolMock->expects(self::exactly(2))->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        $connectionPoolMock->expects(self::once())->method('getConnectionForTable')->willReturn($connectionMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('normalizeFrontendGroupField')
            ->with('pages', $dummyPageRecord)
            ->willReturn($dummyPageRecord);

        $this->queryGeneratorMock
            ->expects(self::any())
            ->method('getTreeList')
            ->willReturn($dummyPageRecord['uid'] . ',100,200');

        $this->garbageHandler->performRecordGarbageCheck($dummyPageRecord['uid'], 'pages', ['hidden' => 1], true);
    }

    /**
     * @test
     */
    public function getRecordWithFieldRelevantForGarbageCollectionDeterminesFields(): void
    {
        $GLOBALS['TCA']['tx_foo_bar'] = ['columns' => []];

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getVisibilityAffectingFieldsByTable')
            ->with('tx_foo_bar')
            ->willReturn('hidden,fe_group');

        $dummyRecord = ['uid' => 123];
        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(self::once())->method('fetch')->willReturn($dummyRecord);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects(self::once())->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects(self::once())->method('execute')->willReturn($statementMock);
        $queryBuilderMock
            ->expects(self::once())
            ->method('select')
            ->with('hidden', 'fe_group');

        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $connectionPoolMock->expects(self::once())->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $record = $this->garbageHandler->getRecordWithFieldRelevantForGarbageCollection('tx_foo_bar', 123);
        self::assertEquals($dummyRecord, $record);
    }
}
