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
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Node;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

/**
 * PHP Unit test for connection manager
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
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
        $TSFE = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE'] = $TSFE;

        $this->logManagerMock = $this->createMock(SolrLogManager::class);
        $this->pageRepositoryMock = $this->createMock(PagesRepository::class);
        $this->siteRepositoryMock = $this->createMock(SiteRepository::class);

        $this->configurationManager = new ConfigurationManager();
        $this->connectionManager = $this->getMockBuilder(ConnectionManager::class)
            ->setConstructorArgs([
                $this->pageRepositoryMock,
                $this->siteRepositoryMock,
            ])
            ->onlyMethods(['getSolrConnectionForNodes'])
            ->getMock();
        parent::setUp();
    }

    /**
     * Provides data for the connection test
     */
    public function connectDataProvider(): array
    {
        return [
            ['host' => 'localhost', 'port' => '', 'path' => '', 'scheme' => '', 'expectsException' => true, 'expectedConnectionString' => null],
            ['host' => '127.0.0.1', 'port' => 8181, 'path' => '/solr/core_de/', 'scheme' => 'https', 'expectsException' => false, 'expectedConnectionString' => 'https://127.0.0.1:8181/solr/core_de/'],
        ];
    }

    /**
     * Tests the connect
     *
     * @dataProvider connectDataProvider
     * @test
     */
    public function canConnect(string $host, string|int $port, string $path, string $scheme, bool $expectsException, ?string $expectedConnectionString): void
    {
        $self = $this;
        $this->connectionManager->expects(self::once())->method('getSolrConnectionForNodes')->willReturnCallback(
            function ($readNode, $writeNode) use ($self) {
                $readNode = Node::fromArray($readNode);
                $writeNode = Node::fromArray($writeNode);
                $typoScriptConfigurationMock = $self->createMock(TypoScriptConfiguration::class);
                $synonymsParserMock = $self->createMock(SynonymParser::class);
                $stopWordParserMock = $self->createMock(StopWordParser::class);
                $schemaParserMock = $self->createMock(SchemaParser::class);

                return new SolrConnection(
                    $readNode,
                    $writeNode,
                    $typoScriptConfigurationMock,
                    $synonymsParserMock,
                    $stopWordParserMock,
                    $schemaParserMock,
                    $self->logManagerMock,
                    $this->createMock(ClientInterface::class),
                    $this->createMock(RequestFactoryInterface::class),
                    $this->createMock(StreamFactoryInterface::class),
                    $this->createMock(EventDispatcherInterface::class)
                );
            }
        );
        $exceptionOccurred = false;
        try {
            $readNode = ['host' => $host, 'port' => $port, 'path' => $path, 'scheme' => $scheme];
            $configuration['read'] = $readNode;
            $configuration['write'] = $readNode;

            $solrService = $this->connectionManager->getConnectionFromConfiguration($configuration);
            self::assertEquals($expectedConnectionString, $solrService->getReadService()->__toString());
        } catch (UnexpectedValueException $exception) {
            $exceptionOccurred = true;
        }
        self::assertEquals($expectsException, $exceptionOccurred);
    }
}
