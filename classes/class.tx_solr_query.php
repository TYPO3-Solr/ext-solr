<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * A Solr search query
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_Query {

	const SORT_ASC = 'ASC';
	const SORT_DESC = 'DESC';

	/**
	 * Used to identify the queries.
	 */
	protected static $idCount = 0;
	protected $id;

	protected $solrConfiguration;

	protected $keywords;
	protected $filters;
	protected $sorting;

	protected $queryString;

	protected $queryParameters = array();

	protected $resultsPerPage;
	protected $page;

	/**
	 * holds the query fields with their associated boosts. The key represents
	 * the field name, value represents the field's boost.
	 *
	 * @var array
	 */
	protected $queryFields = array();
	protected $returnFields = array();
	protected $filterFields;
	protected $sortingFields;

	protected $subQueries = array();

	/**
	 * constructor for class tx_solr_Query
	 */
	public function __construct($keywords) {
		$this->solrConfiguration = tx_solr_Util::getSolrConfiguration();

			// TODO specify which fields to get exactly
		$this->returnFields = array('*', 'score');
		$this->setKeywords($keywords);
		$this->filters  = array();
		$this->sorting  = '';

		if (!empty($this->solrConfiguration['search.']['query.']['fields'])) {
			$this->setQueryFieldsFromString($this->solrConfiguration['search.']['query.']['fields']);
		}

		$this->id = ++self::$idCount;
	}

	/**
	 * magic implementation for clone(), makes sure that the id counter is
	 * incremented
	 *
	 * @return void
	 */
	public function __clone() {
		$this->id = ++self::$idCount;
	}

	/**
	 * Creates the string that is later used as the q parameter in the solr query
	 *
	 * @return void
	 */
	protected function buildQueryString() {
			// very simple for now
		$this->queryString = $GLOBALS['TSFE']->csConvObj->utf8_encode(
			$this->keywords,
			$GLOBALS['TSFE']->metaCharset
		);
	}

	/**
	 * returns a string representation of the query
	 *
	 * @return	string	the string representation of the query
	 */
	public function __toString() {
		return $this->getQueryString();
	}

	/**
	 * Builds the query string which is then used for Solr's q parameters
	 *
	 * @return	string	Solr query string
	 */
	public function getQueryString() {
		$this->buildQueryString();
		return $this->queryString;
	}

	/**
	 * Sets the query string, be cautious with this function!
	 *
	 * @param $queryString
	 */
	public function setQueryString($queryString) {
		$this->queryString = $queryString;
	}

	/**
	 * Returns the query's ID.
	 *
	 * @return	integer	The query's ID.
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Quote and escape search strings
	 *
	 * @param	string	the search string
	 * @return	string	the escaped/quoted string
	 */
	public function escape($string) {
		if (!is_numeric($string)) {
			if (preg_match('/\W/', $string) == 1) {
					// interpret as phrase, if it's not a single word
				$string = Apache_Solr_Service::escapePhrase($string);
			} else {
				$string = Apache_Solr_Service::escape($string);
			}
		}

		return $string;
	}


	// pagination


	/**
	 * Returns the number of results that should be shown per page
	 *
	 * @return	integer	number of resutls to show per page
	 */
	public function getResultsPerPage() {
		return $this->resultsPerPage;
	}

	/**
	 * Sets the number of results that should be shown per page
	 *
	 * @param	integer	Number of results to show per page
	 * @return	coid
	 */
	public function setResultsPerPage($resultsPerPage) {
		$this->resultsPerPage = t3lib_div::intInRange($resultsPerPage, 5);
	}

	/**
	 * Gets the currently showing page's number
	 *
	 * @return	integer	page number currently showing
	 */
	public function getPage() {
		return $this->page;
	}

	/**
	 * Sets the page that should be shown
	 *
	 * @param	integer	page number to show
	 * @return	boid
	 */
	public function setPage($page) {
		$this->page = t3lib_div::intval_positive($page);
	}

	/**
	 * Gets the index of the first result document we're showing
	 *
	 * @return	integer	index of the currently first document showing
	 */
	public function getStartIndex() {
		return ($this->page - 1) * $this->resultsPerPage;
	}

	/**
	 * Gets the index of the last result document we're showing
	 *
	 * @return	integer	index of the currently last document showing
	 */
	public function getEndIndex() {
		return $this->page * $this->resultsPerPage;
	}


	// faceting

	/**
	 * Activates and deactivates faceting for the current query.
	 *
	 * @param	boolean	True to enable faceting, false to disable faceting
	 * @return	void
	 */
	public function setFaceting($faceting = true) {
		if ($faceting) {
			$this->queryParameters['facet'] = 'true';
			$this->queryParameters['facet.mincount'] = $this->solrConfiguration['search.']['faceting.']['minimumCount'];

			if (t3lib_div::inList('count,index,1,0,true,false', $this->solrConfiguration['search.']['faceting.']['sortBy'])) {
				$this->queryParameters['facet.sort'] = $this->solrConfiguration['search.']['faceting.']['sortBy'];
			}
		} else {
			foreach ($this->queryParameters as $key => $value) {
					// remove all facet.* settings
				if (t3lib_div::isFirstPartOfStr($key, 'facet')) {
					unset($this->queryParameters[$key]);
				}

					// remove all f.*.facet.* settings (overrides for individual fields)
				if (t3lib_div::isFirstPartOfStr($key, 'f.') && strpos($key, '.facet.') !== false) {
					unset($this->queryParameters[$key]);
				}
			}
		}
	}

	public function setFacetFields(array $facetFields) {

	}

	public function addFacetFilter($field, $value) {
		// might do the same as addFilter()
	}


	// filter


	/**
	 * Adds a filter parameter.
	 *
	 * @param	string	the filter to add, in the form of field:value
	 * @return	void
	 */
	public function addFilter($filterString) {
			// TODO refactor to split filter field and filter value, @see Drupal
		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['filters']) {
			t3lib_div::devLog('adding filter', 'tx_solr', 0, array($filterString));
		}

		$this->filters[] = $filterString;
	}

	/**
	 * Removes a filter on a field
	 *
	 * @param	string	the field name the filter should be removed for
	 * @return	void
	 */
	public function removeFilter($filterFieldName) {
		foreach ($this->filters as $key => $filterString) {
			if (t3lib_div::isFirstPartOfStr($filterString, $filterFieldName)) {
				unset($this->filters[$key]);
			}
		}
	}

	/**
	 * sets access restrictions for a frontend user
	 *
	 * @param	array	an array of groups a user has been assigned to
	 */
	public function setUserAccessGroups(array $groups) {
		$accessFilter = array();
		foreach ($groups as $group) {
			$accessFilter[] = 'group:"' . $group . '"';
		}
		$accessFilter = implode(' OR ', $accessFilter);

		if (!in_array('-1', $groups)) {
				// if the user is logged in, don't let him find pages that
				// are "hidden at login"
			$accessFilter .= ' -group:"-1"';
		}

		$this->addFilter($accessFilter);
	}

	/**
	 * limits the query to a certain site's content
	 *
	 * @param	string	the site hash
	 */
	public function setSiteHash($siteHash) {
		$this->addFilter('siteHash:"' . $siteHash . '"');
	}


	// sorting

	/**
	 * Adds a sort field and the sorting direction for that field
	 *
	 * @param	string	the field name to sort by
	 * @param	string	either tx_solr_Query::SORT_ASC to sort the field ascending or tx_solr_Query::SORT_DESC to sort descending
	 * @return	void
	 * @throws	InvalidArgumentException	throws an exception if the parameter given is neither tx_solr_Query::SORT_ASC nor tx_solr_Query::SORT_DESC
	 */
	public function addSortField($fieldName, $sort) {
		switch ($sort) {
			case self::SORT_ASC:
			case self::SORT_DESC:
				$this->sortingFields[$fieldName] = $sort;
				break;
			default:
				throw new InvalidArgumentException(
					'Invalid sort direction "' . $sort . '"',
					1235051723
				);
		}
	}

	/**
	 * Gets the currently set sorting fields and their sorting directions
	 *
	 * @return	array	An associative array with the field names as key and their sorting direction as value
	 */
	public function getSortingFields() {
		return $this->sortingFields;
	}


	// sub queries


	public function addSubQuery(tx_solr_Query $query) {
		$this->subQueries[$query->getId()] = $query;
	}

	public function removeSubQuery(tx_solr_Query $query) {
		unset($this->subQueries[$query->getId()]);
	}


	// query parameters

	public function setOmitHeader($omitHeader = true) {
		if ($omitHeader) {
			$this->queryParameters['omitHeader'] = 'true';
		} else {
			unset($this->queryParameters['omitHeader']);
		}
	}

	public function addQueryParameter($parameterName, $parameterValue) {
			// FIXME do some validation
		$this->queryParameters[$parameterName] = $parameterValue;
	}

	public function getKeywords() {
		return $this->keywords;
	}

	public function setKeywords($keywords) {
		$this->keywords = $this->escape($keywords);
	}

	/**
	 * Sets a query field and its boost. If the field doesn't exist yet, it
	 * gets added. Boost is optional, if left out a default boost of 1.0 is
	 * applied.
	 *
	 * @param	string	The field's name
	 * @param	float	Optional field boost
	 * @return	void
	 */
	public function setQueryField($fieldName, $boost = 1.0) {
			// TODO check whether field exists in index ... luke?
		$this->queryFields[$fieldName] = (float) $boost;
	}

	/**
	 * Takes a string of comma separated query fields and _overwrites_ the
	 * currently set query fields. Boost can also be specified in through the
	 * given string.
	 *
	 * Example: "title^5, subtitle^2, content, author^0.5"
	 * This sets the query fields to title with  a boost of 5.0, subtitle with
	 * a boost of 2.0, content with a default boost of 1.0 and the author field
	 * with a boost of 0.5
	 *
	 * @param	string	A string defining which fields to query and their associated boosts
	 * @return	void
	 */
	public function setQueryFieldsFromString($queryFields) {
		$fields = t3lib_div::trimExplode(',', $queryFields, true);

		foreach ($fields as $field) {
			$fieldNameAndBoost = explode('^', $field);

			$boost = 1.0;
			if (isset($fieldNameAndBoost[1])) {
				$boost = floatval($fieldNameAndBoost[1]);
			}

			$this->setQueryField($fieldNameAndBoost[0], $boost);
		}
	}

	/**
	 * formats the set query fields as string to be used in the qf parameter
	 *
	 * @return	string	A string of query fields with their associated boosts
	 */
	public function getQueryFieldsAsString() {
		$queryFieldString = '';

		foreach ($this->queryFields as $fieldName => $fieldBoost) {
			$queryFieldString .= $fieldName;

			if ($fieldBoost != 1.0) {
				$queryFieldString .= '^' . number_format($fieldBoost, 1, '.', '');
			}

			$queryFieldString .= ' ';
		}

		return trim($queryFieldString);
	}

	/**
	 * Builds an array of query parameters to use for the search query.
	 *
	 * @return	array	an array ready to use with query parameters
	 */
	public function getQueryParameters() {
		$queryParameters = array_merge(
			array(
				'fl' => implode(',', $this->returnFields),
				'fq' => $this->filters
			),
			$this->queryParameters
		);

		$queryFieldString = $this->getQueryFieldsAsString();
		if (!empty($queryFieldString)) {
			$queryParameters['qf'] = $queryFieldString;
		}

		return $queryParameters;
	}

	/**
	 * Gets a specific query parameter by its name.
	 *
	 * @param	string	The parameter to return
	 * @return	string	The parameter's value or null if not set
	 */
	public function getQueryParameter($parameterName) {
		$requestedParameter = null;
		$parameters = $this->getQueryParameters();

		if (isset($parameters[$parameterName])) {
			$requestedParameter = $parameters[$parameterName];
		}

		return $requestedParameter;
	}

	/**
	 * Adds a field to the list of fields to query. Also checks whether * is set
	 * for the fields, if so it's removed from the field list.
	 *
	 * @param	string	the field name
	 */
	public function addReturnField($fieldName) {
		if (in_array('*', $this->returnFields)) {
			while($index = array_search('*', $this->returnFields)) {
				unset($this->returnFields[$index]);
			}
		}

			// TODO check whether the field exists (using luke?)

		$this->returnFields[] = $fieldName;
	}

	public function setHighlighting($highlighting = true, $fragmentSize = 200) {

		if ($highlighting) {
			$this->queryParameters['hl'] = 'true';
			$this->queryParameters['hl.fragsize'] = (int) $fragmentSize;

			if (isset($this->solrConfiguration['search.']['highlighting.']['highlightFields'])) {
				$this->queryParameters['hl.fl'] = $this->solrConfiguration['search.']['highlighting.']['highlightFields'];
			}

			$wrap = explode('|', $this->solrConfiguration['search.']['highlighting.']['wrap']);
			$this->queryParameters['hl.simple.pre']  = $wrap[0];
			$this->queryParameters['hl.simple.post'] = $wrap[1];
		} else {
				// remove all hl.* settings
			foreach ($this->queryParameters as $key => $value) {
				if (t3lib_div::isFirstPartOfStr($key, 'hl')) {
					unset($this->queryParameters[$key]);
				}
			}
		}
	}

	public function setSpellchecking($spellchecking = true) {
		if ($spellchecking) {
			$this->queryParameters['spellcheck'] = 'true';
		} else {
			unset($this->queryParameters['spellcheck']);
		}
	}

	public function setSorting($sort = true) {
		if ($sort) {
			$piVars = t3lib_div::_GP('tx_solr');

				// Validate sort parameter
			if (isset($piVars['sort']) && preg_match('/^[a-z0-9_]+ (asc|desc)$/i', $piVars['sort'])) {
				list($sortField) = explode(' ', $piVars['sort']);
				$this->queryParameters['sort'] = $sortField == 'relevancy' ? '' : $piVars['sort'];
			}
		} else {
			unset($this->queryParameters['sort']);
		}
	}

	/**
	 * Enables or disables the debug parameter for the query.
	 *
	 * @param	boolean	Enables debugging when set to true, deactivates debugging when set to false.
	 */
	public function setDebugMode($debugMode = true) {
		if ($debugMode) {
			$this->queryParameters['debugQuery'] = 'true';
		} else {
			unset($this->queryParameters['debugQuery']);
		}
	}


	// output


	public function getQueryLink($linkText, array $additionalQueryParameters = array()) {
		$cObj = t3lib_div::makeInstance('tslib_cObj');

		$prefix = 'tx_solr';
		$getPostParameters = t3lib_div::_GP($prefix);
		$piVars = is_array($getPostParameters) ? $getPostParameters : array();

		$queryParameters = array_merge(
			$piVars,
			array('q' => $this->getKeywords()),
			$additionalQueryParameters
		);
		$queryParameters = $this->removeUnwantedUrlParameters($queryParameters);

		$linkConfiguration = array(
			'useCacheHash'     => false,
			'no_cache'         => false,
			'parameter'        => $this->solrConfiguration['search.']['targetPage'],
			'additionalParams' => t3lib_div::implodeArrayForUrl('', array($prefix => $queryParameters), '', true)
		);

		return $cObj->typoLink($linkText, $linkConfiguration);
	}

	public function getQueryUrl(array $additionalQueryParameters = array()) {
			// TODO find a way to remove duplicate code (@see getQueryLink)
		$cObj = t3lib_div::makeInstance('tslib_cObj');

		$prefix = 'tx_solr';
		$getPostParameters = t3lib_div::_GP($prefix);
		$piVars = is_array($getPostParameters) ? $getPostParameters : array();

		$queryParameters = array_merge(
			$piVars,
			array('q' => $this->getKeywords()),
			$additionalQueryParameters
		);
		$queryParameters = $this->removeUnwantedUrlParameters($queryParameters);

		$linkConfiguration = array(
			'useCacheHash'     => false,
			'no_cache'         => false,
			'parameter'        => $this->solrConfiguration['search.']['targetPage'],
			'additionalParams' => t3lib_div::implodeArrayForUrl('', array($prefix => $queryParameters), '', true)
		);

		$cObj->typoLink('|', $linkConfiguration);

		return $cObj->lastTypoLinkUrl;
	}

	public function removeUnwantedUrlParameters($urlParameters) {
		$unwantedUrlParameters = array('resultsPerPage', 'page');

		foreach ($unwantedUrlParameters as $unwantedUrlParameter) {
			unset($urlParameters[$unwantedUrlParameter]);
		}

		return $urlParameters;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_query.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_query.php']);
}

?>