<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo.renner@dkd.de>
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
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Search implements t3lib_Singleton {

	/**
	 * An instance of the Solr service
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solr = NULL;

	/**
	 * The search query
	 *
	 * @var	tx_solr_Query
	 */
	protected $query = NULL;

	/**
	 * The search response
	 *
	 * @var Apache_Solr_Response
	 */
	protected $response = NULL;

	/**
	 * Flag for marking a search
	 *
	 * @var	boolean
	 */
	protected $hasSearched = FALSE;


	// TODO Override __clone to reset $response and $hasSearched

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
	 * Gets the Solr connection used by this search.
	 *
	 * @return tx_solr_SolrService Solr connection
	 */
	public function getSolrConnection() {
		return $this->solr;
	}

	/**
	 * Executes a search against a Solr server.
	 *
	 * 1) Gets the query string
	 * 2) Conducts the actual search
	 * 3) Checks debug settings
	 *
	 * @param	tx_solr_Query	$query The query with keywords, filters, and so on.
	 * @param	integer	$offset Result offset for pagination.
	 * @param	integer	$limit Maximum number of results to return. If set to NULL, this value is taken from the query object.
	 * @return	Apache_Solr_Response	Solr response
	 */
	public function search(tx_solr_Query $query, $offset = 0, $limit = 10) {
		$query = $this->modifyQuery($query);
		$this->query = $query;

		if (empty($limit)) {
			$limit = $query->getResultsPerPage();
		}

		try {
			$response = $this->solr->search(
				$query->getQueryString(),
				$offset,
				$limit,
				$query->getQueryParameters()
			);

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['queryString']) {
				t3lib_div::devLog('Querying Solr, getting result', 'solr', 0, array(
					'query string'     => $query->getQueryString(),
					'query parameters' => $query->getQueryParameters(),
					'response'         => json_decode($response->getRawResponse(), TRUE)
				));
			}
		} catch (RuntimeException $e) {
			$response = $this->solr->getResponse();

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('Exception while querying Solr', 'solr', 3, array(
					'exception' => $e->__toString(),
					'query'     => (array) $query,
					'offset'    => $offset,
					'limit'     => $limit
				));
			}
		}

		$response = $this->modifyResponse($response);
		$this->response    = $response;
		$this->hasSearched = TRUE;

		return $this->response;
	}

	/**
	 * Allows to modify a query before eventually handing it over to Solr.
	 *
	 * @param tx_solr_Query The current query before it's being handed over to Solr.
	 * @return tx_solr_Query The modified query that is actually going to be given to Solr.
	 */
	protected function modifyQuery(tx_solr_Query $query) {
			// hook to modify the search query
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'] as $classReference) {
				$queryModifier = t3lib_div::getUserObj($classReference);

				if ($queryModifier instanceof tx_solr_QueryModifier) {
					if ($queryModifier instanceof tx_solr_SearchAware) {
						$queryModifier->setSearch($this);
					}

					$query = $queryModifier->modifyQuery($query);
				} else {
					throw new UnexpectedValueException(
						get_class($queryModifier) . ' must implement interface tx_solr_QueryModifier',
						1310387414
					);
				}
			}
		}

		return $query;
	}

	/**
	 * Allows to modify a response returned from Solr before returning it to
	 * the rest of the extension.
	 *
	 * @param Apache_Solr_Response The response as returned by Solr
	 * @return Apache_Solr_Response The modified response that is actually going to be returned to the extension.
	 */
	protected function modifyResponse(Apache_Solr_Response $response) {
			// hook to modify the search response
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchResponse'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchResponse'] as $classReference) {
				$responseModifier = t3lib_div::getUserObj($classReference);

				if ($responseModifier instanceof tx_solr_ResponseModifier) {
					if ($responseModifier instanceof tx_solr_SearchAware) {
						$responseModifier->setSearch($this);
					}

					$response = $responseModifier->modifyResponse($response);
				} else {
					throw new UnexpectedValueException(
						get_class($responseModifier) . ' must implement interface tx_solr_ResponseModifier',
						1343147211
					);
				}
			}

				// add modification indicator
			$response->response->isModified = TRUE;
		}

		return $response;
	}

	/**
	 * Sends a ping to the solr server to see whether it is available.
	 *
	 * @return	boolean	Returns TRUE on successful ping.
	 * @throws	Exception	Throws an exception in case ping was not successful.
	 */
	public function ping() {
		$solrAvailable = FALSE;

		try {
			if (!$this->solr->ping()) {
				throw new Exception('Solr Server not responding.', 1237475791);
			}

			$solrAvailable = TRUE;
		} catch (Exception $e) {
			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
				t3lib_div::devLog('exception while trying to ping the solr server', 'solr', 3, array(
					$e->__toString()
				));
			}
		}

		return $solrAvailable;
	}

	/**
	 * checks whether a search has been executed
	 *
	 * @return boolean	TRUE if there was a search, FALSE otherwise (if the user just visited the search page f.e.)
	 */
	public function hasSearched() {
		return $this->hasSearched;
	}

	/**
	 * Gets the query object.
	 *
	 * @return tx_solr_Query Query
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * Gets the Solr response
	 *
	 * @return Apache_Solr_Response
	 */
	public function getResponse() {
		return $this->response;
	}

	public function getRawResponse() {
		return $this->response->getRawResponse();
	}

	public function getResponseHeader() {
		return $this->getResponse()->responseHeader;
	}

	public function getResponseBody() {
		return $this->getResponse()->response;
	}

	public function getResultDocuments() {
		return $this->getResponseBody()->docs;
	}

	/**
	 * Gets the time Solr took to execute the query and return the result.
	 *
	 * @return	integer	Query time in milliseconds
	 */
	public function getQueryTime() {
		return $this->getResponseHeader()->QTime;
	}

	/**
	 * Gets the number of results per page.
	 *
	 * @return	integer	Number of results per page
	 */
	public function getResultsPerPage() {
		return $this->getResponseHeader()->params->rows;
	}

	/**
	 * Gets all facets with their fields, options, and counts.
	 *
	 * @return
	 */
	public function getFacetCounts() {
		static $facetCountsModified = FALSE;
		static $facetCounts         = NULL;

		$unmodifiedFacetCounts = $this->response->facet_counts;

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'])) {

			if (!$facetCountsModified) {
				$facetCounts = $unmodifiedFacetCounts;

				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyFacets'] as $classReference) {
					$facetsModifier = t3lib_div::getUserObj($classReference);

					if ($facetsModifier instanceof tx_solr_FacetsModifier) {
						$facetCounts = $facetsModifier->modifyFacets($facetCounts);
						$facetCountsModified = TRUE;
					} else {
						throw new UnexpectedValueException(
							get_class($facetsModifier) . ' must implement interface tx_solr_FacetsModifier',
							1310387526
						);
					}
				}
			}

		} else {
			$facetCounts = $unmodifiedFacetCounts;
		}

		return $facetCounts;
	}

	public function getFacetFieldOptions($facetField) {
		return get_object_vars($this->getFacetCounts()->facet_fields->$facetField);
	}

	public function getFacetQueryOptions($facetField) {
		$options = array();

		$facetQueries = get_object_vars($this->getFacetCounts()->facet_queries);
		foreach ($facetQueries as $facetQuery => $numberOfResults) {
				// remove tags from the facet.query response, for facet.field
				// and facet.range Solr does that on its own automatically
			$facetQuery = preg_replace('/^\{!ex=[^\}]*\}(.*)/', '\\1', $facetQuery);

			if (t3lib_div::isFirstPartOfStr($facetQuery, $facetField)) {
				$options[$facetQuery] = $numberOfResults;
			}
		}

			// filter out queries with no results
		$options = array_filter($options);

		return $options;
	}

	public function getFacetRangeOptions($rangeFacetField) {
		return get_object_vars($this->getFacetCounts()->facet_ranges->$rangeFacetField);
	}

	public function getNumberOfResults() {
		return $this->response->response->numFound;
	}

	/**
	 * Gets the result offset.
	 *
	 * @return	integer	Result offset
	 */
	public function getResultOffset() {
		return $this->response->response->start;
	}

	public function getMaximumResultScore() {
		return $this->response->response->maxScore;
	}

	public function getDebugResponse() {
		return$this->response->debug;
	}

	public function getHighlightedContent() {
		$highlightedContent = FALSE;

		if ($this->response->highlighting) {
			$highlightedContent = $this->response->highlighting;
		}

		return $highlightedContent;
	}

	public function getSpellcheckingSuggestions() {
		$spellcheckingSuggestions = FALSE;

		$suggestions = (array) $this->response->spellcheck->suggestions;
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