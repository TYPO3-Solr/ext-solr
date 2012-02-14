<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
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
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 *
 * Testcase for query parser range
 * @author Markus Goldbach
 */

class tx_solr_query_filterencoder_RangeTestCase extends tx_phpunit_testcase {

	private $rangeParser;

	public function setUp() {
		$this->rangeParser = t3lib_div::makeInstance('tx_solr_query_filterencoder_Range');
	}

	/**
	 * @test
	 */
	public function canParseRangeQuery() {
		$expected = '[firstValue TO secondValue]';
		$actual   = $this->rangeParser->parseFilter('firstValue-secondValue');

		$this->assertEquals($expected, $actual);
	}

}
?>