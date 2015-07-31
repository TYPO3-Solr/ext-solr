<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;
use TYPO3\CMS\Scheduler\Task\AbstractTask;


/**
 * A worker indexing the items in the index queue. Needs to be set up as one
 * task per root page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_Scheduler_IndexQueueWorkerTask extends AbstractTask implements ProgressProviderInterface {

	/**
	 * The site this task is indexing.
	 *
	 * @var Site
	 */
	protected $site;

	protected $documentsToIndexLimit;
	protected $configuration;


	/**
	 * Works through the indexing queue and indexes the queued items into Solr.
	 *
	 * @return boolean Returns TRUE on success, FALSE if no items were indexed or none were found.
	 */
	public function execute() {
		$executionSucceeded = FALSE;

		$this->configuration = Tx_Solr_Util::getSolrConfigurationFromPageId($this->site->getRootPageId());
		$this->indexItems();
		$this->cleanIndex();
		$executionSucceeded = TRUE;

		return $executionSucceeded;
	}

	/**
	 * Indexes items from the Index Queue.
	 *
	 * @return void
	 */
	protected function indexItems() {
		$limit      = $this->documentsToIndexLimit;
		$indexQueue = GeneralUtility::makeInstance('Tx_Solr_IndexQueue_Queue');

			// get items to index
		$itemsToIndex = $indexQueue->getItemsToIndex($this->site, $limit);
		foreach ($itemsToIndex as $itemToIndex) {
			try {
					// try indexing
				$itemIndexed = $this->indexItem($itemToIndex);
			} catch (Exception $e) {
				$indexQueue->markItemAsFailed(
					$itemToIndex,
					$e->getCode() . ': ' . $e->__toString()
				);

				GeneralUtility::devLog(
					'Failed indexing Index Queue item ' . $itemToIndex->getIndexQueueUid(),
					'solr',
					3,
					array(
						'code'    => $e->getCode(),
						'message' => $e->getMessage(),
						'trace'   => $e->getTrace(),
						'item'    => (array) $itemToIndex
					)
				);
			}
		}
	}

	/**
	 * Indexes an item from the Index Queue.
	 *
	 * @param Tx_Solr_IndexQueue_Item $item An index queue item to index
	 * @return boolean TRUE if the item was successfully indexed, FALSE otherwise
	 */
	protected function indexItem(Tx_Solr_IndexQueue_Item $item) {
		$itemIndexed = FALSE;
		$indexer     = $this->getIndexerByItem($item->getIndexingConfigurationName());

		// Remember original http host value
		$originalHttpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : NULL;
		// Overwrite http host
		$this->initializeHttpHost($item);

		$itemIndexed = $indexer->index($item);

		// update IQ item so that the IQ can determine what's been indexed already
		if ($itemIndexed) {
			$item->updateIndexedTime();
		}

		// restore http host
		if (!is_null($originalHttpHost)) {
			$_SERVER['HTTP_HOST'] = $originalHttpHost;
		} else {
			unset($_SERVER['HTTP_HOST']);
		}

		return $itemIndexed;
	}

	/**
	 * Executes some index maintenance tasks on the site's indexes.
	 *
	 * @return void
	 */
	protected function cleanIndex() {
		if (rand(1, 100) == 50) {
				// clean the index about once in every 100 executions
			$garbageCollector = GeneralUtility::makeInstance('Tx_Solr_GarbageCollector');
			$garbageCollector->cleanIndex($this->site, FALSE);
		}
	}

	/**
	 * A factory method to get an indexer depending on an item's configuration.
	 *
	 * By default all items are indexed using the default indexer
	 * (Tx_Solr_IndexQueue_Indexer) coming with EXT:solr. Pages by default are
	 * configured to be indexed through a dedicated indexer
	 * (Tx_Solr_IndexQueue_PageIndexer). In all other cases a dedicated indexer
	 * can be specified through TypoScript if needed.
	 *
	 * @param string $indexingConfigurationName Indexing configuration name.
	 * @return Tx_Solr_IndexQueue_Indexer An instance of Tx_Solr_IndexQueue_Indexer or a sub class of it.
	 */
	protected function getIndexerByItem($indexingConfigurationName) {
		$indexerClass   = 'Tx_Solr_IndexQueue_Indexer';
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

		$indexer = GeneralUtility::makeInstance($indexerClass, $indexerOptions);
		if (!($indexer instanceof Tx_Solr_IndexQueue_Indexer)) {
			throw new RuntimeException(
				'The indexer class "' . $indexerClass . '" for indexing configuration "' . $indexingConfigurationName . '" is not a valid indexer. Must be a subclass of Tx_Solr_IndexQueue_Indexer.',
				1260463206
			);
		}

		return $indexer;
	}

	/**
	 * Returns some additional information about indexing progress, shown in
	 * the scheduler's task overview list.
	 *
	 * @return string Information to display
	 */
	public function getAdditionalInformation() {
		$itemsIndexedPercentage = $this->getProgress();

		$message = 'Site: ' . $this->site->getLabel();

		$failedItemsCount = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows(
			'uid',
			'tx_solr_indexqueue_item',
			'root = ' . $this->site->getRootPageId() . ' AND errors != \'\''
		);
		if ($failedItemsCount) {
			$message .= ' Failures: ' . $failedItemsCount;
		}

		return $message;
	}

	/**
	 * Gets the indexing progress.
	 *
	 * @return float Indexing progress as a two decimal precision float. f.e. 44.87
	 */
	public function getProgress() {
		$itemsIndexedPercentage = 0.0;

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
		}

		return $itemsIndexedPercentage;
	}

	/**
	 * Gets the site / the site's root page uid this task is indexing.
	 *
	 * @return Site The site's root page uid this task is indexing
	 */
	public function getSite() {
		return $this->site;
	}

	/**
	 * Sets the task's site to indexing.
	 *
	 * @param Site $site The site to index by this task
	 * @return	void
	 */
	public function setSite(Site $site) {
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
	 * @param Tx_Solr_IndexQueue_Item $item Index Queue item to use to determine the host.
	 */
	protected function initializeHttpHost(Tx_Solr_IndexQueue_Item $item) {
		static $hosts = array();

			// relevant for realURL environments, only
		if (ExtensionManagementUtility::isLoaded('realurl')) {
			$rootpageId = $item->getRootPageUid();
			$hostFound  = !empty($hosts[$rootpageId]);

			if (!$hostFound) {
				$rootline = BackendUtility::BEgetRootLine($rootpageId);
				$host     = BackendUtility::firstDomainRecord($rootline);

				$hosts[$rootpageId] = $host;
			}

			$_SERVER['HTTP_HOST'] = $hosts[$rootpageId];
		}
	}
}

