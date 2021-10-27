<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
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
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->responseMock = $this->getDumbMock(Response::class);
        $this->requestMock = $this->getDumbMock(Request::class);
        $this->clientMock = $this->getDumbMock(Client::class);
        $this->clientMock->expects($this->any())->method('createRequest')->willReturn($this->requestMock);
        $this->clientMock->expects($this->any())->method('executeRequest')->willReturn($this->responseMock);

        $this->service = new SolrReadService($this->clientMock);
    }

    /**
     * @test
     */
    public function pingIsOnlyDoingOnePingCallWhenCacheIsEnabled()
    {
        // we fake a 200 OK response and expect that
        $this->responseMock->expects($this->once())->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects($this->once())->method('createPing')->willReturn($this->getDumbMock(PingQuery::class));
        $this->service->ping();
        $this->service->ping();
    }

    /**
     * @test
     */
    public function pingIsOnlyDoingManyPingCallsWhenCacheIsDisabled()
    {
        // we fake a 200 OK response and expect that
        $this->responseMock->expects($this->exactly(2))->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects($this->exactly(2))->method('createPing')->willReturn($this->getDumbMock(PingQuery::class));
        $this->service->ping(false);
        $this->service->ping(false);
    }

    /**
     * @test
     */
    public function searchMethodIsTriggeringGetRequest()
    {
        $this->responseMock->expects($this->once())->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects($this->once())->method('createRequest')->willReturn($this->getDumbMock(Request::class));

        $searchQuery = new SearchQuery();
        $searchQuery->setQuery('foo');
        $result = $this->service->search($searchQuery);

        $this->assertSame(200, $result->getHttpStatus(), 'Expecting to get a 200 OK response');
        $this->assertTrue($this->service->hasSearched(), 'hasSearch indicates that no search was triggered');
    }

    /**
     * @return array
     */
    public function readServiceExceptionDataProvider()
    {
        return [
            'Communication error' => ['exceptionClass' => SolrUnavailableException::class, 0],
            'Internal Server eror' => ['expcetionClass' => SolrInternalServerErrorException::class, 500],
            'Other unspecific error' => ['expcetionClass' => SolrCommunicationException::class, 555]
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
        $this->responseMock->expects($this->any())->method('getStatusCode')->willReturn($statusCode);
        $this->clientMock->expects($this->once())->method('createRequest')->willReturn($this->getDumbMock(Request::class));

        $this->clientMock->expects($this->once())->method('executeRequest')->willReturnCallback(function() use ($statusCode) {
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
