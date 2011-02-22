<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo.renner@dkd.de>
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
 * Indexer manager to decide which indexer to use to index pages.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_IndexerSelector {

	const INDEXER_STRATEGY_FRONTEND = 'Frontend';
	const INDEXER_STRATEGY_QUEUE    = 'IndexQueue';

	/**
	 * Indexer strategy to use for the current environment.
	 *
	 * @var	string
	 */
	protected $indexerStrategy = NULL;

	/**
	 * Constructor for tx_solr_IndexerSelector. Initializes the indexer
	 * strategy.
	 *
	 * @return	void
	 */
	public function __construct() {
		$this->indexerStrategy = $this->selectIndexer();
	}

	/**
	 * Determines which indexer to use for the current environment.
	 *
	 * @return	string	One of the currently supported indexer strategies, INDEXER_STRATEGY_FRONTEND or INDEXER_STRATEGY_QUEUE.
	 */
	protected function selectIndexer() {
			// default
		$indexerStrategy = self::INDEXER_STRATEGY_FRONTEND;

			// here would be code to detect a configured Index Queue

		return $indexerStrategy;
	}

	/**
	 * Registers the indexer found to be appropriate for the current environment.
	 *
	 * @return	void
	 */
	public function registerIndexer() {
			// registers the frontend indexer, the Index Queue indexer registers itself on demand
		if ($this->indexerStrategy == self::INDEXER_STRATEGY_FRONTEND) {
				// registering the page indexer itself
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['pageIndexing']['tx_solr_Indexer'] = 'EXT:solr/classes/class.tx_solr_indexer.php:tx_solr_Indexer';
				// track FE user groups used to protect content on a page
			$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['postInit']['tx_solr_Indexer'] = 'EXT:solr/classes/class.tx_solr_indexer.php:&tx_solr_Indexer';
		}
	}

	/**
	 * Gets the currently determined indexer strategy.
	 *
	 * @return	string	One of the currently supported indexer strategies, INDEXER_STRATEGY_FRONTEND or INDEXER_STRATEGY_QUEUE.
	 */
	public function getIndexerStrategy() {
		return $this->indexerStrategy;
	}

	/**
	 * Checks whether a given indexer strategy exists / is valid.
	 *
	 * @param	string	$indexerStrategy Indexer strategy to validate.
	 * @return	boolean	TRUE if it's a valid indexer strategy, FALSE otherwise.
	 */
	public static function indexerStrategyExists($indexerStrategy) {
		$strategyExists = FALSE;
		$validStartegies = array(
			self::INDEXER_STRATEGY_FRONTEND,
			self::INDEXER_STRATEGY_QUEUE
		);

		if (in_array($indexerStrategy, $validStartegies)) {
			$strategyExists = TRUE;
		}

		return $strategyExists;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_indexerselector.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_indexerselector.php']);
}

?>