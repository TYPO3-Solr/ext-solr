<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Daniel Poetzinger <poetzinger@aoemedia.de>
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
 * Processes a value that may appear as field value in documents
 *
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_fieldprocessor_PathToHierarchy implements tx_solr_FieldProcessor {

	/**
	 * Expects a value like "some/hierarchy/value"
	 *
	 * @param	array	Array of values, an array because of multivalued fields
	 * @return	array	Modified array of values
	 */
	public function process(array $values) {
		$results = array();

		foreach ($values as $value) {
			$results[] = $this->buildSolrHierarchyFromPath($value);
		}

		return $results;
	}

	/**
	 * Builds a Solr hierarchy from path string.
	 *
	 * @param string $path path string
	 * @return array Solr hierarchy
	 * @see http://wiki.apache.org/solr/HierarchicalFaceting
	 */
	protected function buildSolrHierarchyFromPath($path) {
		$hierarchy = array();

		$treeParts = t3lib_div::trimExplode('/', $path, TRUE);
		$currentTreeParts = array();

		foreach ($treeParts as $i => $part) {
			$currentTreeParts[] = $part;

			$hierarchy[] = $i . '-' . implode('/', $currentTreeParts);
		}

		return $hierarchy;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/fieldprocessor/class.tx_solr_fieldprocessor_pathtohierarchy.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/fieldprocessor/class.tx_solr_fieldprocessor_pathtohierarchy.php']);
}

?>