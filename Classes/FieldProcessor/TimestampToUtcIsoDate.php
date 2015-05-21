<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Andreas Allacher <andreas.allacher@cyberhouse.at>
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
 * @author	Andreas Allacher <andreas.allacher@cyberhouse.at>
 * @package	TYPO3
 * @subpackage	solr
 */
class Tx_Solr_FieldProcessor_TimestampToUtcIsoDate implements Tx_Solr_FieldProcessor {

	/**
	 * Expects a timestamp and converts it to an ISO 8601 date in UTC as needed by Solr.
	 *
	 * Example date output format: 1995-12-31T23:59:59Z
	 * The trailing "Z" designates UTC time and is mandatory
	 *
	 * @param	array	Array of values, an array because of multivalued fields
	 * @return	array	Modified array of values
	 */
	public function process(array $values) {
		$results = array();

		foreach ($values as $timestamp) {
			$results[] = Tx_Solr_Util::timestampToUtcIso($timestamp);
		}

		return $results;
	}
}