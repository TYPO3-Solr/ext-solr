<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Ingo Renner <ingo@typo3.org>
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
 * Tests the Query class
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_QueryTestCase extends tx_phpunit_testcase {

	/**
	 * @test
	 */
	public function noFiltersAreSetAfterInitialization() {
		$query = t3lib_div::makeInstance('tx_solr_Query', 'test');
		$filters = $query->getFilters();

		$this->assertTrue(
			empty($filters),
			'Query already contains filters after intialization.'
		);
	}

	/**
	 * @test
	 */
	public function addsCorrectAccessFilterForAnonymousUser() {
		$query = t3lib_div::makeInstance('tx_solr_Query', 'test');
		$query->setUserAccessGroups(array(-1, 0));
		$filters = $query->getFilters();

		$this->assertContains(
			'{!typo3access}-1,0',
			$filters,
			'Access filter not found in [' . implode('], [', $filters) . ']'
		);
	}

	/**
	 * @test
	 */
	public function grantsAccessToGroupZeroIfNoGroupsProvided() {
		$query = t3lib_div::makeInstance('tx_solr_Query', 'test');
		$query->setUserAccessGroups(array());
		$filters = $query->getFilters();

		$this->assertContains(
			'{!typo3access}0',
			$filters,
			'Access filter not found in [' . implode('], [', $filters) . ']'
		);
	}

	/**
	 * @test
	 */
	public function grantsAccessToGroupZeroIfZeroNotProvided() {
		$query = t3lib_div::makeInstance('tx_solr_Query', 'test');
		$query->setUserAccessGroups(array(5));
		$filters = $query->getFilters();

		$this->assertContains(
			'{!typo3access}0,5',
			$filters,
			'Access filter not found in [' . implode('], [', $filters) . ']'
		);
	}

	/**
	 * @test
	 */
	public function filtersDuplicateAccessGroups() {
		$query = t3lib_div::makeInstance('tx_solr_Query', 'test');
		$query->setUserAccessGroups(array(1,1));
		$filters = $query->getFilters();

		$this->assertContains(
			'{!typo3access}0,1',
			$filters,
			'Access filter not found in [' . implode('], [', $filters) . ']'
		);
	}

	/**
	 * @test
	 */
	public function allowsOnlyOneAccessFilter() {
		$query = t3lib_div::makeInstance('tx_solr_Query', 'test');
		$query->setUserAccessGroups(array(1));
		$query->setUserAccessGroups(array(2));
		$filters = $query->getFilters();

		$this->assertSame(
			count($filters),
			1,
			'Too many filters in [' . implode('], [', $filters) . ']'
		);
	}

	// TODO if user is in group -2 (logged in), disallow access to group -1
}

?>