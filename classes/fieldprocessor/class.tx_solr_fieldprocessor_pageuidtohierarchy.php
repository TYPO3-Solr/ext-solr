<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Michael Knoll <knoll@punkt.de>
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
 * This Processor takes a PID, and resolves its rootline in solr notation.
 *
 * Format of this field corresponds to http://wiki.apache.org/solr/HierarchicalFaceting
 *
 * Let's say we have a record indexed on page 111 which is a sub page like shown in this page tree:
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
 * @author Michael Knoll <knoll@punkt.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_fieldprocessor_PageUidToHierarchy implements tx_solr_FieldProcessor {

	/**
	 * Expects a page ID of a page. Returns a Solr hierarchy notation for the
	 * rootline of the page ID.
	 *
	 * @param	array	Array of values, an array because of multivalued fields
	 * @return	array	Modified array of values
	 */
	public function process(array $values) {
		$results = array();

		foreach ($values as $value) {
			$results[] = $this->getSolrRootlineForPageId($value);
		}

		return $results;
	}

	/**
	 * Returns a Solr hierarchy notation string for rootline of given PID.
	 *
	 * @param integer $pageId Page ID to get a rootline as Solr hierarchy for
	 * @return string Rootline as Solr hierarchy
	 */
	protected function getSolrRootlineForPageId($pageId) {
		$pageIdRootline = $this->buildPageIdRootline($pageId);
		$solrRootline   = $this->buildSolrHierarchyFromPageIdRootline($pageIdRootline);

		return $solrRootline;
	}

	/**
	 * Builds a page's rootline of parent page Ids
	 *
	 * @param integer $pageId The page Id to build the rootline for
	 * @return array Page Id rootline as array
	 */
	protected function buildPageIdRootline($pageId) {
		$rootlinePageIds = array();

		$pageSelector = t3lib_div::makeInstance('t3lib_pageSelect');
		$rootline = $pageSelector->getRootLine($pageId);

		foreach($rootline as $page) {
			if ($page['is_siteroot']) {
				break;
			}

			array_unshift($rootlinePageIds, $page['pid']);
		}

		$rootlinePageIds[] = $pageId;

		return $rootlinePageIds;
	}

	/**
	 * Builds a Solr hierarchy from an array of page Ids that make up a page's
	 * rootline.
	 *
	 * @param array $pageIdRootline array of page Ids representing a page's rootline
	 * @return array Solr hierarchy
	 * @see http://wiki.apache.org/solr/HierarchicalFaceting
	 */
	protected function buildSolrHierarchyFromPageIdRootline(array $pageIdRootline) {
		$hierarchy = array();

		$depth       = 0;
		$currentPath = array_shift($pageIdRootline);
		foreach($pageIdRootline as $pageId) {
			$hierarchy[] = $depth . '-' . $currentPath;

			$depth++;
			$currentPath .= '/' . $pageId;
		}
		$hierarchy[] = $depth . '-' . $currentPath;

		return $hierarchy;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/fieldprocessor/class.tx_solr_fieldprocessor_pageuidohierarchy.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/fieldprocessor/class.tx_solr_fieldprocessor_pageuidtohierarchy.php']);
}

?>