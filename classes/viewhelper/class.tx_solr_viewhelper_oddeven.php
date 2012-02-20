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
 * Odd / Even viewhelper to add Zebra stripes to table rows f.e.
 * Replaces viewhelpers ###ODD_EVEN:number###
 *
 * To create alternating rows use this in your results loop:
 * class="row-###ODD_EVEN:###LOOP_CURRENT_ITERATION_COUNT######"
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_viewhelper_OddEven implements tx_solr_ViewHelper {

	/**
	 * constructor for class tx_solr_viewhelper_OddEven
	 */
	public function __construct(array $arguments = array()) {

	}

	/**
	 * returns either "odd" or "even" depending on the current row's number
	 *
	 * @param array $arguments
	 * @return	string
	 */
	public function execute(array $arguments = array()) {
		$oddEven = 'even';

		if ($arguments[0] % 2) {
			$oddEven = 'odd';
		}


		return $oddEven;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_oddeven.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_oddeven.php']);
}

?>