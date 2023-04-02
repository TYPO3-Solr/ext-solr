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
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\AdditionalIndexQueueItemIndexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Exception\IndexingException;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerDocumentsModifier;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockBuilder;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionClass;
use RuntimeException;
use Traversable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * Class IndexerTest
 */
class IndexerTest extends SetUpUnitTestCase
{
    use ProphecyTrait;

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
            ['itemToDocument', 'processDocuments', 'getAdditionalDocuments'],
            [],
            '',
            false
        );

        $solrConnectionMock = $this->createMock(SolrConnection::class);
        $solrConnectionMock
            ->expects(self::atLeastOnce())
            ->method('getWriteService')
            ->willReturn($writeServiceMock);
        $indexer->_set('currentlyUsedSolrConnection', $solrConnectionMock);

        $itemMock = $this->createMock(Item::class);
        $itemDocumentMock = $this->createMock(Document::class);
        $indexer
            ->expects(self::once())
            ->method('itemToDocument')
            ->with($itemMock, 0)
            ->willReturn($itemDocumentMock);

        $indexer
            ->expects(self::once())
            ->method('getAdditionalDocuments')
            ->with($itemMock, 0, $itemDocumentMock)
            ->willReturn([]);

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
     *
     * @return Traversable
     */
    public function canTriggerIndexingAndIndicateIndexStatusDataProvider(): Traversable
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
     * @param string|AdditionalIndexQueueItemIndexer $class
     * @param string|null $expectedException
     * @param int $resultCount
     *
     * @test
     * @dataProvider canGetAdditionalDocumentsDataProvider
     */
    public function canGetAdditionalDocuments($class, ?string $expectedException, int $expectedResultCount): void
    {
        if ($class !== null) {
            if (is_object($class)) {
                $classReference = get_class($class);
                GeneralUtility::addInstance($classReference, $class);
            } else {
                $classReference = $class;
            }
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'] = [
                $classReference,
            ];
        }

        $indexer = $this->getAccessibleMock(
            Indexer::class,
            null,
            [],
            '',
            false
        );

        if ($expectedException !== null) {
            self::expectException($expectedException);
        }

        $documents = $indexer->_call(
            'getAdditionalDocuments',
            $this->createMock(Item::class),
            0,
            $this->createMock(Document::class)
        );
        self::assertEquals($expectedResultCount, count($documents));
        foreach ($documents as $document) {
            self::assertTrue($document instanceof Document);
        }
    }

    /**
     * Data provider for "canGetAdditionalDocuments"
     *
     * @return Traversable
     */
    public function canGetAdditionalDocumentsDataProvider(): Traversable
    {
        yield 'no AdditionalIndexQueueItemIndexer registered' => [
            null,
            null,
            0,
        ];

        yield 'unknown class as AdditionalIndexQueueItemIndexer registered' => [
            'invalidClass',
            InvalidArgumentException::class,
            0,
        ];

        yield 'invalid AdditionalIndexQueueItemIndexer registered' => [
            new \stdClass(),
            UnexpectedValueException::class,
            0,
        ];

        $indexerMock = $this->createMock(AdditionalIndexQueueItemIndexer::class);
        $indexerMock
            ->expects(self::once())
            ->method('getAdditionalItemDocuments');
        yield 'valid AdditionalIndexQueueItemIndexer, no additional documents' => [
            $indexerMock,
            null,
            0,
        ];

        $indexerMock = $this->createMock(AdditionalIndexQueueItemIndexer::class);
        $indexerMock
            ->expects(self::once())
            ->method('getAdditionalItemDocuments')
            ->willReturn([$this->createMock(Document::class)]);
        yield 'valid AdditionalIndexQueueItemIndexer, one additional documents' => [
            $indexerMock,
            null,
            1,
        ];
    }

    /**
     * @param object|null $modifier
     * @param string|null $expectedException
     *
     * @test
     * @dataProvider canCallDocumentsModifierHookDataProvider
     */
    public function canCallDocumentsModifierHook(?object $modifier, ?string $expectedException): void
    {
        $itemMock = $this->createMock(Item::class);
        if ($modifier !== null) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments'] = [
                get_class($modifier),
            ];
        } else {
            $modifier = $this->createMock(PageIndexerDocumentsModifier::class);
            $modifier->expects(self::never())->method('modifyDocuments');
        }
        GeneralUtility::addInstance(get_class($modifier), $modifier);

        if ($expectedException !== null) {
            self::expectException($expectedException);
        }

        Indexer::preAddModifyDocuments($itemMock, 0, []);
    }

    /**
     * Data provider for "canCallDocumentsModifierHook"
     *
     * @return Traversable
     */
    public function canCallDocumentsModifierHookDataProvider(): Traversable
    {
        yield 'no modifier' => [null, null];

        yield 'invalid modifier' => [new \stdClass(), RuntimeException::class];

        $modifierMock = $this->createMock(PageIndexerDocumentsModifier::class);
        $modifierMock
            ->expects(self::once())
            ->method('modifyDocuments')
            ->willReturn([]);
        yield 'valid modifier' => [$modifierMock, null];
    }

    /**
     * @test
     */
    public function indexerAlwaysInitializesTSFE()
    {
        self::markTestSkipped('API has been changed, the test case must be moved, since it is still relevant.');
        /* @var Item|ObjectProphecy $item */
        $item =  $this->prophesize(Item::class);
        $item->getType()->willReturn('pages');
        $item->getRecordUid()->willReturn(12);
        $item->getRootPageUid()->willReturn(1);
        $item->getIndexingConfigurationName()->willReturn('fakeIndexingConfigurationName');

        /* @var FrontendEnvironment|ObjectProphecy $frontendEnvironment */
        $frontendEnvironment = $this->prophesize(FrontendEnvironment::class);
        $frontendEnvironment->getSolrConfigurationFromPageId(12, 0)->shouldBeCalled();

        $indexer = $this->getMockBuilderForIndexer([], null, null, null, null, $frontendEnvironment->reveal())
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
        $itemToDocumentReflectionMethod->invokeArgs($indexer, [$item->reveal()]);
    }

    /**
     * Returns a mock builder with dump-mocked object properties.
     *
     * @param array $options
     * @param PagesRepository|null $pagesRepository
     * @param Builder|null $documentBuilder
     * @param SolrLogManager|null $logger
     * @param ConnectionManager|null $connectionManager
     * @param FrontendEnvironment|null $frontendEnvironment
     * @return MockBuilder
     */
    protected function getMockBuilderForIndexer(
        array $options = [],
        PagesRepository $pagesRepository = null,
        Builder $documentBuilder = null,
        SolrLogManager $logger = null,
        ConnectionManager $connectionManager = null,
        FrontendEnvironment $frontendEnvironment = null
    ): MockBuilder {
        return $this->getMockBuilder(Indexer::class)->setConstructorArgs([
            $options,
            $pagesRepository ?? $this->getDumbMock(PagesRepository::class),
            $documentBuilder ?? $this->getDumbMock(Builder::class),
            $logger ?? $this->getDumbMock(SolrLogManager::class),
            $connectionManager ?? $this->getDumbMock(ConnectionManager::class),
            $frontendEnvironment ?? $this->getDumbMock(FrontendEnvironment::class),
        ]);
    }
}
