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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\IndexQueueIndexingPropertyRepository;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\QueueItemRepository;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Exception\IndexingException;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionClass;
use TYPO3\CMS\Core\Tests\Unit\Fixtures\EventDispatcher\MockEventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class IndexerTest
 */
class IndexerTest extends SetUpUnitTestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']);
        parent::tearDown();
    }

    /**
     * @param int $httpStatus
     * @param bool $itemIndexed
     *
     * @test
     * @dataProvider canTriggerIndexingAndIndicateIndexStatusDataProvider
     */
    public function canTriggerIndexingAndIndicateIndexStatus(int $httpStatus, bool $itemIndexed): void
    {
        $writeServiceMock = $this->createMock(SolrWriteService::class);
        $responseMock = $this->createMock(ResponseAdapter::class);

        $indexer = $this->getAccessibleMock(
            Indexer::class,
            ['itemToDocument', 'processDocuments'],
            [],
            '',
            false
        );
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::any())->method('dispatch')->willReturnArgument(0);
        $indexer->_set('eventDispatcher', $eventDispatcher);

        $solrConnectionMock = $this->createMock(SolrConnection::class);
        $solrConnectionMock
            ->expects(self::atLeastOnce())
            ->method('getWriteService')
            ->willReturn($writeServiceMock);
        $indexer->_set('currentlyUsedSolrConnection', $solrConnectionMock);

        $siteMock = $this->createMock(Site::class);
        $itemMock = $this->createMock(Item::class);
        $itemMock->expects(self::any())->method('getSite')->willReturn($siteMock);
        $itemDocumentMock = $this->createMock(Document::class);
        $indexer
            ->expects(self::once())
            ->method('itemToDocument')
            ->with($itemMock, 0)
            ->willReturn($itemDocumentMock);

        $indexer
            ->expects(self::once())
            ->method('processDocuments')
            ->with($itemMock, [$itemDocumentMock])
            ->willReturnArgument(1);

        $writeServiceMock
            ->expects(self::atLeastOnce())
            ->method('addDocuments')
            ->with([$itemDocumentMock])
            ->willReturn($responseMock);

        $responseMock
            ->expects(self::atLeastOnce())
            ->method('getHttpStatus')
            ->willReturn($httpStatus);

        if ($httpStatus !== 200) {
            self::expectException(IndexingException::class);
        }
        $result = $indexer->_call('indexItem', $itemMock, 0);
        if ($httpStatus === 200) {
            self::assertEquals($itemIndexed, $result);
        }
    }

    /**
     * Data provider for "canTriggerIndexingAndIndicateIndexStatus"
     */
    public function canTriggerIndexingAndIndicateIndexStatusDataProvider(): \Generator
    {
        yield 'Item could be indexed' => [
            200,
            true,
        ];
        yield 'Item could not be indexed' => [
            500,
            false,
        ];
    }

    /**
     * @test
     * @dataProvider canGetAdditionalDocumentsDataProvider
     */
    public function canGetAdditionalDocuments(\Closure|null $listener, ?string $expectedException, int $expectedResultCount): void
    {
        $indexer = $this->getAccessibleMock(
            Indexer::class,
            null,
            [],
            '',
            false
        );

        $eventDispatcher = new MockEventDispatcher();
        if ($listener) {
            $eventDispatcher->addListener($listener);
        }
        $indexer->_set('eventDispatcher', $eventDispatcher);

        if ($expectedException !== null) {
            self::expectException($expectedException);
        }

        $itemMock = new class ([], [], $this->createMock(IndexQueueIndexingPropertyRepository::class), $this->createMock(QueueItemRepository::class)) extends Item {
            protected $site;
            public function setSite(Site $site): void
            {
                $this->site = $site;
            }
            public function getSite(): ?Site
            {
                return $this->site;
            }
        };
        $itemMock->setSite($this->createMock(Site::class));

        // new $itemMock()
        $documents = $indexer->_call(
            'getAdditionalDocuments',
            new Document(),
            $itemMock,
            0,
        );
        self::assertCount($expectedResultCount, $documents);
        foreach ($documents as $document) {
            self::assertTrue($document instanceof Document);
        }
    }

    /**
     * Data provider for "canGetAdditionalDocuments"
     */
    public function canGetAdditionalDocumentsDataProvider(): \Generator
    {
        yield 'no listener registered' => [
            null,
            null,
            1,
        ];

        yield 'valid listener, no additional documents' => [
            function (BeforeDocumentIsProcessedForIndexingEvent $event) {
                // Does nothing
            },
            null,
            1,
        ];
        yield 'valid listener, adds an additional document' => [
            function (BeforeDocumentIsProcessedForIndexingEvent $event) {
                $event->addDocuments([new Document()]);
            },
            null,
            2,
        ];
    }

    /**
     * @test
     * @skip
     */
    public function indexerAlwaysInitializesTSFE(): void
    {
        self::markTestIncomplete('API has been changed, the test case must be moved, since it is still relevant.');
        $item =  $this->createMock(Item::class);
        $item->expects(self::any())->method('getType')->willReturn('pages');
        $item->expects(self::any())->method('getRecordUid')->willReturn(12);
        $item->expects(self::any())->method('getRootPageUid')->willReturn(1);
        $item->expects(self::any())->method('getIndexingConfigurationName')->willReturn('fakeIndexingConfigurationName');

        $frontendEnvironment = $this->createMock(FrontendEnvironment::class);
        $frontendEnvironment->expects(self::atLeastOnce())->method('getSolrConfigurationFromPageId')->with(12, 0);

        $indexer = $this->getMockBuilder(Indexer::class)
            ->setConstructorArgs([
                [],
                $this->createMock(PagesRepository::class),
                $this->createMock(Builder::class),
                $this->createMock(ConnectionManager::class),
                $frontendEnvironment,
                $this->createMock(SolrLogManager::class),
            ])
            ->onlyMethods([
                'getFullItemRecord',
                'isRootPageIdPartOfRootLine',
            ])
            ->getMock();
        $indexer
            ->expects(self::any())
            ->method('getFullItemRecord')
            ->willReturn([]);
        $indexer
            ->expects(self::any())
            ->method('isRootPageIdPartOfRootLine')
            ->willReturn(true);

        $indexerReflection = new ReflectionClass($indexer);
        $itemToDocumentReflectionMethod = $indexerReflection->getMethod('itemToDocument');
        $itemToDocumentReflectionMethod->setAccessible(true);
        $itemToDocumentReflectionMethod->invokeArgs($indexer, [$item]);
    }
}
