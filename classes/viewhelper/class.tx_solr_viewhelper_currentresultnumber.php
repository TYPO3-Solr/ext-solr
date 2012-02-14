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
 * Viewhelper class to display the current result number in the results loop.
 * Replaces viewhelpers ###CURRENT_RESULT_NUMBER:xxx###
 *
 * Example: ###CURRENT_RESULT_NUMBER:###LOOP_CURRENT_ITERATION_COUNT######
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_viewhelper_CurrentResultNumber implements tx_solr_ViewHelper {

	/**
	 * @var tx_solr_Search
	 */
	protected $search;

	protected $configuration;

	/**
	 * constructor for class tx_solr_viewhelper_Date
	 */
	public function __construct(array $arguments = array()) {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');
	}

	/**
	 * Resolves the current iteration index (relative) of a loop to the absolute
	 * number counting from zero of the total number of results.
	 *
	 * @param array $arguments
	 * @return	string
	 */
	public function execute(array $arguments = array()) {
		$numberOfResults = $this->search->getNumberOfResults();
		$currentIterationIndex = $arguments[0];
		$resultsPerPage = $this->search->getResultsPerPage();
		$currentPage = 0;
		$getParameters = t3lib_div::_GET('tx_solr');

		if (isset($getParameters['page'])) {
			$currentPage = intval($getParameters['page']);
		}

		return ($currentPage * $resultsPerPage) + $currentIterationIndex;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_currentresultnumber.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_currentresultnumber.php']);
}

?>