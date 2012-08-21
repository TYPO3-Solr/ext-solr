<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2012 Ingo Renner <ingo@typo3.org>
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
 * Index Queue Page Indexer frontend helper to track which user groups are used
 * on a page.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_frontendhelper_UserGroupDetector

	extends
		tx_solr_indexqueue_frontendhelper_Abstract

	implements
		t3lib_Singleton,
		tslib_content_PostInitHook,
		t3lib_pageSelect_getPageHook,
		t3lib_pageSelect_getPageOverlayHook {



	/**
	 * This frontend helper's executed action.
	 */
	protected $action = 'findUserGroups';

	/**
	 * Holds the original, unmodified TCA during user group detection
	 *
	 * @var	array
	 */
	protected $originalTca = NULL;

	/**
	 * Collects the usergroups used on a page.
	 *
	 * @var	array
	 */
	protected $frontendGroups = array();

	// activation

	/**
	 * Activates a frontend helper by registering for hooks and other
	 * resources required by the frontend helper to work.
	 */
	public function activate() {
			// regsiter hooks
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['isOutputting'][__CLASS__]        = '&tx_solr_indexqueue_frontendhelper_UserGroupDetector->disableFrontendOutput';
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['tslib_fe-PostProc'][__CLASS__]   = '&tx_solr_indexqueue_frontendhelper_UserGroupDetector->disableCaching';
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['configArrayPostProc'][__CLASS__] = '&tx_solr_indexqueue_frontendhelper_UserGroupDetector->deactivateTcaFrontendGroupEnableFields';

		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPage'][__CLASS__]           = '&tx_solr_indexqueue_frontendhelper_UserGroupDetector';
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'][__CLASS__]    = '&tx_solr_indexqueue_frontendhelper_UserGroupDetector';

		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit'][__CLASS__]       = '&tx_solr_indexqueue_frontendhelper_UserGroupDetector';
	}

	/**
	 * Deactivates the frontend user grroup fields in TCA so that no access
	 * restrictions apply during page rendering.
	 *
	 * @param	array	Parameters from frontend
	 * @param	tslib_fe	TSFE object
	 */
	public function deactivateTcaFrontendGroupEnableFields(&$parameters, $parentObject) {
		$this->originalTca = $GLOBALS['TCA'];

		foreach ($GLOBALS['TCA'] as $tableName => $tableConfiguration) {
			if (isset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group'])) {
				unset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group']);
			}
		}
	}

	// manipulation

	/**
	 * Modifies the database query parameters so that access checks for pages
	 * are not performed any longer.
	 *
	 * @param	integer	The page ID
	 * @param	boolean	If set, the check for group access is disabled. VERY rarely used
	 * @param	t3lib_pageSelect	parent t3lib_pageSelect object
	 */
	public function getPage_preProcess(&$uid, &$disableGroupAccessCheck, t3lib_pageSelect $parentObject) {
		$disableGroupAccessCheck = TRUE;
		$parentObject->where_groupAccess = ''; // just to be on the safe side
	}

	/**
	 * Modifies page records so that when checking for access through fe groups
	 * no groups or extendToSubpages flag is found and thus access is granted.
	 *
	 * @param	array	Page record
	 * @param	integer	Overlay language ID
	 * @param	t3lib_pageSelect	Parent t3lib_pageSelect object
	 */
	public function getPageOverlay_preProcess(&$pageRecord, &$languageUid, t3lib_pageSelect $parentObject) {
		if (is_array($pageRecord)) {
			$pageRecord['fe_group'] = '';
			$pageRecord['extendToSubpages'] = '0';
		}
	}

	// execution

	/**
	 * Hook for post processing the initialization of tslib_cObj
	 *
	 * @param	tslib_cObj	parent content object
	 */
	public function postProcessContentObjectInitialization(tslib_cObj &$parentObject) {
		if (!empty($parentObject->currentRecord)) {
			list($table) = explode(':', $parentObject->currentRecord);

			if (!empty($table) && $table != 'pages') {
				$this->findFrontendGroups($parentObject->data, $table);
			}
		}
	}

	/**
	 * Tracks user groups access restriction applied to records.
	 *
	 * @param	array	A record as an array of fieldname => fieldvalue mappings
	 * @param	string	Table name the record belongs to
	 */
	protected function findFrontendGroups($record, $table) {
		if ($this->originalTca[$table]['ctrl']['enablecolumns']['fe_group']) {
			$frontendGroups = $record[$this->originalTca[$table]['ctrl']['enablecolumns']['fe_group']];

			if (empty($frontendGroups)) {
					// default = public access
				$frontendGroups = 0;
			} else {
				if ($this->request->getParameter('loggingEnabled')) {
					t3lib_div::devLog('Access restriction found', 'solr', 0, array(
						'groups'      => $frontendGroups,
						'record'      => $record,
						'record type' => $table,
					));
				}
			}

			$this->frontendGroups[] = $frontendGroups;
		}
	}

	/**
	 * Returns an array of user groups that have been tracked during page
	 * rendering.
	 *
	 * @return	array	Array of user group IDs
	 */
	protected function getFrontendGroups() {
		$frontendGroupsList = implode(',', $this->frontendGroups);
		$frontendGroups     = t3lib_div::trimExplode(',', $frontendGroupsList, TRUE);

			// clean up: filter double groups
		$frontendGroups = array_unique($frontendGroups);
		$frontendGroups = array_values($frontendGroups);

		if (empty($frontendGroups)) {
				// most likely an empty page with no content elements => public
			$frontendGroups[] = '0';
		}

		return $frontendGroups;
	}

	/**
	 * Returns the user groups found.
	 *
	 * @return	array	Array of user groups.
	 */
	public function getData() {
		return $this->getFrontendGroups();
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_usergroupdetector.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_usergroupdetector.php']);
}

?>