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
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockBuilder;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use ReflectionClass;

/**
 * Class IndexerTest
 */
class IndexerTest extends UnitTest
{
    use ProphecyTrait;

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
