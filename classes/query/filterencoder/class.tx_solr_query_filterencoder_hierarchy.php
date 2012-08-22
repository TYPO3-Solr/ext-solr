<?php
/***************************************************************
*  Copyright notice
*
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
 * Filter encoder to build Solr hierarchy queries from tx_solr[filter]
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class tx_solr_query_filterencoder_Hierarchy implements tx_solr_QueryFilterEncoder {

	/**
	 * Delimiter for hierarchies in the URL.
	 *
	 * @var string
	 */
	const DELIMITER = '/';


	/**
	 * Takes a filter value and encodes it to a human readable format to be
	 * used in an URL GET parameter.
	 *
	 * @param string $filterValue the filter value
	 * @param array $configuration Facet configuration
	 * @return string Value to be used in a URL GET parameter
	 */
	public function encodeFilter($filterValue, array $configuration = array()) {
		list(, $hierarchyPath) = explode('-', $filterValue, 2);

		return '/' . $hierarchyPath;
	}

	/**
	 * Parses the given hierarchy filter and returns a Solr filter query.
	 *
	 * @param string $hierarchy The hierarchy filter query.
	 * @param array $configuration Facet configuration
	 * @return string Lucene query language filter to be used for querying Solr
	 */
	public function decodeFilter($hierarchy, array $configuration = array()) {
		$hierarchy      = substr($hierarchy, 1);
		$hierarchyItems = explode(self::DELIMITER, $hierarchy);

		$hierarchyFilter = '"' . (count($hierarchyItems) - 1) . '-' . $hierarchy . '"';

		return $hierarchyFilter;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/query/filterencoder/class.tx_solr_query_filterencoder_hierarchy.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/query/filterencoder/class.tx_solr_query_filterencoder_hierarchy.php']);
}

?>