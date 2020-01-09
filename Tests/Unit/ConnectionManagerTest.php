<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
     * Set up the connection manager test
     *
     * @return void
     */
    public function setUp()
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
            ->setConstructorArgs([$this->languageRepositoryMock, $this->pageRepositoryMock, $this->siteRepositoryMock])
            ->setMethods(['getSolrConnectionForNodes'])
            ->getMock();
    }

    /**
     * Provides data for the connect test
     *
     * @return array
     */
    public function connectDataProvider()
    {
        return [
            ['host' => 'localhost', 'port' => '', 'path' => '', 'scheme' => '', 'expectsException' => true, 'expectedConnectionString' => null],
            ['host' => '127.0.0.1', 'port' => 8181, 'path' => '/solr/core_de/', 'scheme' => 'https', 'expectsException' => false, 'expectedConnectionString' => 'https://127.0.0.1:8181/solr/core_de/']
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
     * @return void
     */
    public function canConnect($host, $port, $path, $scheme, $expectsException, $expectedConnectionString)
    {
        $self = $this;
        $this->connectionManager->expects($this->once())->method('getSolrConnectionForNodes')->will(
            $this->returnCallback(function($readNode, $writeNode) use ($self) {

                $readNode = Node::fromArray($readNode);
                $writeNode = Node::fromArray($writeNode);
                $typoScriptConfigurationMock = $self->getDumbMock(TypoScriptConfiguration::class);
                $synonymsParserMock = $self->getDumbMock(SynonymParser::class);
                $stopWordParserMock = $self->getDumbMock(StopWordParser::class);
                $schemaParserMock = $self->getDumbMock(SchemaParser::class);

                return new SolrConnection($readNode, $writeNode, $typoScriptConfigurationMock, $synonymsParserMock, $stopWordParserMock, $schemaParserMock, $self->logManagerMock);
            })
        );
        $exceptionOccured = false;
        try {
            $readNode = ['host' => $host, 'port' => $port, 'path' => $path, 'scheme' => $scheme];
            $configuration['read'] = $readNode;
            $configuration['write'] = $readNode;

            $solrService = $this->connectionManager->getConnectionFromConfiguration($configuration);
            $this->assertEquals($expectedConnectionString, $solrService->getReadService()->__toString());
        } catch (\UnexpectedValueException $exception) {
            $exceptionOccured = true;
        }
        $this->assertEquals($expectsException, $exceptionOccured);
    }
}
