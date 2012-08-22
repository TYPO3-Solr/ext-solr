<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * tests the path to hierarchy processing
 *
 * @author 	Daniel Pötzinger <poetzinger@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_fieldprocessor_PathToHierarchyTestCase extends tx_phpunit_testcase {

	private $processor;

	public function setUp() {
		$this->processor = new tx_solr_fieldprocessor_PathToHierarchy();
	}

	/**
	* @test
	*/
	public function canBuildSolrHierarchyString() {
		$this->assertEquals($this->processor->process(array('sport/cricket')),array('0-sport', '1-sport/cricket'));
		$this->assertEquals($this->processor->process(array('sport/skateboarding')),array('0-sport', '1-sport/skateboarding'));
		$this->assertEquals($this->processor->process(array('sport/skateboarding/street')),array('0-sport', '1-sport/skateboarding', '2-sport/skateboarding/street'));
		$this->assertEquals($this->processor->process(array('/sport/skateboarding/street//')),array('0-sport', '1-sport/skateboarding', '2-sport/skateboarding/street'));
	}
}

?>