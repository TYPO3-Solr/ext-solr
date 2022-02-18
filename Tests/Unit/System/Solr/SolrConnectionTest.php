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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Solr;

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
 *
 * @author Timo Hund <timo.hund@dkd.de>
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
    ): ?SolrConnection {
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
        } catch (\Throwable $e) {
            // No exception will be ever happen, this is for saving up the lines in test cases.
        }
        return null;
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
