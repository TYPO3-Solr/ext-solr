<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;

/**
 * Tests the SolrAdminService class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrAdminServiceTest extends UnitTest
{

    /**
     * @var SolrAdminService
     */
    protected $adminService;

    /**
     * @var Client
     */
    protected $clientMock;

    /**
     * @var Endpoint
     */
    protected $endpointMock;

    protected function setUp(): void
    {
        $this->endpointMock = $this->getDumbMock(Endpoint::class);
        $this->endpointMock->expects(self::any())->method('getScheme')->willReturn('http');
        $this->endpointMock->expects(self::any())->method('getHost')->willReturn('localhost');
        $this->endpointMock->expects(self::any())->method('getPort')->willReturn(8983);
        $this->endpointMock->expects(self::any())->method('getPath')->willReturn('/solr');
        $this->endpointMock->expects(self::any())->method('getCore')->willReturn('core_en');
        $this->endpointMock->expects(self::any())->method('getCoreBaseUri')->willReturn('http://localhost:8983/solr/core_en/');

        $this->clientMock = $this->getDumbMock(Client::class);
        $this->clientMock->expects(self::any())->method('getEndpoints')->willReturn([$this->endpointMock]);
        $this->adminService = $this->getMockBuilder(SolrAdminService::class)->setConstructorArgs([$this->clientMock])->onlyMethods(['_sendRawGet'])->getMock();
        parent::setUp();
    }
    /**
     * @test
     */
    public function getLukeMetaDataIsSendingRequestToExpectedUrl()
    {
        $fakedLukeResponse = [];
        $this->assertGetRequestIsTriggered('http://localhost:8983/solr/core_en/admin/luke?numTerms=50&wt=json&fl=%2A', $fakedLukeResponse);
        $result = $this->adminService->getLukeMetaData(50);

        self::assertSame($fakedLukeResponse, $result, 'Could not get expected result from getLukeMetaData');
    }

    /**
     * @test
     */
    public function getPluginsInformation()
    {
        $fakePluginsResponse = $this->getDumbMock(ResponseAdapter::class);
        $fakePluginsResponse->responseHeader = null;
        $this->assertGetRequestIsTriggered('http://localhost:8983/solr/core_en/admin/plugins?wt=json', $fakePluginsResponse);
        $result = $this->adminService->getPluginsInformation();
        self::assertSame($fakePluginsResponse, $result, 'Could not get expected result from getPluginsInformation');
    }

    /**
     * @test
     */
    public function getSystemInformation()
    {
        $fakeSystemInformationResponse = $this->getDumbMock(ResponseAdapter::class);
        $this->assertGetRequestIsTriggered('http://localhost:8983/solr/core_en/admin/system?wt=json', $fakeSystemInformationResponse);
        $result = $this->adminService->getSystemInformation();
        self::assertSame($fakeSystemInformationResponse, $result, 'Could not get expected result from getSystemInformation');
    }

    /**
     * @test
     */
    public function getSolrServerVersion()
    {
        $fakeSystemInformationResponse = $this->getDumbMock(ResponseAdapter::class);
        $fakeSystemInformationResponse->lucene = new \stdClass();
        $fakeSystemInformationResponse->lucene->{'solr-spec-version'} = '6.2.1';
        $this->assertGetRequestIsTriggered('http://localhost:8983/solr/core_en/admin/system?wt=json', $fakeSystemInformationResponse);
        $result = $this->adminService->getSolrServerVersion();
        self::assertSame('6.2.1', $result, 'Can not get solr version from faked response');
    }

    /**
     * @test
     */
    public function canGetSolrConfigNameFromFakedXmlResponse()
    {
        $fakeTestSchema = $this->getFixtureContentByName('solrconfig.xml');
        $fakedSolrConfigResponse = $this->getDumbMock(ResponseAdapter::class);
        $fakedSolrConfigResponse->expects(self::once())->method('getRawResponse')->willReturn($fakeTestSchema);

        $this->assertGetRequestIsTriggered('http://localhost:8983/solr/core_en/admin/file?file=solrconfig.xml', $fakedSolrConfigResponse);
        $expectedSchemaVersion = 'tx_solr-9-9-9--20221020';
        self::assertSame($expectedSchemaVersion, $this->adminService->getSolrconfigName(), 'SolrAdminService could not parse the solrconfig version as expected');
    }

    /**
     * @param string $url
     * @param mixed $fakeResponse
     */
    protected function assertGetRequestIsTriggered($url, $fakeResponse)
    {
        $this->adminService->expects(self::once())->method('_sendRawGet')->with($url)->willReturn($fakeResponse);
    }
}
