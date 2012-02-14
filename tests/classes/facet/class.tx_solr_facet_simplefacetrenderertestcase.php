<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
*  (c) 2012 Ingo Renner <ingo@typo3.org>
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


/**
 *
 */
class tx_solr_facet_SimpleFacetRendererTestCase extends tx_phpunit_testcase {

	protected $facetRenderer;

	public function setUp() {
		tx_solr_Util::initializeTsfe('1');

		$GLOBALS['TSFE']->tmpl->getFileName_backPath = PATH_site;
		$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage'] = '0';
		$GLOBALS['TSFE']->tmpl->setup['config.']['tx_realurl_enable'] = '0';

		$facetName = 'TestFacet';
		$facetOptions = array('testoption'=> 1);
		$facetConfiguration = array(
			'selectingSelectedFacetOptionRemovesFilter' => 0,
			'renderingInstruction'
		);
		$parentPlugin       = t3lib_div::makeInstance('tx_solr_pi_results');
		$parentPlugin->cObj = t3lib_div::makeInstance('tslib_cObj');
		$parentPlugin->main('', array());
		$query = t3lib_div::makeInstance('tx_solr_Query', array('test'));

		$this->facetRenderer = t3lib_div::makeInstance(
			'tx_solr_facet_SimpleFacetRenderer',
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

?>