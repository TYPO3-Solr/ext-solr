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
 * A Solr search query
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Query {

	// FIXME extract link building from the query, it's not the query's domain

	const SORT_ASC  = 'ASC';
	const SORT_DESC = 'DESC';

	const OPERATOR_AND = 'AND';
	const OPERATOR_OR  = 'OR';

	/**
	 * Used to identify the queries.
	 *
	 * @var integer
	 */
	protected static $idCount = 0;
	protected $id;

	protected $solrConfiguration;

	protected $keywords;
	protected $keywordsRaw;
	protected $filters = array();
	protected $sorting;

	private   $rawQueryString = FALSE;
	protected $queryString;

	protected $queryParameters = array();

	protected $resultsPerPage;
	protected $page;

	protected $linkTargetPageId;

	/**
	 * holds the query fields with their associated boosts. The key represents
	 * the field name, value represents the field's boost.
	 *
	 * @var array
	 */
	protected $queryFields = array();
	protected $fieldList = array();
	protected $filterFields;
	protected $sortingFields;

	protected $subQueries = array();

	/**
	 * Constructor
	 *
	 */
	public function __construct($keywords) {
		$this->solrConfiguration = tx_solr_Util::getSolrConfiguration();

		$this->fieldList = array('*', 'score');
		$this->setKeywords($keywords);
		$this->sorting  = '';

		if (!empty($this->solrConfiguration['search.']['query.']['fields'])) {
			$this->setQueryFieldsFromString($this->solrConfiguration['search.']['query.']['fields']);
		}

		$this->linkTargetPageId = $this->solrConfiguration['search.']['targetPage'];
		if (empty($this->linkTargetPageId)) {
			$this->linkTargetPageId = $GLOBALS['TSFE']->id;
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
		$this->queryString = $this->keywords;
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
	 * Sets whether a raw query sting should be used, that is, whether the query
	 * string should be escaped or not.
	 *
	 * @param	boolean	$useRawQueryString TRUE to use raw queries (like Lucene Query Language) or FALSE for regular, escaped queries
	 */
	public function useRawQueryString($useRawQueryString) {
		$this->rawQueryString = (boolean) $useRawQueryString;
	}

	/**
	 * Builds the query string which is then used for Solr's q parameters
	 *
	 * @return	string	Solr query string
	 */
	public function getQueryString() {
		if (!$this->rawQueryString) {
			$this->buildQueryString();
		}

		return $this->queryString;
	}

	/**
	 * Sets the query string without any escaping.
	 *
	 * Be cautious with this function!
	 *
	 * @param string $queryString The raw query string.
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
					// multiple words

				$stringLength = strlen($string);
				if ($string{0} == '"' && $string{$stringLength - 1} == '"') {
						// phrase
					$string = trim($string, '"');
					$string = $this->escapePhrase($string);
				} else {
					$string = $this->escapeSpecialCharacters($string);
				}
			} else {
				$string = $this->escapeSpecialCharacters($string);
			}
		}

		return $string;
	}

	/**
	 * Escapes characters with special meanings in Lucene query syntax.
	 *
	 * @param	string	$value Unescaped - "dirty" - string
	 * @return	string	Escaped - "clean" - string
	 */
	protected function escapeSpecialCharacters($value) {
			// list taken from http://lucene.apache.org/java/3_3_0/queryparsersyntax.html#Escaping%20Special%20Characters
			// not escaping *, &&, ||, ?, -, !, + though
		$pattern = '/(\(|\)|\{|}|\[|]|\^|"|~|:|\\\)/';
		$replace = '\\\$1';

		return preg_replace($pattern, $replace, $value);
	}

	/**
	 * Escapes a value meant to be contained in a phrase with characters with
	 * special meanings in Lucene query syntax.
	 *
	 * @param	string	$value Unescaped - "dirty" - string
	 * @return	string	Escaped - "clean" - string
	 */
	protected function escapePhrase($value) {
		$pattern = '/("|\\\)/';
		$replace = '\\\$1';

		return '"' . preg_replace($pattern, $replace, $value) . '"';
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
	 * @return	void
	 */
	public function setResultsPerPage($resultsPerPage) {
		$this->resultsPerPage = max(intval($resultsPerPage), 1);
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
		$this->page = max(intval($page), 0);
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


	// query elevation


	/**
	 * Activates and deactivates query elevation for the current query.
	 *
	 * @param	boolean	True to enable query elevation (default), FALSE to disable query elevation.
	 * @param	boolean	Optionaly force elevation so that the elevated documents are always on top regardless of sorting, default to TRUE.
	 * @return	void
	 */
	public function setQueryElevation($elevation = TRUE, $forceElevation = TRUE) {
		if ($elevation) {
			$this->queryParameters['enableElevation'] = 'true';
			$this->queryParameters['forceElevation']  = 'true';
		} else {
			if (isset($this->queryParameters['enableElevation'])) {
				unset($this->queryParameters['enableElevation']);
				unset($this->queryParameters['forceElevation']);
			}
		}
	}


	// grouping


	/**
	 * Activates and deactivates grouping for the current query.
	 *
	 * @param boolean $grouping TRUE to enable grouping, FALSE to disable grouping
	 * @return void
	 */
	public function setGrouping($grouping = TRUE) {
		if ($grouping) {
			$this->queryParameters['group']         = 'true';
			$this->queryParameters['group.format']  = 'grouped';
			$this->queryParameters['group.ngroups'] = 'true';
		} else {
			foreach ($this->queryParameters as $key => $value) {
					// remove all group.* settings
				if (t3lib_div::isFirstPartOfStr($key, 'group')) {
					unset($this->queryParameters[$key]);
				}
			}
		}
	}

	/**
	 * Sets the number of groups to return per group field or group query
	 *
	 * Internally uses the rows parameter.
	 *
	 * @param integer $numberOfGroups Number of groups per group.field or group.query
	 */
	public function setNumberOfGroups($numberOfGroups) {
		$this->setResultsPerPage($numberOfGroups);
	}

	/**
	 * Gets the number of groups to return per group field or group query
	 *
	 * Internally uses the rows parameter.
	 *
	 * @return integer Number of groups per group.field or group.query
	 */
	public function getNumberOfGroups() {
		return $this->getResultsPerPage();
	}

	/**
	 * Adds a field that should be used for grouping.
	 *
	 * @param string $fieldName Name of a field for grouping
	 */
	public function addGroupField($fieldName) {
		if (!isset($this->queryParameters['group.field'])) {
			$this->queryParameters['group.field'] = array();
		}

		$this->queryParameters['group.field'][] = $fieldName;
	}

	/**
	 * Gets the fields set for grouping.
	 *
	 * @return array An array of fields set for grouping.
	 */
	public function getGroupFields() {
		$groupFields = array();

		if (isset($this->queryParameters['group.field'])) {
			$groupFields = $this->queryParameters['group.field'];
		}

		return $groupFields;
	}

	/**
	 * Adds a query that should be used for grouping.
	 *
	 * @param string $query Lucene query for grouping
	 */
	public function addGroupQuery($query) {
		if (!isset($this->queryParameters['group.query'])) {
			$this->queryParameters['group.query'] = array();
		}

		$this->queryParameters['group.query'][] = $query;
	}

	/**
	 * Gets the queries set for grouping.
	 *
	 * @return array An array of queries set for grouping.
	 */
	public function getGroupQueries() {
		$groupQueries = array();

		if (isset($this->queryParameters['group.query'])) {
			$groupQueries = $this->queryParameters['group.query'];
		}

		return $groupQueries;
	}

	/**
	 * Sets the maximum number of results to be returned per group.
	 *
	 * @param integer $numberOfResults Maximum number of results per group to return
	 */
	public function setNumberOfResultsPerGroup($numberOfResults) {
		$numberOfResults = max(intval($numberOfResults), 0);

		$this->queryParameters['group.limit'] = $numberOfResults;
	}

	/**
	 * Gets the maximum number of results to be returned per group.
	 *
	 * @return integer Maximum number of results per group to return
	 */
	public function getNumberOfResultsPerGroup() {
			// default if nothing else set is 1, @see http://wiki.apache.org/solr/FieldCollapsing
		$numberOfResultsPerGroup = 1;

		if (!empty($this->queryParameters['group.limit'])) {
			$numberOfResultsPerGroup = $this->queryParameters['group.limit'];
		}

		return $numberOfResultsPerGroup;
	}


	// faceting


	/**
	 * Activates and deactivates faceting for the current query.
	 *
	 * @param	boolean	TRUE to enable faceting, FALSE to disable faceting
	 * @return	void
	 */
	public function setFaceting($faceting = TRUE) {
		if ($faceting) {
			$this->queryParameters['facet'] = 'true';
			$this->queryParameters['facet.mincount'] = $this->solrConfiguration['search.']['faceting.']['minimumCount'];

			if (t3lib_div::inList('count,index,alpha,lex,1,0,true,false', $this->solrConfiguration['search.']['faceting.']['sortBy'])) {
				$sorting = $this->solrConfiguration['search.']['faceting.']['sortBy'];

					// alpha and lex alias for index
				if ($sorting == 'alpha' || $sorting == 'lex') {
					$sorting = 'index';
				}

				$this->queryParameters['facet.sort'] = $sorting;
			}
		} else {
			foreach ($this->queryParameters as $key => $value) {
					// remove all facet.* settings
				if (t3lib_div::isFirstPartOfStr($key, 'facet')) {
					unset($this->queryParameters[$key]);
				}

					// remove all f.*.facet.* settings (overrides for individual fields)
				if (t3lib_div::isFirstPartOfStr($key, 'f.') && strpos($key, '.facet.') !== FALSE) {
					unset($this->queryParameters[$key]);
				}
			}
		}
	}

	/**
	 * Sets facet fields for a query.
	 *
	 * @param array $facetFields Array of field names
	 */
	public function setFacetFields(array $facetFields) {
		$this->queryParameters['facet.field'] = array();

		foreach($facetFields as $facetField) {
			$this->addFacetField($facetField);
		}
	}

	/**
	 * Adds a single facet field.
	 *
	 * @param string $facetField field name
	 */
	public function addFacetField($facetField) {
		$this->queryParameters['facet.field'][] = $facetField;
	}

	public function addFacetFilter($field, $value) {
		// might do the same as addFilter()
	}


	// filter


	/**
	 * Adds a filter parameter.
	 *
	 * @param	string	The filter to add, in the form of field:value
	 * @return	void
	 */
	public function addFilter($filterString) {
			// TODO refactor to split filter field and filter value, @see Drupal
		if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['filters']) {
			t3lib_div::devLog('adding filter', 'solr', 0, array($filterString));
		}

		$this->filters[] = $filterString;
	}

	/**
	 * Removes a filter on a field
	 *
	 * @param	string	The field name the filter should be removed for
	 * @return	void
	 */
	public function removeFilter($filterFieldName) {
		foreach ($this->filters as $key => $filterString) {
			if (t3lib_div::isFirstPartOfStr($filterString, $filterFieldName . ':')) {
				unset($this->filters[$key]);
			}
		}
	}

	/**
	 * Gets all currently applied filters.
	 *
	 * @return	array	Array of filters
	 */
	public function getFilters() {
		return $this->filters;
	}

	/**
	 * Sets access restrictions for a frontend user.
	 *
	 * @param	array	an array of groups a user has been assigned to
	 */
	public function setUserAccessGroups(array $groups) {
		$groups = array_map('intval', $groups);
		$groups[] = 0; // always grant access to public documents
		$groups = array_unique($groups);
		sort($groups, SORT_NUMERIC);

		$accessFilter = '{!typo3access}' . implode(',', $groups);

		foreach ($this->filters as $key => $filter) {
			if (t3lib_div::isFirstPartOfStr($filter, '{!typo3access}')) {
				unset($this->filters[$key]);
			}
		}

		$this->addFilter($accessFilter);
	}

	/**
	 * Limits the query to certain sites
	 *
	 * @param array $allowedSites Comma-separated list of domains
	 */
	public function setSiteHashFilter($allowedSites) {
		$allowedSites = t3lib_div::trimExplode(',', $allowedSites);
		$filters      = array();

		foreach($allowedSites as $site) {
			$siteHash = tx_solr_Util::getSiteHashForDomain($site);

			$filters[] = 'siteHash:"' . $siteHash . '"';
		}

		$this->addFilter(implode(' OR ', $filters));
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


	/**
	 * Gets the list of fields a query will return.
	 *
	 * @return	array	Array of fieldnames the query will return
	 */
	public function getFieldList() {
		return $this->fieldList;
	}

	/**
	 * Sets the fields to return by a query.
	 *
	 * @param	array|string	$fieldList an array or comma-separated list of fieldnames
	 * @throws	UnexpectedValueException on parameters other than comma-separated lists and arrays
	 */
	public function setFieldList($fieldList = array('*', 'score')) {
		if (is_string($fieldList)) {
			$fieldList = t3lib_div::trimExplode(',', $fieldList);
		}

		if (!is_array($fieldList) || empty($fieldList)) {
			throw new UnexpectedValueException(
				'Field list must be a comma-separated list or array and must not be empty.',
				1310740308
			);
		}

		$this->fieldList = $fieldList;
	}

	/**
	 * Adds a field to the list of fields to return. Also checks whether * is
	 * set for the fields, if so it's removed from the field list.
	 *
	 * @param	string	the field name
	 */
	public function addReturnField($fieldName) {
		if (in_array('*', $this->fieldList)) {
			$this->fieldList = array_diff($this->fieldList, array('*'));
		}

		$this->fieldList[] = $fieldName;
	}

	/**
	 * Sets the fields returned in the documents.
	 *
	 * @param	array|string	Accepts an array of return field names or a commy separated list of field names
	 * @deprecated	Use setFieldList() instead
	 */
	public function setReturnFields($returnFields) {
		$this->setFieldList($returnFields);
	}

	/**
	 * Gets the query type, Solr's qt parameter.
	 *
	 * @return	string	Query type, qt parameter.
	 */
	public function getQueryType() {
		return $this->queryParameters['qt'];
	}

	/**
	 * Sets the query type, Solr's qt parameter.
	 *
	 * @param	string|boolean	$queryType String query type or boolean FALSE to disable / reset the qt parameter.
	 * @see	http://wiki.apache.org/solr/CoreQueryParameters#qt
	 */
	public function setQueryType($queryType) {
		if ($queryType) {
			$this->queryParameters['qt'] = $queryType;
		} else {
			unset($this->queryParameters['qt']);
		}
	}

	/**
	 * Sets the query operator to AND or OR. Unsets the query opertor (actually
	 * sets it back to default) for FALSE.
	 *
	 * @param	string|boolean	$operator AND or OR, FALSE to unset
	 */
	public function setOperator($operator) {
		if (in_array($operator, array(self::OPERATOR_AND, self::OPERATOR_OR))) {
			$this->queryParameters['q.op'] = $operator;
		}

		if ($operator === FALSE) {
			unset($this->queryParameters['q.op']);
		}
	}

	/**
	 * Gets the alternative query, Solr's q.alt parameter.
	 *
	 * @return	string	Alternative query, q.alt parameter.
	 */
	public function getAlternativeQuery() {
		return $this->queryParameters['q.alt'];
	}

	/**
	 * Sets an alternative query, Solr's q.alt parameter.
	 *
	 * This query supports the complete Lucene Query Language.
	 *
	 * @param	mixed	$alternativeQuery String alternative query or boolean FALSE to disable / reset the q.alt parameter.
	 * @see	http://wiki.apache.org/solr/DisMaxQParserPlugin#q.alt
	 */
	public function setAlternativeQuery($alternativeQuery) {
		if ($alternativeQuery) {
			$this->queryParameters['q.alt'] = $alternativeQuery;
		} else {
			unset($this->queryParameters['q.alt']);
		}
	}

	public function setOmitHeader($omitHeader = TRUE) {
		if ($omitHeader) {
			$this->queryParameters['omitHeader'] = 'true';
		} else {
			unset($this->queryParameters['omitHeader']);
		}
	}

	public function addQueryParameter($parameterName, $parameterValue) {
		$this->queryParameters[$parameterName] = $parameterValue;
	}

	public function getKeywords() {
		return $this->keywords;
	}

	/**
	 * Gets the cleaned keywords so that it can be used in templates f.e.
	 *
	 * @return string The cleaned keywords.
	 */
	public function getKeywordsCleaned() {
		return self::cleanKeywords($this->keywordsRaw);
	}

	public function getKeywordsRaw() {
		return $this->keywordsRaw;
	}

	public function setKeywords($keywords) {
		$this->keywords    = $this->escape($keywords);
		$this->keywordsRaw = $keywords;
	}

	public static function cleanKeywords($keywords) {
		$keywords = trim($keywords);
		$keywords = t3lib_div::removeXSS($keywords);
		$keywords = htmlentities($keywords, ENT_QUOTES, $GLOBALS['TSFE']->metaCharset);

		// escape triple hashes as they are used in the template engine
		// TODO remove after switching to fluid templates
		$keywords = tx_solr_Template::escapeMarkers($keywords);

		return $keywords;
	}

	/**
	 * Sets the minimum match mm parameter
	 *
	 * @param	mixed	Minimum match parameter as string or boolean FALSE to disable / reset the mm parameter
	 * @see	http://wiki.apache.org/solr/DisMaxRequestHandler#mm_.28Minimum_.27Should.27_Match.29
	 */
	public function setMinimumMatch($minimumMatch) {
		if ($minimumMatch !== FALSE) {
				// hard to validate
			$this->queryParameters['mm'] = $minimumMatch;
		} else {
			unset($this->queryParameters['mm']);
		}
	}

	/**
	 * Sets the boost function bf parameter
	 *
	 * @param	mixed	boost function parameter as string or boolean FALSE to disable / reset the bf parameter
	 * @see	http://wiki.apache.org/solr/DisMaxRequestHandler#bf_.28Boost_Functions.29
	 */
	public function setBoostFunction($boostFunction) {
		if ($boostFunction) {
			$this->queryParameters['bf'] = $boostFunction;
		} else {
			unset($this->queryParameters['bf']);
		}
	}

	/**
	 * Sets the boost query bq parameter
	 *
	 * @param	mixed	boost query parameter as string or boolean FALSE to disable / reset the bq parameter
	 * @see	http://wiki.apache.org/solr/DisMaxQParserPlugin#bq_.28Boost_Query.29
	 */
	public function setBoostQuery($boostQuery) {
		if ($boostQuery) {
			$this->queryParameters['bq'] = $boostQuery;
		} else {
			unset($this->queryParameters['bq']);
		}
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
		$fields = t3lib_div::trimExplode(',', $queryFields, TRUE);

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
				'fl' => implode(',', $this->fieldList),
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
	 * @return	string	The parameter's value or NULL if not set
	 */
	public function getQueryParameter($parameterName) {
		$requestedParameter = NULL;
		$parameters = $this->getQueryParameters();

		if (isset($parameters[$parameterName])) {
			$requestedParameter = $parameters[$parameterName];
		}

		return $requestedParameter;
	}

	/**
	 * Enables or disables highlighting of search terms in result teasers.
	 *
	 * @param	boolean	$highlighting Enables highlighting when set to TRUE, deactivates highlighting when set to FALSE.
	 * @param	boolean	$fragmentSize Size, in characters, of fragments to consider for highlighting.
	 * @see	http://wiki.apache.org/solr/HighlightingParameters
	 * @return	void
	 */
	public function setHighlighting($highlighting = TRUE, $fragmentSize = 200) {

		if ($highlighting) {
			$this->queryParameters['hl'] = 'true';
			$this->queryParameters['hl.fragsize'] = (int) $fragmentSize;

			if (isset($this->solrConfiguration['search.']['results.']['resultsHighlighting.']['highlightFields'])) {
				$this->queryParameters['hl.fl'] = $this->solrConfiguration['search.']['results.']['resultsHighlighting.']['highlightFields'];
			}

			$wrap = explode('|', $this->solrConfiguration['search.']['results.']['resultsHighlighting.']['wrap']);
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

	/**
	 * Enables or disables spellchecking for the query.
	 *
	 * @param	boolean	Enables spellchecking when set to TRUE, deactivates spellchecking when set to FALSE.
	 */
	public function setSpellchecking($spellchecking = TRUE) {
		if ($spellchecking) {
			$this->queryParameters['spellcheck'] = 'true';
			$this->queryParameters['spellcheck.collate'] = 'true';
		} else {
			unset($this->queryParameters['spellcheck']);
			unset($this->queryParameters['spellcheck.collate']);
		}
	}

	/**
	 * Sets the sort parameter.
	 *
	 * A sort ordering must include a field name (or the pseudo-field score),
	 * followed by a space,
	 * followed by a sort direction (asc or desc).
	 *
	 * Multiple sort orderings can be separated by a comma,
	 * ie: <field name> <direction>[,<field name> <direction>]...
	 *
	 * @param	string|boolean	$sorting Either a comma-separated list of sort fields and directions or FALSE to reset sorting to the default behavior (sort by score / relevance)
	 * @see	http://wiki.apache.org/solr/CommonQueryParameters#sort
	 */
	public function setSorting($sorting) {
		if ($sorting) {
			$sortParameter = $sorting;

			list($sortField) = explode(' ', $sorting);
			if ($sortField == 'relevance') {
				$sortParameter = '';
			}

			$this->queryParameters['sort'] =  $sortParameter;
		} else {
			unset($this->queryParameters['sort']);
		}
	}

	/**
	 * Enables or disables the debug parameter for the query.
	 *
	 * @param	boolean	Enables debugging when set to TRUE, deactivates debugging when set to FALSE.
	 */
	public function setDebugMode($debugMode = TRUE) {
		if ($debugMode) {
			$this->queryParameters['debugQuery'] = 'true';
			$this->queryParameters['echoParams'] = 'all';
		} else {
			unset($this->queryParameters['debugQuery']);
			unset($this->queryParameters['echoParams']);
		}
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_query.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_query.php']);
}

?>
