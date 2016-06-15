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
use ApacheSolrForTypo3\Solr\Typo3Environment;

/**
 * PHP Unit test for TYPO3 environment information
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class Typo3EnvironmentTest extends UnitTest
{
    /**
     * Configuration manager
     *
     * @var \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager $configurationManager
     */
    protected $configurationManager;

    /**
     * TYPO3 environment information
     *
     * @var Typo3Environment
     */
    protected $typo3Environment;

    /**
     * Set up the TYPO3 environment test
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
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = 25;
        $GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';
        $GLOBALS['TSFE']->config['config'] = $this->tmpl->setup['config.'];

        $this->configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\ConfigurationManager');
        $this->typo3Environment = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Typo3Environment');
    }

    /**
     * Sets the solrfile indexing status
     *
     * @param string $glue
     * @return void
     */
    protected function setSolrfileIndexingStatus($status)
    {
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['files'] = $status;
        $this->configurationManager->reset();
    }

    /**
     * Provides data for the solrfile file indexing status test
     *
     * @return array
     */
    public function solrfileIndexingStatusCheckDataProvider()
    {
        return array(
            array('status' => 1, 'expectedResult' => true),
            array('status' => 0, 'expectedResult' => false),
            array('status' => null, 'expectedResult' => false)
        );
    }

    /**
     * Tests the check of the solrfile file indexing status
     *
     * @dataProvider solrfileIndexingStatusCheckDataProvider
     * @test
     *
     * @param string $status
     * @param mixed $expectedResult
     * @return void
     */
    public function canCheckSolrfileIndexingStatus($status, $expectedResult)
    {
        $this->setSolrfileIndexingStatus($status);
        $isFileIndexingEnabled = $this->typo3Environment->isFileIndexingEnabled();
        $this->assertEquals($expectedResult, $isFileIndexingEnabled);
    }
}
