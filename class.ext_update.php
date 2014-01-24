<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Ingo Renner <ingo.renner@dkd.de>
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
 * Update class for the extension manager.
 *
 * @author Ingo Renner <ingo.renner@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class ext_update {

	/**
	 * Determines whether the update menu entry should by shown.
	 *
	 * @return boolean TRUE if we need ti run an update, FALSE otherwise
	 */
	public function access() {
		return $this->needsStaticIncludeUpdate();
	}

	/**
	 * Main update function called by the extension manager.
	 *
	 * @return string
	 */
	public function main() {
		$this->updateStaticIncludes();

		return 'Done.';
	}

	/**
	 * Checks for old static includes.
	 *
	 * @return boolean TRUE if old static includes are found, FALSE if everything's ok
	 */
	protected function needsStaticIncludeUpdate() {
		$numInvalidIncludes = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
			'uid',
			'sys_template',
			'include_static_file LIKE \'%EXT:solr/static/%\''
		);

		return ($numInvalidIncludes > 0);
	}

	/**
	 * Updates references to static TypoScript includes
	 *
	 * @return void
	 */
	protected function updateStaticIncludes() {
		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/static/\', \'/Configuration/TypoScript/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);

		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/solr/\', \'/Solr/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);

		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/opensearch/\', \'/OpenSearch/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);

		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/everything-on/\', \'/EverythingOn/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);

		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/filter-pages/\', \'/FilterPages/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);

		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/indexqueue-news/\', \'/IndexQueueNews/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);

		$GLOBALS['TYPO3_DB']->sql_query(
			'UPDATE sys_template
			 SET include_static_file = REPLACE(include_static_file, \'/indexqueue-ttnews/\', \'/IndexQueueTtNews/\')
			 WHERE include_static_file LIKE \'%EXT:solr/%\''
		);
	}

}

?>