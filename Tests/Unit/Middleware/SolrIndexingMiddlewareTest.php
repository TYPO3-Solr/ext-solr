<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Middleware;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\Middleware\SolrIndexingMiddleware;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionMethod;
use Traversable;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Testcase for the middleware that turns an indexing sub-request into Solr documents
 */
final class SolrIndexingMiddlewareTest extends SetUpUnitTestCase
{
    #[DataProvider('getAdditionalDocumentsDataProvider')]
    #[Test]
    public function canGetAdditionalDocuments(?Closure $listener, int $expectedResultCount): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        if ($listener !== null) {
            $eventDispatcher->expects(self::once())->method('dispatch')->willReturnCallback($listener);
        } else {
            $eventDispatcher->expects(self::once())->method('dispatch')->willReturnArgument(0);
        }

        $middleware = new SolrIndexingMiddleware(
            $eventDispatcher,
            $this->createMock(IndexingResultCollector::class),
            $this->createMock(ConnectionManager::class),
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteRepository::class),
            $this->createMock(FrontendEnvironment::class),
        );

        $documents = (new ReflectionMethod($middleware, 'getAdditionalDocuments'))->invoke(
            $middleware,
            new Document(),
            $this->createMock(Item::class),
            $this->createMock(ServerRequest::class),
        );

        self::assertCount($expectedResultCount, $documents);
    }

    public static function getAdditionalDocumentsDataProvider(): Traversable
    {
        yield 'no listener registered' => [
            'listener' => null,
            'expectedResultCount' => 1,
        ];
        yield 'valid listener, no additional documents' => [
            'listener' => static function (BeforeDocumentIsProcessedForIndexingEvent $event) {
                return $event;
            },
            'expectedResultCount' => 1,
        ];
        yield 'valid listener, adds an additional document' => [
            'listener' => static function (BeforeDocumentIsProcessedForIndexingEvent $event) {
                $event->addDocuments([new Document()]);
                return $event;
            },
            'expectedResultCount' => 2,
        ];
    }
}
