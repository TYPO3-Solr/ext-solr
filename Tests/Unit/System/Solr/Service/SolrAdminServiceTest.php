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
        $this->clientMock->expects(self::any())->method('getEndpoint')->willReturn($this->endpointMock);
        $this->adminService = $this->getMockBuilder(SolrAdminService::class)->setConstructorArgs([$this->clientMock])->onlyMethods(['_sendRawGet'])->getMock();
        parent::setUp();
    }
    /**
     * @test
     */
    public function getLukeMetaDataIsSendingRequestToExpectedUrl()
    {
        $fakedLukeResponse = $this->getDumbMock(ResponseAdapter::class);
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
    protected function assertGetRequestIsTriggered(string $url, $fakeResponse)
    {
        $this->adminService->expects(self::once())->method('_sendRawGet')->with($url)->willReturn($fakeResponse);
    }
}
