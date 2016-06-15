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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\ConnectionManager;

/**
 * PHP Unit test for connection manager
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
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
     * Set up the connection manager test
     *
     * @return void
     */
    public function setUp()
    {
        $TSFE = $this->getDumbMock('\\TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController');
        $GLOBALS['TSFE'] = $TSFE;

        /** @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getDumbMock('\\TYPO3\\CMS\\Core\\TypoScript\\TemplateService', array('linkData'));
        $GLOBALS['TSFE']->tmpl->init();
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
        $GLOBALS['TSFE']->tmpl->setup['config.']['typolinkEnableLinksAcrossDomains'] = 0;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['host'] = 'localhost';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['port'] = '8080';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['path'] = '/solr/core_en/';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['solr.']['scheme'] = 'http';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = 25;
        $GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';
        $GLOBALS['TSFE']->config['config'] = $this->tmpl->setup['config.'];

        $this->configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\ConfigurationManager');
        $this->connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');
    }

    /**
     * Provides data for the connect test
     *
     * @return array
     */
    public function connectDataProvider()
    {
        return array(
            array('host' => 'localhost', 'port' => '', 'path' => '', 'scheme' => '', 'expectsException' => true, 'expectedConnectionString' => null),
            array('host' => '', 'port' => '', 'path' => '', 'scheme' => '', 'expectsException' => false, 'expectedConnectionString' => 'http://localhost:8080/solr/core_en/'),
            array('host' => '127.0.0.1', 'port' => '8181', 'path' => '/solr/core_de/', 'scheme' => 'https', 'expectsException' => false, 'expectedConnectionString' => 'https://127.0.0.1:8181/solr/core_de/')
        );
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
     * @param boolean $expectsException
     * @param string $expectedConnectionString
     * @return void
     */
    public function canConnect($host, $port, $path, $scheme, $expectsException, $expectedConnectionString)
    {
        $exceptionOccured = false;
        try {
            $solrService = $this->connectionManager->getConnection($host, $port, $path, $scheme);
            $this->assertEquals($expectedConnectionString, $solrService->__toString());
        } catch (\UnexpectedValueException $exception) {
            $exceptionOccured = true;
        }
        $this->assertEquals($expectsException, $exceptionOccured);
    }
}
