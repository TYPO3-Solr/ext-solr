<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Ingo Renner <ingo@typo3.org>
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
 * XCLASS for t3lib_pageSelect to add a TYPO3 4.4 hook in 4.3 installations
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class ux_t3lib_pageSelect extends t3lib_pageSelect {

	/**
	 * Returns the relevant page overlay record fields
	 *
	 * @param	mixed		If $pageInput is an integer, it's the pid of the pageOverlay record and thus the page overlay record is returned. If $pageInput is an array, it's a page-record and based on this page record the language record is found and OVERLAYED before the page record is returned.
	 * @param	integer		Language UID if you want to set an alternative value to $this->sys_language_uid which is default. Should be >=0
	 * @return	array		Page row which is overlayed with language_overlay record (or the overlay record alone)
	 */
	public function getPageOverlay($pageInput,$lUid=-1)	{

			// Initialize:
		if ($lUid<0)	$lUid = $this->sys_language_uid;
		$row = NULL;

### backported hook start ###
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_page.php']['getPageOverlay'] as $classRef) {
				$hookObject = t3lib_div::getUserObj($classRef);

				if (!($hookObject instanceof t3lib_pageSelect_getPageOverlayHook)) {
					throw new UnexpectedValueException('$hookObject must implement interface t3lib_pageSelect_getPageOverlayHook', 1269878881);
				}

				$hookObject->getPageOverlay_preProcess($pageInput, $lUid, $this);
			}
		}
### backported hook end ###

			// If language UID is different from zero, do overlay:
		if ($lUid)	{
			$fieldArr = explode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields']);
			if (is_array($pageInput))	{
				$page_id = $pageInput['uid'];	// Was the whole record
				$fieldArr = array_intersect($fieldArr,array_keys($pageInput));		// Make sure that only fields which exist in the incoming record are overlaid!
			} else {
				$page_id = $pageInput;	// Was the id
			}

			if (count($fieldArr))	{
				/*
					NOTE to enabledFields('pages_language_overlay'):
					Currently the showHiddenRecords of TSFE set will allow pages_language_overlay records to be selected as they are child-records of a page.
					However you may argue that the showHiddenField flag should determine this. But that's not how it's done right now.
				*/

					// Selecting overlay record:
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
							implode(',',$fieldArr),
							'pages_language_overlay',
							'pid='.intval($page_id).'
								AND sys_language_uid='.intval($lUid).
								$this->enableFields('pages_language_overlay'),
							'',
							'',
							'1'
						);
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				$this->versionOL('pages_language_overlay',$row);

				if (is_array($row))	{
					$row['_PAGES_OVERLAY'] = TRUE;

						// Unset vital fields that are NOT allowed to be overlaid:
					unset($row['uid']);
					unset($row['pid']);
				}
			}
		}

			// Create output:
		if (is_array($pageInput))	{
			return is_array($row) ? array_merge($pageInput,$row) : $pageInput;	// If the input was an array, simply overlay the newfound array and return...
		} else {
			return is_array($row) ? $row : array();	// always an array in return
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/compat/class.ux_t3lib_page.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/compat/class.ux_t3lib_page.php']);
}

?>