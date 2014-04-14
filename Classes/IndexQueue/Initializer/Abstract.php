<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * Abstract Index Queue initializer with implementation  of methods for common
 * needs during Index Queue initialization.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
abstract class Tx_Solr_IndexQueue_Initializer_Abstract implements Tx_Solr_IndexQueueInitializer {

	/**
	 * Site to initialize
	 *
	 * @var Tx_Solr_Site
	 */
	protected $site;

	/**
	 * The type of items this initializer is handling.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Index Queue configuration.
	 *
	 * @var array
	 */
	protected $indexingConfiguration;

	/**
	 * Indexing configuration name.
	 *
	 * @var string
	 */
	protected $indexingConfigurationName;


		// Object initialization


	/**
	 * Sets the site for the initializer.
	 *
	 * @param Tx_Solr_Site $site The site to initialize Index Queue items for.
	 * @see Tx_Solr_IndexQueueInitializer::setSite()
	 */
	public function setSite(Tx_Solr_Site $site) {
		$this->site = $site;
	}

	/**
	 * Set the type (usually a Db table name) of items to initialize.
	 *
	 * @param string $type Type to initialize.
	 * @see Tx_Solr_IndexQueueInitializer::setType()
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Sets the configuration for how to index a type of items.
	 *
	 * @param array $indexingConfiguration Indexing configuration from TypoScript
	 * @see Tx_Solr_IndexQueueInitializer::setIndexingConfiguration()
	 */
	public function setIndexingConfiguration(array $indexingConfiguration) {
		$this->indexingConfiguration = $indexingConfiguration;
	}

	/**
	 * Sets the name of the indexing configuration to initialize.
	 *
	 * @param string $indexingConfigurationName Indexing configuration name
	 * @see Tx_Solr_IndexQueueInitializer::setIndexingConfigurationName()
	 */
	public function setIndexingConfigurationName($indexingConfigurationName) {
		$this->indexingConfigurationName = (string) $indexingConfigurationName;
	}


		// Index Queue initialization


	/**
	 * Initializes Index Queue items for a certain site and indexing
	 * configuration.
	 *
	 * @return boolean TRUE if initialization was successful, FALSE on error.
	 * @see Tx_Solr_IndexQueueInitializer::initialize()
	 */
	public function initialize() {
		$initialized = FALSE;

		$initializationQuery = 'INSERT INTO tx_solr_indexqueue_item (root, item_type, item_uid, indexing_configuration, indexing_priority, changed) '
			. $this->buildSelectStatement() . ' '
			. 'FROM ' . $this->type . ' '
			. 'WHERE '
				. $this->buildPagesClause()
				. $this->buildTcaWhereClause()
				. $this->buildUserWhereClause();

		$GLOBALS['TYPO3_DB']->sql_query($initializationQuery);

		if (!$GLOBALS['TYPO3_DB']->sql_error()) {
			$initialized = TRUE;
		}

		$this->logInitialization($initializationQuery);

		return $initialized;
	}

	protected function logInitialization($initializationQuery) {
		$solrConfiguration = $this->site->getSolrConfiguration();

		$logSeverity = -1;
		$logData     = array(
			'site'                        => $this->site->getLabel(),
			'indexing configuration name' => $this->indexingConfigurationName,
			'type'                        => $this->type,
			'query'                       => $initializationQuery,
			'rows'                        => $GLOBALS['TYPO3_DB']->sql_affected_rows()
		);

		if ($GLOBALS['TYPO3_DB']->sql_errno()) {
			$logSeverity      = 3;
			$logData['error'] = $GLOBALS['TYPO3_DB']->sql_errno() . ': ' . $GLOBALS['TYPO3_DB']->sql_error();
		}

		if ($solrConfiguration['logging.']['indexing.']['indexQueueInitialization']) {
			t3lib_div::devLog(
				'Index Queue initialized for indexing configuration ' . $this->indexingConfigurationName,
				'solr',
				$logSeverity,
				$logData
			);
		}
	}


		// initialization query building


	/**
	 * Builds the SELECT part of the Index Queue initialization query.
	 *
	 */
	protected function buildSelectStatement() {
		$changedField = $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'];
		if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'])) {
			$changedField = 'GREATEST(' . $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['starttime'] . ',' . $GLOBALS['TCA'][$this->type]['ctrl']['tstamp'] . ')';
		}
		$select = 'SELECT '
			. '\'' . $this->site->getRootPageId() . '\' as root, '
			. '\'' . $this->type . '\' AS item_type, '
			. 'uid AS item_uid, '
			. '\'' . $this->indexingConfigurationName . '\' as indexing_configuration, '
			. $this->getIndexingPriority() . ' AS indexing_priority, '
			. $changedField . ' AS changed';

		return $select;
	}

	/**
	 * Builds a part of the WHERE clause of the Index Queue initialization
	 * query. This part selects the limits items to be selected from the pages
	 * in a site only, plus additional pages that may have been configured.
	 *
	 */
	protected function buildPagesClause() {
		$pages = $this->getPages();

		$pageIdField = 'pid';
		if ($this->type == 'pages') {
			$pageIdField = 'uid';
		}

		return $pageIdField . ' IN(' . implode(',', $pages) . ')';
	}

	/**
	 * Gets the pages in a site plus additional pages that may have been
	 * configured.
	 *
	 * @return array A (sorted) array of page IDs in a site
	 */
	protected function getPages() {
		$pages = $this->site->getPages();

		$additionalPageIds = array();
		if (!empty($this->indexingConfiguration['additionalPageIds'])) {
			$additionalPageIds = t3lib_div::intExplode(
				',',
				$this->indexingConfiguration['additionalPageIds']
			);
		}

		$pages = array_merge($pages, $additionalPageIds);
		sort($pages, SORT_NUMERIC);

		return $pages;
	}

	/**
	 * Reads the indexing priority for an indexing configuration.
	 *
	 * @return integer Indexing priority
	 */
	protected function getIndexingPriority() {
		$priority = 0;

		if (!empty($this->indexingConfiguration['indexingPriority'])) {
			$priority = (int) $this->indexingConfiguration['indexingPriority'];
		}

		return $priority;
	}

	/**
	 * Builds the WHERE clauses of the Index Queue initialization query based
	 * on TCA information for the type to be initialized.
	 *
	 * @return string Conditions to only add indexable items to the Index Queue
	 */
	protected function buildTcaWhereClause() {
		$tcaWhereClause = '';
		$conditions     = array();

		if (isset($GLOBALS['TCA'][$this->type]['ctrl']['delete'])) {
			$conditions['delete'] = $GLOBALS['TCA'][$this->type]['ctrl']['delete'] . ' = 0';
		}

		if (isset($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['disabled'])) {
			$conditions['disabled'] = $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['disabled'] . ' = 0';
		}

		if (isset($GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['endtime'])) {
				// only include records with a future endtime or default value (0)
			$endTimeFieldName = $GLOBALS['TCA'][$this->type]['ctrl']['enablecolumns']['endtime'];
			$conditions['endtime'] = '(' . $endTimeFieldName . ' > ' . time() . ' OR ' . $endTimeFieldName . ' = 0)';
		}

		if (t3lib_BEfunc::isTableLocalizable($this->type)) {
			$conditions['languageField'] = array(
				$GLOBALS['TCA'][$this->type]['ctrl']['languageField'] . ' = 0', // default language
				$GLOBALS['TCA'][$this->type]['ctrl']['languageField'] . ' = -1' // all languages
			);
			if (isset($GLOBALS['TCA'][$this->type]['ctrl']['transOrigPointerField'])) {
				$conditions['languageField'][] = $GLOBALS['TCA'][$this->type]['ctrl']['transOrigPointerField'] . ' = 0'; // translations without original language source
			}
			$conditions['languageField'] = '(' . implode(' OR ', $conditions['languageField']) . ')';
		}

		if (!empty($GLOBALS['TCA'][$this->type]['ctrl']['versioningWS'])) {
				// versioning is enabled for this table: exclude draft workspace records
			$conditions['versioningWS'] = 'pid != -1';
		}

		if (count($conditions)) {
			$tcaWhereClause = ' AND ' . implode(' AND ', $conditions);
		}

		return $tcaWhereClause;
	}

	/**
	 * Builds the WHERE clauses of the Index Queue initialization query based
	 * on TypoScript configuration for the type to be initialized.
	 *
	 * @return string Conditions to add items to the Index Queue based on TypoScript configuration
	 */
	protected function buildUserWhereClause() {
		$condition = '';

			// FIXME replace this with the mechanism described below
		if (isset($this->indexingConfiguration['additionalWhereClause'])) {
			$condition = ' AND '  . $this->indexingConfiguration['additionalWhereClause'];
		}

		return $condition;

		// TODO add a query builder implementation based on TypoScript configuration

/* example TypoScript

		@see http://docs.jboss.org/drools/release/5.4.0.Final/drools-expert-docs/html_single/index.html
		@see The Java Rule Engine API (JSR94)

		tt_news {

				// RULES cObject provided by EXT:rules, simply evaluates to boolean TRUE or FALSE
			conditions = RULES
			conditions {

				and {

					10 {
						field = pid
						value = 2,3,5
						condition = in / equals / notEquals / greaterThan / lessThan / greaterThanOrEqual / lessThanOrEqual
					}

					20 {
						field = ...
						value = ...
						condition = ...

						or {
							10 {
								field = ...
								value = ...
								condition =  ...
							}

							20 {
								field = ...
								value = ...
								condition = ...
							}
						}
					}

				}

			}

			fields {
				// field mapping
			}
		}
*/

	}

}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/IndexQueue/Initializer/Abstract.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/IndexQueue/Initializer/Abstract.php']);
}

?>