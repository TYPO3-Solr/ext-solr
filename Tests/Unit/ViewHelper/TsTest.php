<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\ViewHelper\Ts;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * PHP Unit test for view helper Tx_Solr_viewhelper_Ts
 *
 * @author Timo Webler <timo.webler@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class TsTest extends UnitTest
{

    /**
     * @var Ts
     */
    protected $viewHelper = null;

    /**
     * @var array
     */
    protected $fixtures = array();

    public function setUp()
    {
        $this->skipInVersionBelow('7.6');
        $TSFE = $this->getDumbMock('\\TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController');

        $GLOBALS['TSFE'] = $TSFE;
        /** @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getMockBuilder(TemplateService::class)
            ->setMethods(['linkData'])
            ->getMock();
        $GLOBALS['TSFE']->tmpl->init();
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = '0';
        $GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';


        // setup up ts objects
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['detailPage'] = 5050;
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['renderObjects.'] = array(
            'testContent' => 'TEXT',
            'testContent.' => array(
                'field' => 'argument_0'
            ),
            'testContent2' => 'TEXT',
            'testContent2.' => array(
                'field' => 'argument_1',
                'stripHtml' => 1
            )
        );

        $this->fixtures = array(
            'argument content',
            '<span>argument content with html</span>',
            'third argument content'
        );


        $cObj = $this->getMockBuilder(ContentObjectRenderer::class)
            ->setMethods(['getResourceFactory', 'getEnvironmentVariable'])
            ->setConstructorArgs([$TSFE])
            ->getMock();

        $cObj->setContentObjectClassMap(array(
            'TEXT' => 'TYPO3\\CMS\\Frontend\\ContentObject\\TextContentObject'
        ));
        /** @var \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\ConfigurationManager');
        $configurationManager->reset();

        $this->viewHelper = new Ts();
        $this->viewHelper->setContentObject($cObj);
    }

    /**
     * @test
     */
    public function getTypoScriptPathWithoutCObject()
    {
        $path = 'plugin.tx_solr.search.detailPage';
        $arguments = $this->fixtures;
        array_unshift($arguments, $path);
        $expected = 5050;

        $actual = $this->viewHelper->execute($arguments);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function getTypoScriptPathAsCObject()
    {
        $path = 'plugin.tx_solr.renderObjects.testContent';
        $arguments = $this->fixtures;
        array_unshift($arguments, $path);
        $expected = 'argument content';

        $actual = $this->viewHelper->execute($arguments);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function getTypoScriptPathAsCObjectWithMoreThanOneArgument()
    {
        $path = 'plugin.tx_solr.renderObjects.testContent2';
        $arguments = $this->fixtures;
        array_unshift($arguments, $path);
        $expected = 'argument content with html';

        $actual = $this->viewHelper->execute($arguments);

        $this->assertEquals($expected, $actual);
    }
}
