<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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
 * A field processor that converts timestamps to ISO dates as needed by Solr
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_FieldProcessor_TimestampToIsoDate implements Tx_Solr_FieldProcessor {

	/**
	 * Expects a timestamp and converts it to an ISO 8601 date as needed by Solr.
	 *
	 * Example date output format: 1995-12-31T23:59:59Z
	 * The trailing "Z" designates UTC time and is mandatory
	 *
	 * @param array Array of values, an array because of multivalued fields
	 * @return array Modified array of values
	 */
	public function process(array $values) {
		$results = array();

		foreach ($values as $timestamp) {
			$results[] = Tx_Solr_Util::timestampToIso($timestamp);
		}

		return $results;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/FieldProcessor/TimestampToIsoDate.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/FieldProcessor/TimestampToIsoDate.php']);
}

?>