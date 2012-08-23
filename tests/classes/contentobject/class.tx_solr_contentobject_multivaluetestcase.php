<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo.renner@dkd.de>
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
 * Tests for the SOLR_MULTIVALUE cObj.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_contentobject_MultivalueTestCase extends tx_phpunit_testcase {

	protected function setUp() {
		$GLOBALS['TSFE'] = new stdClass();

			// counter checked in cObjGetSingle()
		$GLOBALS['TSFE']->cObjectDepthCounter = 2;
	}

	protected function tearDown() {
		unset($GLOBALS['TSFE']);
	}

	/**
	 * @test
	 */
	public function convertsCommaSeparatedListFromRecordToSerializedArrayOfTrimmedValues() {
		$list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
		$expected = 'a:9:{i:0;s:3:"abc";i:1;s:3:"def";i:2;s:3:"ghi";i:3;s:3:"jkl";i:4;s:3:"mno";i:5;s:3:"pqr";i:6;s:3:"stu";i:7;s:3:"vwx";i:8;s:2:"yz";}';

		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start(array('list' => $list));

		$actual = $cObj->cObjGetSingle(
			tx_solr_contentobject_Multivalue::CONTENT_OBJECT_NAME,
			array(
				'field'     => 'list',
				'separator' => ','
			)
		);

		$this->assertEquals($expected, $actual);
	}

	/**
	 * @test
	 */
	public function convertsCommaSeparatedListFromValueToSerializedArrayOfTrimmedValues() {
		$list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
		$expected = 'a:9:{i:0;s:3:"abc";i:1;s:3:"def";i:2;s:3:"ghi";i:3;s:3:"jkl";i:4;s:3:"mno";i:5;s:3:"pqr";i:6;s:3:"stu";i:7;s:3:"vwx";i:8;s:2:"yz";}';

		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start(array());

		$actual = $cObj->cObjGetSingle(
			tx_solr_contentobject_Multivalue::CONTENT_OBJECT_NAME,
			array(
				'value'     => $list,
				'separator' => ','
			)
		);

		$this->assertEquals($expected, $actual);
	}

}


?>