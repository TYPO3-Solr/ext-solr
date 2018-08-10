<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr;

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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;

/**
 * Class SolrConnectionTest
 * @package ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr
 */
class SolrConnectionTest extends UnitTest
{
    /**
     * @test
     */
    public function authenticationIsNotTriggeredWithoutUsername()
    {
        $endpointMock = $this->getDumbMock(Endpoint::class);
        $clientMock = $this->getDumbMock(Client::class);
        $clientMock->expects($this->any())->method('getEndpoints')->willReturn([$endpointMock]);
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);

        $connection = new SolrConnection('127.0.0.1', 8080, '/solr/core_en/', 'https', ' ', '', $configurationMock);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects($this->never())->method('setAuthentication');
        $connection->getAdminService();
    }

    /**
     * @test
     */
    public function authenticationIsTriggeredWhenUsernameIsPassed()
    {
        $endpointMock = $this->getDumbMock(Endpoint::class);
        $clientMock = $this->getDumbMock(Client::class);
        $clientMock->expects($this->any())->method('getEndpoints')->willReturn([$endpointMock]);
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);

        $connection = new SolrConnection('127.0.0.1', 8080, '/solr/core_en/', 'https', 'foo', 'bar', $configurationMock);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects($this->once())->method('setAuthentication');
        $connection->getAdminService();
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
        $solrService = new SolrConnection('localhost','8080', $path,'http', '', '', $fakeConfiguration);
        $this->assertSame($expectedCoreName, $solrService->getReadService()->getPrimaryEndpoint()->getCore());
    }

    /**
     * @return array
     */
    public function coreBasePathDataProvider()
    {
        return [
            ['path' => '/solr/bla', 'expectedPath' => '/solr'],
            ['path' => '/somewherelese/solr/corename', 'expectedCoreBasePath' => '/somewherelese/solr']
        ];
    }

    /**
     * @dataProvider coreBasePathDataProvider
     * @test
     */
    public function canGetCoreBasePath($path, $expectedCoreBasePath)
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $solrService = new SolrConnection('localhost','8080', $path,'http', '', '', $fakeConfiguration);
        $this->assertSame($expectedCoreBasePath, $solrService->getReadService()->getPrimaryEndpoint()->getPath());
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
                        'timeout' => 99
                    ]
                ]
            ]
        ]);
        $solrService = new SolrConnection('localhost','8080','/solr/','http', '', '', $configuration);
        $this->assertSame(99, $solrService->getReadService()->getPrimaryEndpoint()->getTimeout(), 'Default timeout was not set from configuration');
    }
}