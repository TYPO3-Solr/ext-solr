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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrReadService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrInternalServerErrorException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\Client;
use Solarium\Core\Client\Request;
use Solarium\Core\Client\Response;
use Solarium\Exception\HttpException;
use Solarium\QueryType\Ping\Query as PingQuery;

/**
 * Tests the ApacheSolrForTypo3\Solr\SolrService class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrReadServiceTest extends UnitTest
{

    /**
     * @var Request
     */
    protected $requestMock;

    /**
     * @var Response
     */
    protected $responseMock;

    /**
     * @var Client
     */
    protected $clientMock;

    /**
     * @var SolrReadService
     */
    protected SolrReadService $service;

    protected function setUp(): void
    {
        $this->responseMock = $this->getDumbMock(Response::class);
        $this->requestMock = $this->getDumbMock(Request::class);
        $this->clientMock = $this->getDumbMock(Client::class);
        $this->clientMock->expects(self::any())->method('createRequest')->willReturn($this->requestMock);
        $this->clientMock->expects(self::any())->method('executeRequest')->willReturn($this->responseMock);

        $this->service = new SolrReadService($this->clientMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function pingIsOnlyDoingOnePingCallWhenCacheIsEnabled()
    {
        // we fake a 200 OK response and expect that
        $this->responseMock->expects(self::once())->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects(self::once())->method('createPing')->willReturn($this->getDumbMock(PingQuery::class));
        $this->service->ping();
        $this->service->ping();
    }

    /**
     * @test
     */
    public function pingIsOnlyDoingManyPingCallsWhenCacheIsDisabled()
    {
        // we fake a 200 OK response and expect that
        $this->responseMock->expects(self::exactly(2))->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects(self::exactly(2))->method('createPing')->willReturn($this->getDumbMock(PingQuery::class));
        $this->service->ping(false);
        $this->service->ping(false);
    }

    /**
     * @test
     */
    public function searchMethodIsTriggeringGetRequest()
    {
        $this->responseMock->expects(self::once())->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects(self::once())->method('createRequest')->willReturn($this->getDumbMock(Request::class));

        $searchQuery = new SearchQuery();
        $searchQuery->setQuery('foo');
        $result = $this->service->search($searchQuery);

        self::assertSame(200, $result->getHttpStatus(), 'Expecting to get a 200 OK response');
        self::assertTrue($this->service->hasSearched(), 'hasSearch indicates that no search was triggered');
    }

    /**
     * @return array
     */
    public function readServiceExceptionDataProvider()
    {
        return [
            'Communication error' => ['exceptionClass' => SolrUnavailableException::class, 0],
            'Internal Server eror' => ['expcetionClass' => SolrInternalServerErrorException::class, 500],
            'Other unspecific error' => ['expcetionClass' => SolrCommunicationException::class, 555],
        ];
    }

    /**
     * @dataProvider readServiceExceptionDataProvider
     * @param string $exceptionClass
     * @param int $statusCode
     * @test
     */
    public function searchThrowsExpectedExceptionForStatusCode($exceptionClass, $statusCode)
    {
        $this->responseMock->expects(self::any())->method('getStatusCode')->willReturn($statusCode);
        $this->clientMock->expects(self::once())->method('createRequest')->willReturn($this->getDumbMock(Request::class));

        $this->clientMock->expects(self::once())->method('executeRequest')->willReturnCallback(function () use ($statusCode) {
            throw new HttpException('Solr error', $statusCode);
        });
        $searchQuery = new SearchQuery();
        $searchQuery->setQuery('foo');
        $this->expectException($exceptionClass);

        $this->service->search($searchQuery);
    }

    /**
     * @return SolrReadService
     */
    protected function getDefaultSolrServiceWithMockedDependencies()
    {
        $clientMock = $this->getDumbMock(Client::class);
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $logManagerMock = $this->getDumbMock(SolrLogManager::class);
        $solrService = new SolrReadService($clientMock, $fakeConfiguration, $logManagerMock);
        return $solrService;
    }
}
