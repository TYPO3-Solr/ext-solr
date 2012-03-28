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
 * There's a buggy PHP version in Ubuntu LTS 10.04 which causes filter_var to
 * produces incorrect results. This status checks for this issue.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_report_FilterVarStatus implements tx_reports_StatusProvider {

	/**
	 * Checks whether allow_url_fopen is enabled.
	 *
	 * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
	 */
	public function getStatus() {
		$reports  = array();

		$validUrl = 'http://www.typo3-solr.com';
		if (!filter_var($validUrl, FILTER_VALIDATE_URL)) {
			$message = 'You are using a PHP version that is affected by a bug in
				function filter_var(). This bug causes said function to
				incorrectly report valid URLs as invalid if they contain a
				dash (-). EXT:solr uses this function to validate URLs when
				indexing TYPO3 pages. Please check with your administrator
				whether a newer version can be installed.
				More information is available at
				<a href="https://bugs.php.net/bug.php?id=51192">php.net</a>.';

			$reports[] = t3lib_div::makeInstance('tx_reports_reports_status_Status',
				'PHP filter_var() bug',
				'Affected PHP version detected.',
				$message,
				tx_reports_reports_status_Status::ERROR
			);
		}

		return $reports;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_filtervarstatus.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/report/class.tx_solr_report_filtervarstatus.php']);
}

?>