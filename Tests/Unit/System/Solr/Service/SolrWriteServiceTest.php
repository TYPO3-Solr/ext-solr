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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Service;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\Client;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\QueryType\Update\Query\Query;
use Solarium\QueryType\Update\Result;
use Traversable;

/**
 * Tests the ApacheSolrForTypo3\Solr\SolrService class
 */
class SolrWriteServiceTest extends SetUpUnitTestCase
{
    protected Response|MockObject $responseMock;
    protected Result|MockObject $resultMock;
    protected Client|MockObject $clientMock;
    protected TypoScriptConfiguration&MockObject $configuration;
    protected SolrWriteService $service;

    protected function setUp(): void
    {
        $this->responseMock = $this->createMock(Response::class);

        $this->resultMock = $this->createMock(Result::class);
        $this->resultMock->expects(self::any())->method('getResponse')->willReturn($this->responseMock);
        $this->clientMock = $this->createMock(Client::class);

        $this->configuration = $this->createMock(TypoScriptConfiguration::class);

        $this->service = new SolrWriteService($this->clientMock, $this->configuration);
        parent::setUp();
    }

    #[Test]
    public function canRunOptimizeIndex(): void
    {
        $this->responseMock->expects(self::once())->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects(self::once())->method('createUpdate')->willReturn($this->createMock(Query::class));
        $this->clientMock->expects(self::once())->method('update')->willReturn($this->resultMock);

        $result = $this->service->optimizeIndex();
        self::assertSame(200, $result->getResponse()->getStatusCode(), 'Expecting to get a 200 OK response');
    }

    #[Test]
    #[DataProvider('addDocumentsDataProvider')]
    public function canAddDocuments(bool $vectorSearchEnabled): void
    {
        $documents = [new Document()];
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects(self::once())->method('addDocuments')->with($documents);

        $requestMock = $this->createMock(Request::class);
        if ($vectorSearchEnabled) {
            $requestMock->expects(self::once())->method('addParam')->with('update.chain', 'textToVector');
        } else {
            $requestMock->expects(self::never())->method('addParam');
        }

        $this->configuration
            ->expects(self::once())
            ->method('isVectorSearchEnabled')
            ->willReturn($vectorSearchEnabled);

        $this->clientMock->expects(self::once())->method('createUpdate')->willReturn($queryMock);
        $this->clientMock
            ->expects(self::once())
            ->method('createRequest')
            ->with($queryMock)
            ->willReturn($requestMock);

        $this->service->addDocuments($documents);
    }

    public static function addDocumentsDataProvider(): Traversable
    {
        yield 'vector search disabled' => [
            'vectorSearchEnabled' => false,
        ];
        yield 'vector search enabled' => [
            'vectorSearchEnabled' => true,
        ];
    }
}
