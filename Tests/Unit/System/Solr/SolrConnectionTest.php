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
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Endpoint;
use Throwable;
use Traversable;

/**
 * Class SolrConnectionTest
 */
class SolrConnectionTest extends SetUpUnitTestCase
{
    /**
     * @param Endpoint|null $readEndpoint
     * @param Endpoint|null $writeEndpoint
     * @param TypoScriptConfiguration|null $configuration
     * @param SynonymParser|null $synonymParser
     * @param StopWordParser|null $stopWordParser
     * @param SchemaParser|null $schemaParser
     * @param SolrLogManager|null $logManager
     * @param ClientInterface|null $psr7Client
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null $streamFactory
     * @param EventDispatcherInterface|null $eventDispatcher
     * @return SolrConnection|null
     */
    protected function getSolrConnectionWithDummyConstructorArgs(
        ?Endpoint $readEndpoint = null,
        ?Endpoint $writeEndpoint = null,
        ?TypoScriptConfiguration $configuration = null,
        ?SynonymParser $synonymParser = null,
        ?StopWordParser $stopWordParser = null,
        ?SchemaParser $schemaParser = null,
        ?SolrLogManager $logManager = null,
        ?ClientInterface $psr7Client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ): ?SolrConnection {
        try {
            return new SolrConnection(
                $readEndpoint ?? $this->createMock(Endpoint::class),
                $writeEndpoint ?? $this->createMock(Endpoint::class),
                $configuration ?? $this->createMock(TypoScriptConfiguration::class),
                $synonymParser ?? $this->createMock(SynonymParser::class),
                $stopWordParser ?? $this->createMock(StopWordParser::class),
                $schemaParser ?? $this->createMock(SchemaParser::class),
                $logManager ?? $this->createMock(SolrLogManager::class),
                $psr7Client ?? $this->createMock(ClientInterface::class),
                $requestFactory ?? $this->createMock(RequestFactoryInterface::class),
                $streamFactory ?? $this->createMock(StreamFactoryInterface::class),
                $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            );
        } catch (Throwable) {
            // No exception will be ever happen, this is for saving up the lines in test cases.
        }
        return null;
    }

    /**
     * @throws MockObjectException
     */
    #[Test]
    public function authenticationIsNotTriggeredWithoutUsername(): void
    {
        $endpointMock = $this->createMock(Endpoint::class);
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(self::any())->method('getEndpoints')->willReturn([$endpointMock]);

        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/', 'core' => 'core_en', 'scheme' => 'https', 'username' => '', 'password' => ''],
        );
        $writeEndpoint = $readEndpoint;
        $connection = $this->getSolrConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects(self::never())->method('setAuthentication');
        $connection->getAdminService();
    }

    /**
     * @throws MockObjectException
     */
    #[Test]
    public function authenticationIsTriggeredWhenUsernameIsPassed(): void
    {
        $endpointMock = $this->createMock(Endpoint::class);
        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(self::any())->method('getEndpoints')->willReturn([$endpointMock]);

        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => '/solr/', 'core' => 'core_en', 'scheme' => 'https', 'username' => 'foo', 'password' => 'bar'],
        );
        $writeEndpoint = $readEndpoint;
        $connection = $this->getSolrConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        $connection->setClient($clientMock, 'admin');

        $endpointMock->expects(self::once())->method('setAuthentication');
        $connection->getAdminService();
    }

    public static function coreNameDataProvider(): Traversable
    {
        yield ['path' => '/solr/', 'core' => 'bla', 'expectedCoreName' => 'bla'];
        yield ['path' => '/somewherelese/solr/', 'core' => 'corename', 'expectedCoreName' => 'corename'];
    }

    /**
     * @throws MockObjectException
     */
    #[DataProvider('coreNameDataProvider')]
    #[Test]
    public function canGetCoreName(string $path, string $core, string $expectedCoreName): void
    {
        $fakeConfiguration = $this->createMock(TypoScriptConfiguration::class);
        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'core' => $core, 'scheme' => 'http', 'username' => '', 'password' => ''],
        );
        $writeEndpoint = $readEndpoint;
        $solrService = $this->getSolrConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint, $fakeConfiguration);
        self::assertSame($expectedCoreName, $solrService->getReadService()->getPrimaryEndpoint()->getCore());
    }

    public static function coreBasePathDataProvider(): Traversable
    {
        yield ['path' => '/', 'core' => 'bla', 'expectedCoreBasePath' => ''];
        yield ['path' => '/somewherelese/', 'core' => 'corename', 'expectedCoreBasePath' => '/somewherelese'];
    }

    #[DataProvider('coreBasePathDataProvider')]
    #[Test]
    public function canGetCoreBasePath(string $path, string $core, string $expectedCoreBasePath): void
    {
        $readEndpoint = new Endpoint(
            ['host' => 'localhost', 'port' => 8080, 'path' => $path, 'core' => $core, 'scheme' => 'http', 'username' => '', 'password' => ''],
        );
        $writeEndpoint = $readEndpoint;
        $solrService = $this->getSolrConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        self::assertSame($expectedCoreBasePath, $solrService->getReadService()->getPrimaryEndpoint()->getPath());
    }

    #[Test]
    public function coreBaseUriContainsAllSegments(): void
    {
        $readEndpoint = new Endpoint([
            'host' => 'localhost',
            'port' => 8080,
            'path' => '/mypath/',
            'core' => 'core_de',
            'scheme' => 'http',
            'username' => '',
            'password' => '',
        ]);
        $writeEndpoint = $readEndpoint;
        $solrService = $this->getSolrConnectionWithDummyConstructorArgs($readEndpoint, $writeEndpoint);
        self::assertSame(
            'http://localhost:8080/mypath/solr/core_de/',
            $solrService->getEndpoint('read')->getCoreBaseUri(),
            'Core base URI doesn\'t contain expected segments',
        );
    }
}
