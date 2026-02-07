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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Exception\InvalidConnectionException;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Container;
use Traversable;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PHP Unit test for connection manager
 */
class ConnectionManagerTest extends SetUpUnitTestCase
{
    protected ConnectionManager|MockObject $connectionManager;
    protected SolrLogManager|MockObject $logManagerMock;
    protected PagesRepository|MockObject $pageRepositoryMock;
    protected SiteRepository|MockObject $siteRepositoryMock;
    protected ConfigurationManager $configurationManager;

    /**
     * Set up the connection manager test
     */
    protected function setUp(): void
    {
        $this->logManagerMock = $this->createMock(SolrLogManager::class);
        $this->pageRepositoryMock = $this->createMock(PagesRepository::class);
        $this->siteRepositoryMock = $this->createMock(SiteRepository::class);

        $this->configurationManager = new ConfigurationManager();
        $this->connectionManager = new ConnectionManager(
            $this->pageRepositoryMock,
            $this->siteRepositoryMock,
        );

        $container = new Container();
        $container->set(ClientInterface::class, $this->createMock(ClientInterface::class));
        $container->set(RequestFactoryInterface::class, $this->createMock(RequestFactoryInterface::class));
        $container->set(StreamFactoryInterface::class, $this->createMock(StreamFactoryInterface::class));
        $container->set(EventDispatcherInterface::class, $this->createMock(EventDispatcherInterface::class));
        $container->set(SiteFinder::class, $this->createMock(SiteFinder::class));
        GeneralUtility::setContainer($container);

        parent::setUp();
    }

    /**
     * Provides data for the connection test
     */
    public static function connectDataProvider(): Traversable
    {
        yield 'invalid' => [
            'scheme' => '',
            'host' => 'localhost',
            'port' => null,
            'path' => '',
            'core' => 'core_de',
            'expectsException' => true,
            'expectedConnectionString' => null,
        ];

        yield 'valid without path' => [
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'port' => 8181,
            'path' => '' ,
            'core' => 'core_de',
            'expectsException' => false,
            'expectedConnectionString' => 'https://127.0.0.1:8181/solr/core_de/',
        ];

        yield 'valid with slash in path' => [
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'port' => 8181,
            'path' => '/' ,
            'core' => 'core_de',
            'expectsException' => false,
            'expectedConnectionString' => 'https://127.0.0.1:8181/solr/core_de/',
        ];

        yield 'valid connection with path' => [
            'scheme' => 'https',
            'host' => '127.0.0.1',
            'port' => 8181,
            'path' => '/production/' ,
            'core' => 'core_de',
            'expectsException' => false,
            'expectedConnectionString' => 'https://127.0.0.1:8181/production/solr/core_de/',
        ];
    }

    /**
     * Tests the connection
     */
    #[DataProvider('connectDataProvider')]
    #[Test]
    public function canConnect(
        string $scheme,
        string $host,
        ?int $port,
        string $path,
        string $core,
        bool $expectsException,
        ?string $expectedConnectionString,
    ): void {
        $exceptionOccurred = false;
        try {
            $configuration = [
                'read' => ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'path' => $path, 'core' => $core],
            ];
            $configuration['write'] = $configuration['read'];

            $solrService = $this->connectionManager->getConnectionFromConfiguration(
                $configuration,
                $this->createMock(TypoScriptConfiguration::class),
            );
            self::assertEquals($expectedConnectionString, $solrService->getReadService()->__toString());
        } catch (InvalidConnectionException $exception) {
            $exceptionOccurred = true;
        }
        self::assertEquals($expectsException, $exceptionOccurred);
    }
}
