<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use Apache_Solr_HttpTransport_Interface;
use Apache_Solr_HttpTransport_Response;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;

/**
 * Tests the ApacheSolrForTypo3\Solr\SolrService class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrServiceTest extends UnitTest
{
    /**
     * @test
     */
    public function pingIsOnlyDoingOnePingCallWhenCacheIsEnabled()
    {
        // we fake a 200 OK response and expect that
            /** @var  \Apache_Solr_HttpTransport_Response $responseMock */
        $responseMock = $this->getDumbMock(Apache_Solr_HttpTransport_Response::class);
        $responseMock->expects($this->any())->method('getStatusCode')->will($this->returnValue(200));

            /** @var \Apache_Solr_HttpTransport_Interface $transportMock */
        $transportMock = $this->getDumbMock(Apache_Solr_HttpTransport_Interface::class);
        // we expect that exactly one get request is done
        $transportMock->expects($this->once())->method('performGetRequest')->will($this->returnValue($responseMock));

        $solrService = $this->getMockedSolrServiceReturningFakeRepsone($transportMock);

        $solrService->ping();
        $solrService->ping();
    }

    /**
     * @test
     */
    public function pingIsOnlyDoingMayPingCallsWhenCacheIsDisabled()
    {

        // we fake a 200 OK response and expect that
        /** @var  \Apache_Solr_HttpTransport_Response $responseMock */
        $responseMock = $this->getDumbMock(Apache_Solr_HttpTransport_Response::class);
        $responseMock->expects($this->any())->method('getStatusCode')->will($this->returnValue(200));

        /** @var \Apache_Solr_HttpTransport_Interface $transportMock */
        $transportMock = $this->getDumbMock(Apache_Solr_HttpTransport_Interface::class);
        // we expect that exactly one get request is done
        $transportMock->expects($this->exactly(2))->method('performGetRequest')->will($this->returnValue($responseMock));

        /** @var $solrService SolrService */
        $solrService = $this->getMockedSolrServiceReturningFakeRepsone($transportMock);

        $solrService->ping(2, false);
        $solrService->ping(2, false);
    }

    /**
     * @test
     */
    public function timeoutIsInitializedFromConfiguration()
    {
        $configuration = new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'solr.' => [
                        'timeout' => 99.0
                    ]
                ]
            ]
        ]);

        $synonymsParserMock = $this->getDumbMock(SynonymParser::class);
        $stopWordParserMock = $this->getDumbMock(StopWordParser::class);
        $schemaParserMock = $this->getDumbMock(SchemaParser::class);
        $logManagerMock = $this->getDumbMock(SolrLogManager::class);

        $solrService = new SolrService('localhost', '8080', '/solr/', 'http', $configuration, $synonymsParserMock, $stopWordParserMock, $schemaParserMock, $logManagerMock);
        $this->assertSame(99.0, $solrService->getHttpTransport()->getDefaultTimeout(), 'Default timeout was not set from configuration');
    }

    /**
     * @test
     */
    public function canSetScheme()
    {
        $solrService = $this->getDefaultSolrServiceWithMockedDependencies();
        $this->assertSame('http', $solrService->getScheme());
        $solrService->setScheme('https');
    }

    /**
     * @test
     */
    public function setSchemeThrowsExceptionWhenEmptySchemeWasPassed()
    {
        $this->setExpectedException(\UnexpectedValueException::class, 'Scheme parameter is empty');
        $solrService = $this->getDefaultSolrServiceWithMockedDependencies();
        $solrService->setScheme('');
    }

    /**
     * @test
     */
    public function setSchemeThrowsExceptionWhenInvalidSchemeWasPassed()
    {
        $this->setExpectedException(\UnexpectedValueException::class, 'Unsupported scheme parameter, scheme must be http or https');
        $solrService = $this->getDefaultSolrServiceWithMockedDependencies();
        $solrService->setScheme('invalidscheme');
    }

    /**
     * @return array
     */
    public function coreNameDataProvider()
    {
        return [
            ['path' => '/solr/bla', 'expectedName' => 'bla'],
            ['path' => '/somewherelese/solr/corename', 'expectedName' => 'corename']

        ];
    }

    /**
     * @dataProvider coreNameDataProvider
     * @test
     */
    public function canGetCoreName($path, $expectedCoreName)
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $synonymsParserMock = $this->getDumbMock(SynonymParser::class);
        $stopWordParserMock = $this->getDumbMock(StopWordParser::class);
        $schemaParserMock = $this->getDumbMock(SchemaParser::class);
        $logManagerMock = $this->getDumbMock(SolrLogManager::class);

        $solrService = new SolrService('localhost', '8080', $path, 'http', $fakeConfiguration, $synonymsParserMock, $stopWordParserMock, $schemaParserMock, $logManagerMock);
        $this->assertSame($expectedCoreName, $solrService->getCoreName());
    }

    /**
     * @return array
     */
    public function coreBasePathDataProvider()
    {
        return [
            ['path' => '/solr/bla', 'expectedPath' => '/solr/'],
            ['path' => '/somewherelese/solr/corename', 'expectedCoreBasePath' => '/somewherelese/solr/']
        ];
    }

    /**
     * @dataProvider coreBasePathDataProvider
     * @test
     */
    public function canGetCoreBasePath($path, $expectedCoreBasePath)
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $synonymsParserMock = $this->getDumbMock(SynonymParser::class);
        $stopWordParserMock = $this->getDumbMock(StopWordParser::class);
        $schemaParserMock = $this->getDumbMock(SchemaParser::class);
        $logManagerMock = $this->getDumbMock(SolrLogManager::class);

        $solrService = new SolrService('localhost', '8080', $path, 'http', $fakeConfiguration, $synonymsParserMock, $stopWordParserMock, $schemaParserMock, $logManagerMock);
        $this->assertSame($expectedCoreBasePath, $solrService->getCoreBasePath());
    }

    /**
     * @param $transportMock
     * @return SolrService
     */
    protected function getMockedSolrServiceReturningFakeRepsone($transportMock)
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $synonymsParserMock = $this->getDumbMock(SynonymParser::class);
        $stopWordParserMock = $this->getDumbMock(StopWordParser::class);
        $schemaParserMock = $this->getDumbMock(SchemaParser::class);
        $logManagerMock = $this->getDumbMock(SolrLogManager::class);

        /** @var $solrService SolrService */
        $solrService = $this->getMockBuilder(SolrService::class)
            ->setMethods(['getHttpTransport'])
            ->setConstructorArgs(['test', 8983, '/solr/', 'http', $fakeConfiguration, $synonymsParserMock, $stopWordParserMock, $schemaParserMock, $logManagerMock])
            ->getMock();
        $solrService->expects($this->any())->method('getHttpTransport')->will($this->returnValue($transportMock));
        return $solrService;
    }

    /**
     * @return SolrService
     */
    protected function getDefaultSolrServiceWithMockedDependencies()
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $synonymsParserMock = $this->getDumbMock(SynonymParser::class);
        $stopWordParserMock = $this->getDumbMock(StopWordParser::class);
        $schemaParserMock = $this->getDumbMock(SchemaParser::class);
        $logManagerMock = $this->getDumbMock(SolrLogManager::class);
        $solrService = new SolrService('localhost', '8080', '/solr/', 'http', $fakeConfiguration, $synonymsParserMock, $stopWordParserMock, $schemaParserMock, $logManagerMock);
        return $solrService;
    }
}
