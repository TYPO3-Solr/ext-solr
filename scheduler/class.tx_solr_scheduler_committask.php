<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2012 Georg Kuehnberger <georg@georg.org>
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
 * Scheduler task to execute an index <commit /> command on a regular basis
 *
 * @author	Georg Kuehnberger <georg@georg.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_scheduler_CommitTask extends tx_scheduler_Task {

	/**
	 * The site this task is committing its indexes on.
	 *
	 * @var	tx_solr_Site
	 */
	protected $site;

	/**
	 * Executes the commit task and returns TRUE if the execution was
	 * succesfull
	 *
	 * @return	boolean	returns TRUE on success, FALSE on failure
	 */
	public function execute() {
		$result = FALSE;

		$solrServers = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnectionsBySite($this->site);

		foreach($solrServers as $solrServer) {
			$response = $solrServer->commit(FALSE, FALSE, FALSE);
			if ($response->responseHeader->status === 0) {
				$result = TRUE;
			}
		}

		return $result;
	}

	/**
	 * Gets the site / the site's root page uid this task is optimizinh.
	 *
	 * @return	tx_solr_Site	The site's root page uid this task is optimizinh
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Sets the task's site to optimizing.
	 *
	 * @param	tx_solr_Site	$site The site to optimizing by this task
	 * @return	void
	 */
	public function setSite(tx_solr_Site $site) {
		$this->site = $site;
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
		$information = 'Site: ';

		if($this->site) {
			$information .= $this->site->getLabel();
	}

		return $information;
}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/scheduler/class.tx_solr_scheduler_committask.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/scheduler/class.tx_solr_scheduler_committask.php']);
}

?>