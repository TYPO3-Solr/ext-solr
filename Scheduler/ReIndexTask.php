<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2015 Christoph Moeller <support@network-publishing.de>
*  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
*
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
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Scheduler task to empty the indexes of a site and re-initialize the
 * Solr Index Queue thus making the indexer re-index the site.
 *
 * @author Christoph Moeller <support@network-publishing.de>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_Scheduler_ReIndexTask extends tx_scheduler_Task {

	/**
	 * The site this task is supposed to initialize the index queue for.
	 *
	 * @var Tx_Solr_Site
	 */
	protected $site;

	/**
	 * Indexing configurations to re-initialize.
	 *
	 * @var array
	 */
	protected $indexingConfigurationsToReIndex = array();


	/**
	 * Purges/commits all Solr indexes, initializes the Index Queue
	 * and returns TRUE if the execution was successful
	 *
	 * @return boolean Returns TRUE on success, FALSE on failure.
	 */
	public function execute() {
			// clean up
		$cleanUpResult = $this->cleanUpIndex();

			// initialize for re-indexing
		$indexQueue = GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');
		$indexQueueInitializationResults = array();
		foreach ($this->indexingConfigurationsToReIndex as $indexingConfigurationName) {
			$indexQueueInitializationResults = $indexQueue->initialize($this->site, $indexingConfigurationName);
		}

		return ($cleanUpResult && !in_array(FALSE, $indexQueueInitializationResults));
	}

	/**
	 * Removes documents of the selected types from the index.
	 *
	 * @return bool TRUE if clean up was successful, FALSE on error
	 */
	protected function cleanUpIndex() {
		$cleanUpResult     = TRUE;
		$solrConfiguration = $this->site->getSolrConfiguration();
		$solrServers       = GeneralUtility::makeInstance('Tx_Solr_ConnectionManager')->getConnectionsBySite($this->site);
		$typesToCleanUp    = array();

		foreach ($this->indexingConfigurationsToReIndex as $indexingConfigurationName) {
			$type = Tx_Solr_IndexQueue_Queue::getTableToIndexByIndexingConfigurationName(
				$solrConfiguration,
				$indexingConfigurationName
			);

			$typesToCleanUp[] = $type;
		}

		foreach ($solrServers as $solrServer) {
					// make sure not-yet committed documents are removed, too
				$solrServer->commit();

				$deleteQuery = 'type:(' . implode(' OR ', $typesToCleanUp) . ')'
					. ' AND siteHash:' . $this->site->getSiteHash();
				$solrServer->deleteByQuery($deleteQuery);

				$response = $solrServer->commit(FALSE, FALSE, FALSE);
				if ($response->getHttpStatus() != 200) {
					$cleanUpResult = FALSE;
					break;
				}
		}

		return $cleanUpResult;
	}

	/**
	 * Gets the site / the site's root page uid this task is running on.
	 *
	 * @return Tx_Solr_Site The site's root page uid this task is optimizing
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Sets the task's site.
	 *
	 * @param Tx_Solr_Site $site The site to be handled by this task
	 */
	public function setSite(Tx_Solr_Site $site) {
		$this->site = $site;
	}

	/**
	 * Gets the indexing configurations to re-index.
	 *
	 * @return array
	 */
	public function getIndexingConfigurationsToReIndex() {
		return $this->indexingConfigurationsToReIndex;
	}

	/**
	 * Sets the indexing configurations to re-index.
	 *
	 * @param array $indexingConfigurationsToReIndex
	 */
	public function setIndexingConfigurationsToReIndex(array $indexingConfigurationsToReIndex) {
		$this->indexingConfigurationsToReIndex = $indexingConfigurationsToReIndex;
	}

	/**
	 * This method is designed to return some additional information about the task,
	 * that may help to set it apart from other tasks from the same class
	 * This additional information is used - for example - in the Scheduler's BE module
	 * This method should be implemented in most task classes
	 *
	 * @return string Information to display
	 */
	public function getAdditionalInformation() {
		$information = '';

		if ($this->site) {
			$information = 'Site: ' . $this->site->getLabel();
		}

		if (!empty($this->indexingConfigurationsToReIndex)) {
			$information .= ', Indexing Configurations: ' . implode(', ', $this->indexingConfigurationsToReIndex);
		}

		return $information;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Scheduler/ReIndexTask.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Scheduler/ReIndexTask.php']);
}

?>