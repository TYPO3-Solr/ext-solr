<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2010 Ingo Renner <ingo.renner@dkd.de>
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
 * Class to handle solr search requests
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_Search implements t3lib_Singleton {

	/**
	 * An instance of the Solr service
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solr;

	/**
	 * The search query
	 *
	 * @var	tx_solr_Query
	 */
	protected $query;

	/**
	 * Constructor
	 *
	 * @param	tx_solr_SolrService	$solrConnection The Solr connection to use for searching
	 */
	public function __construct(tx_solr_SolrService $solrConnection = NULL) {
		$this->solr = $solrConnection;

		if (is_null($solrConnection)) {
			$this->solr = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnectionByPageId(
				$GLOBALS['TSFE']->id,
				$GLOBALS['TSFE']->sys_language_uid
			);

		}
	}

	/**
	 * Executes a search against a Solr server.
	 *
	 * @param	tx_solr_Query	$query The query with keywords, filters, and so on.
	 * @param	integer	$offset Result offset for pagination.
	 * @param	integer	$limit Maximum number of results to return.
	 * @return	Apache_Solr_Response	Solr response
	 */
	public function search(tx_solr_Query $query, $offset = 0, $limit = 10) {

			// * get query string
			// * do actual search by calling the parent's search method
			// * obey the debug setting

		$this->query = $query;

		try {
			$response = $this->solr->search(
				$query->getQueryString(),
				$offset,
				$limit,
				$query->getQueryParameters()
			);

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['queryString']) {
				t3lib_div::devLog('querying solr, getting result', 'tx_solr', 0, array(
					'query string'     => $query->getQueryString(),
					'query parameters' => $query->getQueryParameters(),
					'response'         => json_decode($response->getRawResponse(), true)
				));
			}
		} catch (Exception $e) {
			// FIXME fix searches like "*.*", "-", "+"

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('exception while querying solr', 'tx_solr', 3, array(
					$e->__toString()
				));
			}
		}

		return $response;
	}

	/**
	 * Sends a ping to the solr server to see whether it is available.
	 *
	 * @return	boolean	Returns true on successful ping.
	 * @throws	Exception	Throws an exception in case ping was not successful.
	 */
	public function ping() {
		$solrAvailable = false;

		try {
			if (!$this->solr->ping()) {
				throw new Exception('Solr Server not responding.', 1237475791);
			}

			$solrAvailable = true;
		} catch (Exception $e) {
			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('exception while trying to ping the solr server', 'tx_solr', 3, array(
					$e->__toString()
				));
			}
		}

		return $solrAvailable;
	}

	/**
	 * checks whether a search has been executed
	 *
	 * @return boolean	true if there was a search, false otherwise (if the user just visited the search page f.e.)
	 */
	public function hasSearched() {
		return $this->solr->hasSearched();
	}

	public function getQuery() {
		return $this->query;
	}

	public function getResponse() {
		return $this->solr->getResponse()->response;
	}

	public function getRawResponse() {
		return $this->solr->getResponse()->getRawResponse();
	}

	public function getResponseHeader() {
		return $this->solr->getResponse()->responseHeader;
	}

	/**
	 * Gets the time Solr took to execute the query and return the result.
	 *
	 * @return	integer	Query time in milliseconds
	 */
	public function getQueryTime() {
		return $this->solr->getResponse()->responseHeader->QTime;
	}

	/**
	 * Gets the number of results per page.
	 *
	 * @return	integer	Number of results per page
	 */
	public function getResultsPerPage() {
		return $this->solr->getResponse()->responseHeader->params->rows;
	}

	public function getFacetCounts() {
		return $this->solr->getResponse()->facet_counts;
	}

	public function getNumberOfResults() {
		return $this->solr->getResponse()->response->numFound;
	}

	/**
	 * Gets the result offset.
	 *
	 * @return	integer	Result offset
	 */
	public function getResultOffset() {
		return $this->solr->getResponse()->response->start;
	}

	public function getMaximumResultScore() {
		return $this->solr->getResponse()->response->maxScore;
	}

	public function getDebugResponse() {
		return $this->solr->getResponse()->debug;
	}

	public function getHighlightedContent() {
		$highlightedContent = false;

		if ($this->solr->getResponse()->highlighting) {
			$highlightedContent = $this->solr->getResponse()->highlighting;
		}

		return $highlightedContent;
	}

	public function getSpellcheckingSuggestions() {
		$spellcheckingSuggestions = false;

		$suggestions = (array) $this->solr->getResponse()->spellcheck->suggestions;
		if (!empty($suggestions)) {
			$spellcheckingSuggestions = $suggestions;
		}

		return $spellcheckingSuggestions;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_search.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_search.php']);
}

?>