<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2013 Steffen Ritter <steffen.ritter@typo3.org>
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
 * This Processor takes a UID of sys_category, and resolves its rootline in solr notation.
 *
 * Format of this field corresponds to http://wiki.apache.org/solr/HierarchicalFaceting
 *
 * Let's say we have a category with uid 111 which is a sub category like shown in this tree:
 *
 * 1
 * |-10
 *   |-100
 *     |-111
 *
 * then we get a rootline 1/10/100/111
 *
 * In Solr hierarchy notation, we get
 *
 * 0-1
 * 1-1/10
 * 2-1/10/100
 * 3-1/10/100/11
 *
 * which is finally saved in a multi-value field.
 *
 * @author Steffen Ritter <steffen.ritter@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_FieldProcessor_CategoryUidToHierarchy extends AbstractHierarchyProcessor implements Tx_Solr_FieldProcessor {

	/**
	 * Expects a uid ID of a category. Returns a Solr hierarchy notation for the
	 * rootline of the category ID.
	 *
	 * @param array $values Array of values, an array because of multivalued fields
	 * @return array Modified array of values
	 */
	public function process(array $values) {
		$results = array();

		foreach ($values as $value) {
			$results = array_merge($results, $this->getSolrRootlineForCategoryId($value));
		}

		return $results;
	}

	/**
	 * Returns a Solr hierarchy notation string for rootline of given category uid.
	 *
	 * @param integer $categoryId Category ID to get a rootline as Solr hierarchy for
	 * @return string Rootline as Solr hierarchy
	 */
	protected function getSolrRootlineForCategoryId($categoryId) {
		$categoryIdRootline = $this->buildCategoryIdRootline($categoryId);
		$solrRootline       = $this->buildSolrHierarchyFromIdRootline($categoryIdRootline);

		return $solrRootline;
	}

	/**
	 * Builds a category's rootline of parent category Ids
	 *
	 * @param integer $uid The category ID to build the rootline for
	 * @return array Category ID rootline as array
	 */
	protected function buildCategoryIdRootline($uid) {
		$rootlineIds    = array();
		$parentCategory = intval($uid);

		while ($parentCategory !== 0) {
			$rootlineIds[] = $parentCategory;
			$row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
				'parent',
				'sys_category',
				'uid = ' . intval($parentCategory)
			);

			if ($row === NULL) {
				$parentCategory = 0;
			} else {
				$parentCategory = intval($row['parent']);
			}
		}
		krsort($rootlineIds);

		return array_values($rootlineIds);
	}

}

?>