<?php
namespace ApacheSolrForTypo3\Solr\Tests\Functional;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Core\FormProtection\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TestCase to check if the template parsing works as expected
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
class TemplateTest extends FunctionalTest
{

    public function setUp()
    {
        parent::setUp();
        chdir(PATH_site);

        $GLOBALS['TYPO3_CONF_VARS']['FE']['addAllowedPaths'] = $this->getFixturePath();

        $TT = $this->getMock('\TYPO3\CMS\Core\TimeTracker\TimeTracker', array(), array(), '', false);
        $TT->expects($this->any())->method('setTSlogMessage')->will($this->returnCallback(function ($message) {
            echo $message;
        }));
        $GLOBALS['TT'] = $TT;

        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController', array(), 1,
            0);
        $GLOBALS['TSFE'] = $TSFE;

        /** @var $TMPL \TYPO3\CMS\Core\TypoScript\TemplateService */
        $TMPL = GeneralUtility::makeInstance('TYPO3\CMS\Core\TypoScript\TemplateService');
        $TMPL->init();
        $GLOBALS['TSFE']->tmpl = $TMPL;
        $GLOBALS['TSFE']->renderCharset = 'utf-8';
        $GLOBALS['TSFE']->csConvObj = GeneralUtility::makeInstance('TYPO3\CMS\Core\Charset\CharsetConverter');
    }

    /**
     * @test
     */
    public function canRenderSimpleTemplate()
    {
        /** @var $cObj \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer */
        $cObj = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer');
        $testTemplatePath = 'EXT:solr/Tests/Functional/Fixtures/test_template.html';

        /** @var $template \ApacheSolrForTypo3\Solr\Template */
        $template = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Template', $cObj, $testTemplatePath,
            'SOLR_TEST');
        $template->addViewHelperIncludePath('solr', 'Classes/ViewHelper/');
        $template->addViewHelper('Crop');
        $result = $template->render(true);

        $this->assertEquals('<div class="tx-solr">hallo.</div>', trim($result), 'Could not render simple template!');
    }
}