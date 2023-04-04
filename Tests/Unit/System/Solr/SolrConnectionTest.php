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
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\Node;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;

/**
 * Class SolrConnectionTest
 */
class SolrConnectionTest extends UnitTest
{

    /**
     * @param Node|null $readNode
     * @param Node|null $writeNode
     * @param TypoScriptConfiguration|null $configuration
     * @param SynonymParser|null $synonymParser
     * @param StopWordParser|null $stopWordParser
     * @param SchemaParser|null $schemaParser
     * @param SolrLogManager|null $logManager
     * @param ClientInterface|null $psr7Client
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null $streamFactory
     * @param EventDispatcherInterface|null $eventDispatcher
     * @return SolrConnection
     */
    protected function getSolrConnectionWithDummyConstructorArgs(
        Node $readNode = null,
        Node $writeNode = null,
        TypoScriptConfiguration $configuration = null,
        SynonymParser $synonymParser = null,
        StopWordParser $stopWordParser = null,
        SchemaParser $schemaParser = null,
        SolrLogManager $logManager = null,
        ClientInterface $psr7Client = null,
        RequestFactoryInterface $requestFactory = null,
        StreamFactoryInterface $streamFactory = null,
        EventDispatcherInterface $eventDispatcher = null
    ): SolrConnection {
        try {
            return new SolrConnection(
                $readNode ?? $this->getDumbMock(Node::class),
                $writeNode ?? $this->getDumbMock(Node::class),
                $configuration ?? $this->getDumbMock(TypoScriptConfiguration::class),
                $synonymParser ?? $this->getDumbMock(SynonymParser::class),
                $stopWordParser ?? $this->getDumbMock(StopWordParser::class),
                $schemaParser ?? $this->getDumbMock(SchemaParser::class),
                $logManager ?? $this->getDumbMock(SolrLogManager::class),
                $psr7Client ?? $this->getDumbMock(ClientInterface::class),
                $requestFactory ?? $this->getDumbMock(RequestFactoryInterface::class),
                $streamFactory ?? $this->getDumbMock(StreamFactoryInterface::class),
                $eventDispatcher ?? $this->getDumbMock(EventDispatcherInterface::class)
            );
        } catch (\Exception $e) {
            // No exception will be ever happen, this is for saving up the lines in test cases.
        }
    }

    /**
     * @test
     */
    public function authenticationIsNotTriggeredWithoutUsername()
    {
        /* @var Endpoint $endpointMock */
        $endpointMock = $this->getDumbMock(Endpoint::class);
        /* @var Client $clientMock */
        $clientMock = $this->getDumbMock(Client::class);
        $clientMock->expects(self::any())->method('getEndpoints')->willReturn([$endpointMock]);

        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/core_en/', 'scheme' => 'https', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $connection = $this->getSolrConnectionWithDummyConstructorArgs($readNode, $writeNode);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects(self::never())->method('setAuthentication');
        $connection->getAdminService();
    }

    /**
     * @test
     */
    public function authenticationIsTriggeredWhenUsernameIsPassed()
    {
        $endpointMock = $this->getDumbMock(Endpoint::class);
        $clientMock = $this->getDumbMock(Client::class);
        $clientMock->expects(self::any())->method('getEndpoints')->willReturn([$endpointMock]);

        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/core_en/', 'scheme' => 'https', 'username' => 'foo', 'password' => 'bar']
        );
        $writeNode = $readNode;
        $connection = $this->getSolrConnectionWithDummyConstructorArgs($readNode, $writeNode);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects(self::once())->method('setAuthentication');
        $connection->getAdminService();
    }

    /**
     * @return array
     */
    public function coreNameDataProvider(): array
    {
        return [
            ['path' => '/solr/bla', 'expectedName' => 'bla'],
            ['path' => '/somewherelese/solr/corename', 'expectedName' => 'corename'],
        ];
    }

    /**
     * @dataProvider coreNameDataProvider
     * @test
     */
    public function canGetCoreName($path, $expectedCoreName)
    {
        $fakeConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $solrService = $this->getSolrConnectionWithDummyConstructorArgs($readNode, $writeNode, $fakeConfiguration);
        self::assertSame($expectedCoreName, $solrService->getReadService()->getPrimaryEndpoint()->getCore());
    }

    /**
     * @return array
     */
    public function coreBasePathDataProvider(): array
    {
        return [
            ['path' => '/solr/bla', 'expectedPath' => ''],
            ['path' => '/somewherelese/solr/corename', 'expectedCoreBasePath' => '/somewherelese'],
        ];
    }

    /**
     * @dataProvider coreBasePathDataProvider
     * @test
     */
    public function canGetCoreBasePath($path, $expectedCoreBasePath)
    {
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $solrService = $this->getSolrConnectionWithDummyConstructorArgs($readNode, $writeNode);
        self::assertSame($expectedCoreBasePath, $solrService->getReadService()->getPrimaryEndpoint()->getPath());
    }

    /**
     * @test
     */
    public function toStringContainsAllSegments()
    {
        $readNode = Node::fromArray(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/core_de/', 'scheme' => 'http', 'username' => '', 'password' => '']
        );
        $writeNode = $readNode;
        $solrService = $this->getSolrConnectionWithDummyConstructorArgs($readNode, $writeNode);
        self::assertSame('http://localhost:8080/solr/core_de/', (string)$solrService->getNode('read'), 'Could not get string representation of connection');
    }
}
