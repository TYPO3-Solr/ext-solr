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
 * Checks for Index Queue page indexer requests and handles the actions
 * requested by them.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class Tx_Solr_IndexQueue_PageIndexerRequestHandler implements t3lib_Singleton {

	/**
	 * Index Queue page indexer request.
	 *
	 * @var	Tx_Solr_IndexQueue_PageIndexerRequest
	 */
	protected $request;

	/**
	 * Index Queue page indexer response.
	 *
	 * @var	Tx_Solr_IndexQueue_PageIndexerResponse
	 */
	protected $response;

	/**
	 * Index Queue page indexer frontend helper dispatcher.
	 *
	 * @var	Tx_Solr_IndexQueue_FrontendHelper_Dispatcher
	 */
	protected $dispatcher;

	/**
	 * Constructor for Tx_Solr_IndexQueue_PageIndexerRequestHandler.
	 *
	 * Initializes request, response, and dispatcher.
	 *
	 */
	public function __construct() {
		$this->dispatcher = t3lib_div::makeInstance('Tx_Solr_IndexQueue_FrontendHelper_Dispatcher');

		$this->request    = t3lib_div::makeInstance('Tx_Solr_IndexQueue_PageIndexerRequest',
			$_SERVER['HTTP_X_TX_SOLR_IQ']
		);
		$this->response   = t3lib_div::makeInstance('Tx_Solr_IndexQueue_PageIndexerResponse');
		$this->response->setRequestId($this->request->getRequestId());
	}

	/**
	 * Authenticates the request, runs the frontend helpers defined by the
	 * request, and registers its own shutdown() method for execution at
	 * hook_eofe in tslib/class.tslib_fe.php.
	 *
	 * @return	void
	 */
	public function run() {
		if (!$this->request->isAuthenticated()) {
			t3lib_div::devLog(
				'Invalid Index Queue Frontend Request detected!',
				'solr',
				3,
				array(
					'page indexer request' => (array) $this->request,
					'index queue header'   => $_SERVER['HTTP_X_TX_SOLR_IQ']
				)
			);
			http_response_code(403);
			die('Invalid Index Queue Request!');
		}

		$this->dispatcher->dispatch($this->request, $this->response);

			// register shutdown method here instead of in ext_localconf.php to
			// allow frontend helpers to execute at hook_eofe in
			// tslib/class.tslib_fe.php before shuting down
		$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe'][__CLASS__] = '&Tx_Solr_IndexQueue_PageIndexerRequestHandler->shutdown';
	}

	/**
	 * Completes the Index Queue page indexer request and returns the response
	 * with the collected results.
	 *
	 * @return	void
	 */
	public function shutdown() {
		$this->dispatcher->shutdown();

			// make sure that no other output messes up the data
		ob_end_clean();

		$this->response->sendHeaders();
		echo $this->response->getContent();

			// exit since we don't want anymore output
		exit;
	}

	/**
	 * Gets the Index Queue page indexer request.
	 *
	 * @return	Tx_Solr_IndexQueue_PageIndexerRequest
	 */
	public function getRequest() {
		return $this->request;
	}

	/**
	 * Gets the Index Queue page indexer resposne.
	 *
	 * @return	Tx_Solr_IndexQueue_PageIndexerResponse
	 */
	public function getResponse() {
		return $this->response;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/IndexQueue/PageIndexerRequestHandler.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/IndexQueue/PageIndexerRequestHandler.php']);
}

?>