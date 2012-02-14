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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Index Queue Frontend Helper interface.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
interface tx_solr_IndexQueuePageIndexerFrontendHelper {

	/**
	 * Activates a frontend helper by registering for hooks and other
	 * resources required by the frontend helper to work.
	 */
	public function activate();

	/**
	 * Deactivates a frontend helper by unregistering from hooks and releasing
	 * resources.
	 */
	public function deactivate();

	/**
	 * Starts the execution of a frontend helper.
	 *
	 * @param	tx_solr_indexqueue_PageIndexerRequest	$request Page indexer request
	 * @param	tx_solr_indexqueue_PageIndexerResponse	$response Page indexer response
	 */
	public function processRequest(tx_solr_indexqueue_PageIndexerRequest $request, tx_solr_indexqueue_PageIndexerResponse $response);

	/**
	 * Returns the collected data.
	 *
	 * @return	array	Collected data.
	 */
	public function getData();

}

?>