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
 * Creates a graphical representation of the current sorting direction by
 * expanding a ###SORT_INDICATOR:sortDirection### marker.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_viewhelper_SortIndicator implements tx_solr_ViewHelper {

	/**
	 * constructor for class tx_solr_viewhelper_SortIndicator
	 */
	public function __construct(array $arguments = array()) {

	}

	/**
	 * Returns an URL that switches sorting to the given sorting field
	 *
	 * @param array $arguments
	 * @return	string
	 */
	public function execute(array $arguments = array()) {
		$content = '';
		$sortDirection = trim($arguments[0]);

		$contentObject = t3lib_div::makeInstance('tslib_cObj');
		$imageConfiguration = array(
			'file' => 'EXT:solr/resources/images/indicator-'
		);

		switch ($sortDirection) {
			case 'asc':
				$imageConfiguration['file'] .= 'up.png';
				$content = $contentObject->IMAGE($imageConfiguration);
				break;
			case 'desc':
				$imageConfiguration['file'] .= 'down.png';
				$content = $contentObject->IMAGE($imageConfiguration);
				break;
		}

		return $content;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_sortindicator.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_sortindicator.php']);
}

?>