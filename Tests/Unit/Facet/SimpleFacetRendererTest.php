<?php
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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 *
 */
class Tx_Solr_Facet_SimpleFacetRendererTest extends Tx_Phpunit_TestCase {

	protected $facetRenderer;

	public function setUp() {
		Util::initializeTsfe('1');

		$GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
		$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = '0';
		$GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';

		$facetName = 'TestFacet';
		$facetOptions = array('testoption'=> 1);
		$facetConfiguration = array(
			'selectingSelectedFacetOptionRemovesFilter' => 0,
			'renderingInstruction'
		);
		$parentPlugin       = GeneralUtility::makeInstance('Tx_Solr_PiResults_Results');
		$parentPlugin->cObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$parentPlugin->main('', array());
		$query = GeneralUtility::makeInstance('Tx_Solr_Query', array('test'));

		$this->facetRenderer = GeneralUtility::makeInstance(
			'Tx_Solr_Facet_SimpleFacetRenderer',
			$facetName,
			$facetOptions,
			$facetConfiguration,
			$parentPlugin->getTemplate(),
			$query
		);
		$this->facetRenderer->setLinkTargetPageId($parentPlugin->getLinkTargetPageId());
	}

	/**
	 * @test
	 */
	public function testRenderAFacete() {
		$expected = '<li class="">
		<a href="de/start/?tx_solr%5Bq%5D%5B0%5D=test&amp;tx_solr%5Bfilter%5D%5B0%5D=TestFacet%253Atestoption">testoption</a> 1
		</li>';
		$actual = $this->facetRenderer->render();

		$this->assertEquals($expected, $actual);
	}
}

