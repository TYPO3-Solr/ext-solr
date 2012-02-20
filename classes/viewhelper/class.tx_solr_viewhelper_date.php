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
 * viewhelper class to format unix timestamps as date
 * Replaces viewhelpers ###DATE:timestamp###
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_viewhelper_Date implements tx_solr_ViewHelper {

	protected $dateFormat = NULL;

	/**
	 * instance of tslib_cObj
	 *
	 * @var tslib_cObj
	 */
	protected $contentObject = NULL;

	/**
	 * constructor for class tx_solr_viewhelper_Date
	 */
	public function __construct(array $arguments = array()) {
		if(is_null($this->dateFormat) || is_null($this->contentObject)) {
			$this->dateFormat = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['general.']['dateFormat.'];
			$this->contentObject = t3lib_div::makeInstance('tslib_cObj');
		}
	}

	/**
	 * Converts a given unix timestamp to a human readble date
	 *
	 * @param array $arguments
	 * @return	string
	 */
	public function execute(array $arguments = array()) {
		$content = '';

		if (count($arguments) > 1) {
			$this->dateFormat = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['general.']['dateFormat.'];
			$this->dateFormat['date'] = $arguments[1];
		}

		if (is_numeric($arguments[0])) {
			$content = $this->contentObject->stdWrap($arguments[0], $this->dateFormat);
		}

		return $content;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_date.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_date.php']);
}

?>