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
 * Scheduler task to execute an index <optimize /> command on a regular basis
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_scheduler_OptimizeTask extends tx_scheduler_Task {

	public $solrHost = '';
	public $solrPort = '';
	public $solrPath = '';

	/**
	 * Solr Service Instance
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solr  = null;

	/**
	 * Initializes a Solr Connection
	 *
	 * @return	void
	 */
	protected function initializeSolr() {
		if (is_null($this->solr)) {
			$this->solr = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnection(
				$this->solrHost,
				$this->solrPort,
				$this->solrPath
			);
		}
	}

	/**
	 * Executes the optimize task and returns true if the execution was
	 * succesfull
	 *
	 * @return	boolean	returns true on success, false on failure
	 */
	public function execute() {
		$result = false;

		if (is_null($this->solr)) {
			$this->initializeSolr();
		}

		$this->solr->commit();
		$response = $this->solr->optimize();
		if ($response->responseHeader->status === 0) {
			$result = true;
		}

		return $result;
	}

	/**
	 * This method is designed to return some additional information about the task,
	 * that may help to set it apart from other tasks from the same class
	 * This additional information is used - for example - in the Scheduler's BE module
	 * This method should be implemented in most task classes
	 *
	 * @return	string	Information to display
	 */
	public function getAdditionalInformation() {
		return $this->solrHost . ':' . $this->solrPort . $this->solrPath;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/scheduler/class.tx_solr_scheduler_optimizetask.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/scheduler/class.tx_solr_scheduler_optimizetask.php']);
}

?>