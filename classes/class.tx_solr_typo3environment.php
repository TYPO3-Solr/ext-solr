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
 * TYPO3 Environment Information
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Typo3Environment implements t3lib_Singleton {

	/**
	 * Checks whether file indexing is enabled.
	 *
	 * @return	boolean	TRUE if file indexing is enabled, FALSE otherwise.
	 */
	public function isFileIndexingEnabled() {
		return (boolean) $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['enableFileIndexing'];
	}

	/**
	 * Checks whether the Index Queue is used to index the current site.
	 *
	 * Tries to find an Index Queue Worker scheduler task that uses a Solr
	 * server configured for the current site. The Index Queue Worker task is
	 * configured for the same site if the Solr server configuration has the
	 * same root page uid like the current page Id's root page uid.
	 *
	 * @return	boolean	TRUE if the Index Queue is configured for the current site, FALSE otherwise.
	 */
	public function isUsingIndexQueue() {
		$usingIndexQueue = FALSE;

			// can not use t3lib_extMgm::isLoaded('scheduler') since scheduler
			// is not loaded in frontend context
		$schedulerLastRun = t3lib_div::makeInstance('t3lib_Registry')->get('tx_scheduler', 'lastRun');
		$schedulerLoaded  = ($schedulerLastRun['end'] > (time() - 86400)); // ran in the last 24h

		if ($schedulerLoaded) {
			$rootPage = tx_solr_Util::getRootPageId($GLOBALS['TSFE']->id);

			$indexQueueWorkers = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'serialized_task_object',
				'tx_scheduler_task',
				'classname = \'tx_solr_scheduler_IndexQueueWorkerTask\''
			);

			if (count($indexQueueWorkers)) {
					// ugly hack, autoloader won't load tx_scheduler_Task as
					// scheduler is declared as not to be loaded in frontend...
				require_once(PATH_typo3 . 'sysext/scheduler/class.tx_scheduler_task.php');
			}

			foreach ($indexQueueWorkers as $indexQueueWorker) {
				$indexQueueWorker = unserialize($indexQueueWorker['serialized_task_object']);
				/* @var	$indexQueueWorker	tx_solr_scheduler_IndexQueueWorkerTask */

				$site = $indexQueueWorker->getSite();
				if ($site->getRootPageId() == $rootPage) {
					$usingIndexQueue = TRUE;
					break;
				}
			}
		}

		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['useIndexQueue'])) {
			$usingIndexQueue = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['useIndexQueue'];
		}

		return $usingIndexQueue;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_typo3environment.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_typo3environment.php']);
}

?>