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
 * XCLASS for tslib_cObj to detect frontend groups used on a page.
 *
 * Used for TYPO3 4.3
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class ux_tslib_cObj extends tslib_cObj {

### backport start ###
	protected $table = '';
### backport end ###


	/**
	 * Class constructor.
	 * Well, it has to be called manually since it is not a real constructor function.
	 * So after making an instance of the class, call this function and pass to it a database record and the tablename from where the record is from. That will then become the "current" record loaded into memory and accessed by the .fields property found in eg. stdWrap.
	 *
	 * @param	array		$data	the record data that is rendered.
	 * @param	string		$table	the table that the data record is from.
	 * @return	void
	 */
	function start($data,$table='')	{
		global $TYPO3_CONF_VARS;
		$this->data = $data;

### backport start ###
		$this->table = $table;
### backport end ###

		$this->currentRecord = $table ? $table.':'.$this->data['uid'] : '';
		$this->parameters = Array();
		if (is_array ($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'])) {
			foreach ($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['cObjTypeAndClass'] as $classArr) {
				$this->cObjHookObjectsArr[$classArr[0]] = t3lib_div::getUserObj($classArr[1]);
			}
		}

		$this->stdWrapHookObjects = array();
		if(is_array($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['stdWrap'])) {
			foreach($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['stdWrap'] as $classData) {
				$hookObject = t3lib_div::getUserObj($classData);

				if(!($hookObject instanceof tslib_content_stdWrapHook)) {
					throw new UnexpectedValueException('$hookObject must implement interface tslib_content_stdWrapHook', 1195043965);
				}

				$this->stdWrapHookObjects[] = $hookObject;
			}
		}

### backported hook start ###
		if (is_array($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit'])) {
			foreach ($TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit'] as $classData) {
				$postInitializationProcessor = t3lib_div::getUserObj($classData);

				if(!($postInitializationProcessor instanceof tslib_content_PostInitHook)) {
					throw new UnexpectedValueException(
						$postInitializationProcessor . ' must implement interface tslib_content_PostInitHook',
						1274563549
					);
				}

				$postInitializationProcessor->postProcessContentObjectInitialization($this);
			}
		}
### backported hook end ###
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/compat/class.ux_tslib_cobj.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/compat/class.ux_tslib_cobj.php']);
}

?>