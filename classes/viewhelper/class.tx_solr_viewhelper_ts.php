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
 * A viewhelper to retrieve TS values and/or objects
 * Replaces viewhelpers ###TS:path.to.some.ts.property.or.content.object###
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_viewhelper_Ts implements tx_solr_ViewHelper {

	/**
	 * instance of tslib_cObj
	 *
	 * @var tslib_cObj
	 */
	protected $contentObject = null;

	/**
	 * constructor for class tx_solr_viewhelper_Ts
	 */
	public function __construct(array $arguments = array()) {

	}

	public function execute(array $arguments = array()) {
		$typoScriptPath = array_shift($arguments);

			// TODO add a feature to resolve content objects
		if (count($arguments)) {
			return $this->resolveTypoScriptPath($typoScriptPath, $arguments);
		} else {
			return $this->resolveTypoScriptPath($typoScriptPath);
		}
	}

	/**
	 * resolves a TS path and returns its value
	 *
	 * @param	string	a TS path, separated with dots
	 * @return	string
	 * @author	Ingo Renner <ingo@typo3.org>
	 */
	protected function resolveTypoScriptPath($path, $arguments = NULL) {
		$pathExploded = explode('.', trim($path));
		$depth        = count($pathExploded);
		$pathBranch   = $GLOBALS['TSFE']->tmpl->setup;
		$value        = '';

		for($i = 0; $i < $depth; $i++) {
			if ($i < ($depth -1 )) {
				$pathBranch = $pathBranch[$pathExploded[$i] . '.'];
			} elseif (empty($pathExploded[$i])) {
					// path ends with a dot. We return the rest of the array
				$value = $pathBranch;
			} else {
					// path ends without a dot. We return the value.
				$value = $pathBranch[$pathExploded[$i]];

				if (isset($pathBranch[$pathExploded[$i] . '.'])) {
						// okay, seems to be a TS Content Element, let's run it
					$cObj = $this->getContentObject();
					if (count($arguments)) {
						$cObj->start(array('arguments' => $arguments));
					}

					$value = $cObj->cObjGetSingle(
						$pathBranch[$pathExploded[$i]],
						$pathBranch[$pathExploded[$i] . '.']
					);
				}
			}
		}

		return $value;
	}

	/**
	 * Returns the viewhelper's internal cObj. If it hasn't been used yet, a
	 * new cObj ist instanciated on demand.
	 *
	 * @return	tslib_cObj	A content object.
	 */
	protected function getContentObject() {
		if (is_null($this->contentObject)) {
			$this->contentObject = t3lib_div::makeInstance('tslib_cObj');
		}

		return $this->contentObject;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_ts.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_ts.php']);
}

?>