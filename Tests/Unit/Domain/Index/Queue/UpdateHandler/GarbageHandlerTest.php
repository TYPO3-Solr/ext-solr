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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\PageStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover\RecordStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use Doctrine\DBAL\Result;
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
            'pid' => 1,
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

        $this->pagesRepositoryMock
            ->expects(self::any())
            ->method('getPage')
            ->willReturn(['uid' => 1]);
        $this->inject($this->garbageHandler, 'pagesRepository', $this->pagesRepositoryMock);

        $resultMock = $this->createMock(Result::class);
        $resultMock->expects(self::once())->method('fetchAssociative')->willReturn($dummyRecord);
        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects(self::once())->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects(self::once())->method('select')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('from')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('where')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('executeQuery')->willReturn($resultMock);
        $this->inject($this->garbageHandler, 'queryBuilders', ['tx_foo_bar' => $queryBuilderMock]);

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
            'pid' => 1,
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

        $this->pagesRepositoryMock
            ->expects(self::any())
            ->method('getPage')
            ->willReturn($dummyPageRecord);
        $this->inject($this->garbageHandler, 'pagesRepository', $this->pagesRepositoryMock);

        $resultMock = $this->createMock(Result::class);
        $resultMock->expects(self::once())->method('fetchAssociative')->willReturn($dummyPageRecord);
        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects(self::once())->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects(self::once())->method('select')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('from')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('where')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('executeQuery')->willReturn($resultMock);
        $this->inject($this->garbageHandler, 'queryBuilders', ['pages' => $queryBuilderMock]);

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

        $resultMock = $this->createMock(Result::class);
        $resultMock->expects(self::once())->method('fetchAssociative')->willReturn($dummyRecord);
        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->expects(self::once())->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $queryBuilderMock->expects(self::once())->method('select')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('from')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('where')->willReturn($queryBuilderMock);
        $queryBuilderMock->expects(self::once())->method('executeQuery')->willReturn($resultMock);
        $this->inject($this->garbageHandler, 'queryBuilders', ['tx_foo_bar' => $queryBuilderMock]);

        $record = $this->garbageHandler->getRecordWithFieldRelevantForGarbageCollection('tx_foo_bar', 123);
        self::assertEquals($dummyRecord, $record);
    }
}
