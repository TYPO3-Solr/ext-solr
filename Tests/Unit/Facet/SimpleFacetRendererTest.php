<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Facet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Charset\CharsetConverter;


/**
 *
 */
class SimpleFacetRendererTest extends UnitTest
{

    /**
     * @var \ApacheSolrForTypo3\Solr\Facet\SimpleFacetRenderer
     */
    protected $facetRenderer;

    public function setUp()
    {
        $this->markTestSkipped('fixme');
        chdir(PATH_site);
        $GLOBALS['TYPO3_DB'] = $this->getMock('\TYPO3\CMS\Core\Database\DatabaseConnection', array());

        $TSFE = $this->getDumbMock('\\TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController');

        $GLOBALS['TSFE'] = $TSFE;
        $GLOBALS['TSFE']->config['config']['disablePrefixComment'] = true;

        $GLOBALS['TT'] = $this->getMock('\\TYPO3\\CMS\\Core\\TimeTracker\\TimeTracker', array(), array(), '', false);

        /** @var $GLOBALS ['TSFE']->tmpl  \TYPO3\CMS\Core\TypoScript\TemplateService */
        $GLOBALS['TSFE']->tmpl = $this->getMock('\\TYPO3\\CMS\\Core\\TypoScript\\TemplateService', array('linkData'));
        $GLOBALS['TSFE']->tmpl->init();
        $GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;

        $GLOBALS['TSFE']->csConvObj = new CharsetConverter();
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = '0';
        $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['templateFiles.']['results'] = 'EXT:solr/Resources/Templates/PiResults/results.htm';


        $facetName = 'TestFacet';
        $facetOptions = array('testoption' => 1);
        $facetConfiguration = array(
            'selectingSelectedFacetOptionRemovesFilter' => 0,
            'renderingInstruction'
        );
        $parentPlugin = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Plugin\\Results\\Results');
        $parentPlugin->cObj = $this->getMock(
            '\\TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer',
            array('getResourceFactory', 'getEnvironmentVariable'),
            array($TSFE)
        );

        $parentPlugin->main('', array());

        /** @var $query \ApacheSolrForTypo3\Solr\Query */
        $query = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query', 'test');

        /** @var $facet \ApacheSolrForTypo3\Solr\Facet\Facet */
        $facet = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Facet\\Facet', array($facetName));
        $this->facetRenderer = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Facet\\SimpleFacetRenderer',
            $facet
        );
        $this->facetRenderer->setLinkTargetPageId($parentPlugin->getLinkTargetPageId());
    }

    /**
     * @test
     */
    public function testRenderAFacete()
    {
        $expected = '<li class="">
		<a href="de/start/?tx_solr%5Bq%5D%5B0%5D=test&amp;tx_solr%5Bfilter%5D%5B0%5D=TestFacet%253Atestoption">testoption</a> 1
		</li>';
        $actual = $this->facetRenderer->render();

        $this->assertEquals($expected, $actual);
    }
}

