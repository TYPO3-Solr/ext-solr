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
 * Query Filter Encoder Interface
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface tx_solr_QueryFilterEncoder {

	/**
	 * Takes a filter value and encodes it to a human readable format to be
	 * used in an URL GET parameter.
	 *
	 * @param string $filterValue the filter value
	 * @param array $configuration Facet configuration
	 * @return string Value to be used in a URL GET parameter
	 */
	public function encodeFilter($filterValue, array $configuration = array());

	/**
	 * Parses the query filter from GET parameters in the URL and translates it
	 * to a Lucene filter value.
	 *
	 * @param string $filterValue the filter query from plugin
	 * @param array $configuration Facet configuration
	 * @return string Value to be used in a Lucene filter
	 */
	public function decodeFilter($filterValue, array $configuration = array());
}

?>