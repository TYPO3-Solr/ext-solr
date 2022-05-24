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
use ApacheSolrForTypo3\Solr\System\Records\SystemLanguage\SystemLanguageRepository;
use ApacheSolrForTypo3\Solr\System\Solr\Node;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SchemaParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\StopWordParser;
use ApacheSolrForTypo3\Solr\System\Solr\Parser\SynonymParser;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

/**
 * PHP Unit test for connection manager
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class ConnectionManagerTest extends UnitTest
{
    /**
     * Connection manager
     *
     * @var ConnectionManager
     */
    protected $connectionManager;

    /**
     * @var SolrLogManager
     */
    protected $logManagerMock;

    /**
     * @var SystemLanguageRepository
     */
    protected $languageRepositoryMock;

    /**
     * @var PagesRepository
     */
    protected $pageRepositoryMock;

    /**
     * @var SiteRepository
     */
    protected $siteRepositoryMock;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * Set up the connection manager test
     */
    protected function setUp(): void
    {
        $TSFE = $this->getDumbMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE'] = $TSFE;

        /** @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getDumbMock(TemplateService::class, ['linkData']);
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = Environment::getPublicPath() . '/';
        $GLOBALS['TSFE']->tmpl->setup['config.']['typolinkEnableLinksAcrossDomains'] = 0;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['host'] = 'localhost';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['port'] = '8999';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['path'] = '/solr/core_en/';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['scheme'] = 'http';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = 25;
        $GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';

        $this->logManagerMock = $this->getDumbMock(SolrLogManager::class);
        $this->languageRepositoryMock = $this->getDumbMock(SystemLanguageRepository::class);
        $this->pageRepositoryMock = $this->getDumbMock(PagesRepository::class);
        $this->siteRepositoryMock = $this->getDumbMock(SiteRepository::class);

        $this->configurationManager = new ConfigurationManager();
        $this->connectionManager = $this->getMockBuilder(ConnectionManager::class)
            ->setConstructorArgs([
                $this->languageRepositoryMock,
                $this->pageRepositoryMock,
                $this->siteRepositoryMock,
            ])
            ->onlyMethods(['getSolrConnectionForNodes'])
            ->getMock();
        parent::setUp();
    }

    /**
     * Provides data for the connection test
     *
     * @return array
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
     *
     * @param string $host
     * @param string $port
     * @param string $path
     * @param string $scheme
     * @param bool $expectsException
     * @param string $expectedConnectionString
     */
    public function canConnect($host, $port, $path, $scheme, $expectsException, $expectedConnectionString)
    {
        $self = $this;
        $this->connectionManager->expects(self::once())->method('getSolrConnectionForNodes')->willReturnCallback(
            function ($readNode, $writeNode) use ($self) {
                $readNode = Node::fromArray($readNode);
                $writeNode = Node::fromArray($writeNode);
                /* @var TypoScriptConfiguration $typoScriptConfigurationMock */
                $typoScriptConfigurationMock = $self->getDumbMock(TypoScriptConfiguration::class);
                /* @var SynonymParser $synonymsParserMock */
                $synonymsParserMock = $self->getDumbMock(SynonymParser::class);
                /* @var StopWordParser $stopWordParserMock */
                $stopWordParserMock = $self->getDumbMock(StopWordParser::class);
                /* @var SchemaParser $schemaParserMock */
                $schemaParserMock = $self->getDumbMock(SchemaParser::class);
                /* @var EventDispatcher $eventDispatcher */
                $eventDispatcher = $self->getDumbMock(EventDispatcher::class);

                return new SolrConnection(
                    $readNode,
                    $writeNode,
                    $typoScriptConfigurationMock,
                    $synonymsParserMock,
                    $stopWordParserMock,
                    $schemaParserMock,
                    $self->logManagerMock,
                    $this->getDumbMock(ClientInterface::class),
                    $this->getDumbMock(RequestFactoryInterface::class),
                    $this->getDumbMock(StreamFactoryInterface::class),
                    $this->getDumbMock(EventDispatcherInterface::class)
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
