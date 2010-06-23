<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2010 Ingo Renner <ingo@typo3.org>
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
 * viewhelper class to create links containing solr parameters
 * Replaces viewhelpers ###LINK:LinkText|Pid|AdditionalParameters|useCache###
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_viewhelper_Link implements tx_solr_ViewHelper {

	/**
	 * instance of tslib_cObj
	 *
	 * @var tslib_cObj
	 */
	protected $contentObject = null;

	/**
	 * constructor for class tx_solr_viewhelper_Date
	 */
	public function __construct(array $arguments = array()) {
		if(is_null($this->contentObject)) {
			$this->contentObject = t3lib_div::makeInstance('tslib_cObj');
		}
	}

	/**
	 * Creates a link to a given page with a given link text
	 *
	 * @param	array	Array of arguments, [0] is the link text, [1] is the (optional) page Id to link to (otherwise TSFE->id), [2] are additional URL parameters, [3] use cache, defaults to false
	 * @return	string	complete anchor tag with URL and link text
	 */
	public function execute(array $arguments = array()) {
		$linkText             = $arguments[0];
		$pageId               = $arguments[1] ? intval($arguments[1]) : $GLOBALS['TSFE']->id;
		$additionalParameters = $arguments[2] ? $arguments[2] : '';
		$useCache             = $arguments[3] ? true : false;

		$linkConfiguration = array(
			'useCacheHash'     => $useCache,
			'no_cache'         => false,
			'parameter'        => $pageId,
			'additionalParams' => $additionalParameters
		);

		return $this->contentObject->typoLink($linkText, $linkConfiguration);
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_link.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_link.php']);
}

?>