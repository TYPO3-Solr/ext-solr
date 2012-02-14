<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_scheduler_IndexQueueWorkerTask extends tx_scheduler_Task {

	/**
	 * The site this task is indexing.
	 *
	 * @var	tx_solr_Site
	 */
	protected $site;

	protected $documentsToIndexLimit;
	protected $configuration;


	/**
	 * Works through the indexing queue and indexes the queued items into Solr.
	 *
	 * @return	boolean	Returns TRUE on success, FALSE if no items were indexed or none were found.
	 * @throws	Exception	Throws an exception on indexing errors.
	 * @see	typo3/sysext/scheduler/tx_scheduler_Task#execute()
	 */
	public function execute() {
		$executionSucceeded  = FALSE;
		$limit               = $this->documentsToIndexLimit;
		$indexQueue          = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');
		$this->configuration = tx_solr_Util::getSolrConfigurationFromPageId($this->site->getRootPageId());

			// get items to index
		$itemsToIndex = $indexQueue->getItemsToIndex($this->site, $limit);
		foreach ($itemsToIndex as $itemToIndex) {
			try {
					// try indexing
				$itemIndexed = $this->indexItem($itemToIndex);

					// update IQ item so that the IQ can determine what's been indexed already
				if ($itemIndexed) {
					$itemToIndex->updateIndexedTime();
				}
			} catch (Exception $e) {

					// TODO mark item as failed (new column)
					// TODO make the IQ filter out failed items
					// TODO add an IQ report: currently indexing, indexed, upcoming, failed
					// TODO make the task display number of failed items

				throw $e;
			}
		}
		$executionSucceeded = TRUE;

		return $executionSucceeded;
	}

	/**
	 * Indexes an item from the index queue.
	 *
	 * @param	tx_solr_indexqueue_Item	An index queue item to index
	 * @return	boolean	TRUE if the item was successfully indexed, FALSE otherwise
	 */
	protected function indexItem(tx_solr_indexqueue_Item $item) {
		$itemIndexed = FALSE;
		$indexer     = $this->getIndexerByItem($item->getIndexingConfigurationName());

		try {
			$this->initializeHttpHost($item);
			$itemIndexed = $indexer->index($item);
		} catch (Exception $e) {
				// FIXME handle exception, log failed item
			throw $e;
		}

		return $itemIndexed;
	}

	/**
	 * A factory method to get an indexer depending on an item's configuration.
	 *
	 * By default all items are indexed using the default indexer
	 * (tx_solr_indexqueue_Indexer) coming with EXT:solr. Pages by default are
	 * configured to be indexed through a dedicated indexer
	 * (tx_solr_indexqueue_PageIndexer). In all other cases a dedicated indexer
	 * can be specified through TypoScript if needed.
	 *
	 * @param	string	Indexing configuration name.
	 * @return	tx_solr_indexqueue_Indexer	An instance of tx_solr_indexqueue_Indexer or a sub class of it.
	 */
	protected function getIndexerByItem($indexingConfigurationName) {
		$indexerClass   = 'tx_solr_indexqueue_Indexer';
		$indexerOptions = array();

			// allow to overwrite indexers per indexing configuration
		if (isset($this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer'])) {
			$indexerClass = $this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer'];
		}

			// get indexer options
		if (isset($this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'])
		&& !empty($this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'])) {
			$indexerOptions = $this->configuration['index.']['queue.'][$indexingConfigurationName . '.']['indexer.'];
		}

		$indexer = t3lib_div::makeInstance($indexerClass, $indexerOptions);
		if (!($indexer instanceof tx_solr_indexqueue_Indexer)) {
			throw new RuntimeException(
				'The indexer class "' . $indexerClass . '" for indexing configuration "' . $indexingConfigurationName . '" is not a valid indexer. Must be a subclass of tx_solr_indexqueue_Indexer.',
				1260463206
			);
		}

		return $indexer;
	}

	/**
	 * Returns some additional information about indexing progress, shown in
	 * the scheduler's task overview list.
	 *
	 * @return	string	Information to display
	 */
	public function getAdditionalInformation() {
		$totalItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
			'uid',
			'tx_solr_indexqueue_item',
			'root = ' . $this->site->getRootPageId()
		);

		$remainingItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
			'uid',
			'tx_solr_indexqueue_item',
			'changed > indexed AND root = ' . $this->site->getRootPageId()
		);
		$itemsIndexedCount = $totalItemsCount - $remainingItemsCount;

		if ($totalItemsCount > 0) {
			$itemsIndexedPercentage = $itemsIndexedCount * 100 / $totalItemsCount;
			$itemsIndexedPercentage = round($itemsIndexedPercentage, 2);
		} else {
			$itemsIndexedPercentage = 0;
		}

		$message = 'Site: ' . $this->site->getLabel() . ', '
			. 'Indexed ' . $itemsIndexedPercentage . '%. ';

		return $message;
	}

	/**
	 * Gets the site / the site's root page uid this task is indexing.
	 *
	 * @return	tx_solr_Site	The site's root page uid this task is indexing
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Sets the task's site to indexing.
	 *
	 * @param	tx_solr_Site	$site The site to index by this task
	 * @return	void
	 */
	public function setSite(tx_solr_Site $site) {
		$this->site = $site;
	}

	public function getDocumentsToIndexLimit() {
		return $this->documentsToIndexLimit;
	}

	public function setDocumentsToIndexLimit($limit) {
		$this->documentsToIndexLimit = $limit;
	}

	/**
	 * Initializes the $_SERVER['HTTP_HOST'] environment variable in CLI
	 * environments dependent on the Index Queue item's root page.
	 *
	 * When the Index Queue Worker task is executed by a cron job there is no
	 * HTTP_HOST since we are in a CLI environment. RealURL needs the host
	 * information to generate a proper URL though. Using the Index Queue item's
	 * root page information we can determine the correct host although being
	 * in a CLI environment.
	 *
	 * @param	tx_solr_indexqueue_Item	$item Index Queue item to use to determine the host.
	 */
	protected function initializeHttpHost(tx_solr_indexqueue_Item $item) {
		static $hosts = array();

			// relevant for realURL multi-domain environments, only
		if (t3lib_extMgm::isLoaded('realurl') && $this->isMultiDomain()) {
			$itemPageId = $item->getRecordPageId();
			$hostFound  = !empty($hosts[$itemPageId]);

			if (!$hostFound) {
				$rootline = t3lib_BEfunc::BEgetRootLine($itemPageId);
				$host     = t3lib_BEfunc::firstDomainRecord($rootline);

				$hosts[$itemPageId] = $host;
			}

			$_SERVER['HTTP_HOST'] = $hosts[$itemPageId];
		}
	}

	/**
	 * Checks if TYPO3 runs in a multi-domain environment.
	 *
	 * Copied from EXT:realurl.
	 *
	 * @return	boolean
	 * @see EXT:realurl/class.tx_realurl.php:tx_realurl->isMultidomain()
	 */
	protected function isMultiDomain() {
		static $isMultiDomain = NULL;

		if ($isMultiDomain === NULL) {
			list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('COUNT(distinct pid) AS t',
				'sys_domain', 'redirectTo=\'\' AND hidden=0');
			$isMultiDomain = ($row['t'] > 1);
		}

		return $isMultiDomain;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/scheduler/class.tx_solr_scheduler_indexqueueworkertask.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/scheduler/class.tx_solr_scheduler_indexqueueworkertask.php']);
}

?>