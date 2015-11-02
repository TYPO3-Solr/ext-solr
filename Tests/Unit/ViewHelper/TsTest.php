<?php
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

use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\ViewHelper\Ts;


/**
 * PHP Unit test for view helper Tx_Solr_viewhelper_Ts
 *
 * @author Timo Webler <timo.webler@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_ViewHelper_TsTest extends \Tx_Phpunit_TestCase
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
        Util::initializeTsfe('1');

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

        $this->viewHelper = new Ts();
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
