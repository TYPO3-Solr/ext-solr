<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\IndexQueue;

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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument\Builder;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use PHPUnit\Framework\MockObject\MockBuilder;
use ReflectionClass;
use ReflectionException;


/**
 * Class IndexerTest
 */
class IndexerTest extends UnitTest
{

    public function setUp()
    {
        parent::setUp();
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function indexerAlwaysInitializesTSFE()
    {
        $item =  $this->prophesize(Item::class);
        $item->getType()->willReturn('pages');
        $item->getRecordUid()->willReturn(12);

        $frontendEnvironment = $this->prophesize(FrontendEnvironment::class);
        $frontendEnvironment->initializeTsfe(12, 0)->shouldBeCalled();

        $indexer = $this->getMockBuilderForIndexer([], null, null, null, null, $frontendEnvironment->reveal())
            ->setMethods([
                'getFullItemRecord'
            ])
            ->getMock();

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
            $frontendEnvironment ?? $this->getDumbMock(FrontendEnvironment::class)
        ]);
    }
}