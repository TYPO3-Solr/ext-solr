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
 * Dispatches the actions requested to the matching frontend helpers.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_frontendhelper_Dispatcher {

	/**
	 * Frontend helper manager.
	 *
	 * @var	tx_solr_indexqueue_frontendhelper_Manager
	 */
	protected $frontendHelperManager;

	/**
	 * Constructor for tx_solr_indexqueue_frontendhelper_Dispatcher
	 */
	public function __construct() {
		$this->frontendHelperManager = t3lib_div::makeInstance('tx_solr_indexqueue_frontendhelper_Manager');
	}

	/**
	 * Takes the request's actions and hands them of to the according frontend
	 * helpers.
	 *
	 * @param	tx_solr_indexqueue_PageIndexerRequest	$request The request to dispatch
	 * @param	tx_solr_indexqueue_PageIndexerResponse	$response The request's response
	 */
	public function dispatch(tx_solr_indexqueue_PageIndexerRequest $request, tx_solr_indexqueue_PageIndexerResponse $response) {
		$actions = $request->getActions();

		foreach ($actions as $action) {
			$frontendHelper = $this->frontendHelperManager->resolveAction($action);
			$frontendHelper->activate();
			$frontendHelper->processRequest($request, $response);
		}
	}

	/**
	 * Sends a shutdown signal to all activated frontend helpers.
	 *
	 * @return	void
	 */
	public function shutdown() {
		$frontendHelpers = $this->frontendHelperManager->getActivatedFrontendHelpers();

		foreach ($frontendHelpers as $frontendHelper) {
			$frontendHelper->deactivate();
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_dispatcher.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/frontendhelper/class.tx_solr_indexqueue_frontendhelper_dispatcher.php']);
}

?>