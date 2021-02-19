<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr;

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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\UnifiedConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Node;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;

/**
 * Class SolrConnectionTest
 * @copyright (c) 2010-2015 Timo Hund <timo.hund@dkd.de>
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
        $unifiedConfiguration = new UnifiedConfiguration(1);

        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/core_en/', 'scheme' => 'https', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $connection = new SolrConnection($readNode, $writeNode, $unifiedConfiguration);
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
        $unifiedConfiguration = new UnifiedConfiguration(1);
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/core_en/', 'scheme' => 'https', 'username' => 'foo', 'password' => 'bar']
        );
        $writeNode = $readNode;
        $connection = new SolrConnection($readNode, $writeNode, $unifiedConfiguration);
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
        $unifiedConfiguration = new UnifiedConfiguration(1);
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $solrService = new SolrConnection($readNode, $writeNode, $unifiedConfiguration);
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
        $unifiedConfiguration = new UnifiedConfiguration(1);
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $solrService = new SolrConnection($readNode, $writeNode, $unifiedConfiguration);
        $this->assertSame($expectedCoreBasePath, $solrService->getReadService()->getPrimaryEndpoint()->getPath());
    }

    /**
     * @test
     */
    public function timeoutIsUsedFromNode()
    {
        $unifiedConfiguration = new UnifiedConfiguration(1);
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/', 'scheme' => 'http', 'username' => '', 'password' => '', 'timeout' => 99]
        );
        $writeNode = $readNode;
        $solrService = new SolrConnection($readNode, $writeNode, $unifiedConfiguration);

        $this->assertSame(99, $solrService->getReadService()->getPrimaryEndpoint()->getTimeout(), 'Default timeout was not set from configuration');
    }

    /**
     * @test
     */
    public function toStringContainsAllSegments()
    {
        $unifiedConfiguration = new UnifiedConfiguration(1);
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/core_de/', 'scheme' => 'http', 'username' => '', 'password' => '', 'timeout' => 99]
        );
        $writeNode = $readNode;
        $solrService = new SolrConnection($readNode, $writeNode, $unifiedConfiguration);
        $this->assertSame('http://localhost:8080/solr/core_de/', (string) $solrService->getNode('read'), 'Could not get string representation of connection');
    }
}
