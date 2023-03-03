<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2020 Jens Jacobsen <typo3@jens-jacobsen.de>
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

use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\Client;
use Solarium\Core\Client\Response;
use Solarium\QueryType\Update\Query\Query;
use Solarium\QueryType\Update\Result;

/**
 * Tests the ApacheSolrForTypo3\Solr\SolrService class
 *
 * @author Jens Jacobsen <typo3@jens-jacobsen.de>
 */
class SolrWriteServiceTest extends UnitTest
{
    /**
     * @var Response
     */
    protected $responseMock;

    /**
     * @var Result
     */
    protected $resultMock;

    /**
     * @var Client
     */
    protected $clientMock;

    /**
     * @var SolrWriteService
     */
    protected $service;

    protected function setUp(): void {
        parent::setUp();
        $this->responseMock = $this->getDumbMock(Response::class);

        $this->resultMock = $this->getDumbMock(Result::class);
        $this->resultMock->expects($this->any())->method('getResponse')->willReturn($this->responseMock);
        $this->clientMock = $this->getDumbMock(Client::class);

        $this->service = new SolrWriteService($this->clientMock);
    }

    /**
     * @test
     */
    public function canRunOptimizeIndex()
    {
        $this->responseMock->expects($this->once())->method('getStatusCode')->willReturn(200);
        $this->clientMock->expects($this->once())->method('createUpdate')->willReturn($this->getDumbMock(Query::class));
        $this->clientMock->expects($this->once())->method('update')->willReturn($this->resultMock);

        $result = $this->service->optimizeIndex();
        $this->assertSame(200, $result->getResponse()->getStatusCode(), 'Expecting to get a 200 OK response');
    }
}
