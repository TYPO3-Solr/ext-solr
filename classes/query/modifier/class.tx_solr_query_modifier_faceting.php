<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo@typo3.org>
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

require_once($GLOBALS['PATH_solr'] . 'interfaces/interface.tx_solr_querymodifier.php');


/**
 * Modifies a query to add faceting parameters
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @author 	Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author 	Sebastian Kurfuerst <sebastian@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_query_modifier_Faceting implements tx_solr_QueryModifier {

	protected $configuration;
	protected $facetParameters = array();
	protected $facetFilters    = array();

	/**
	 * constructor for class tx_solr_query_modifier_Faceting
	 */
	public function __construct() {
		$this->configuration = tx_solr_Util::getSolrConfiguration();
	}

	/**
	 * Modifies the given query and adds the parameters necessary for faceted
	 * search.
	 *
	 * @param	tx_solr_Query	The query to modify
	 * @return	tx_solr_Query	The modified query with faceting parameters
	 */
	public function modifyQuery(tx_solr_Query $query) {
		$this->buildFacetingParameters();
		$this->addFacetQueryFilters();


		foreach ($this->facetParameters as $facetParameter => $value) {
			$query->addQueryParameter($facetParameter, $value);
		}

		foreach ($this->facetFilters as $filter) {
			$query->addFilter($filter);
		}

		return $query;
	}

	/**
	 * Delegates the parameter building to specialized functions depending on
	 * the type of facet to add.
	 *
	 * @return	array	An array of query parameters
	 */
	protected function buildFacetingParameters() {
		$facetingParameters = array();
		$configuredFacets = $this->configuration['search.']['faceting.']['facets.'];

		foreach ($configuredFacets as $facetName => $facetConfiguration) {
			$facetName = substr($facetName, 0, -1);

			if (empty($facetConfiguration['field'])) {
					// TODO later check for query and date, too
				continue;
			}

			if (!empty($facetConfiguration['field'])) {
				$this->buildFacetFieldParameters($facetConfiguration);
			}
		}
	}

	/**
	 * Builds facet parameters for field facets
	 *
	 * @param	array	A facet configuration
	 */
	protected function buildFacetFieldParameters(array $facetConfiguration) {
			// very simple for now, may add overrides f.<field_name>.facet.* later
		if ($facetConfiguration['keepAllOptionsOnSelection'] == 1) {
			$this->facetParameters['facet.field'][] =
				'{!ex=' . $facetConfiguration['field'] . '}'
				. $facetConfiguration['field'];
		} else {
			$this->facetParameters['facet.field'][] = $facetConfiguration['field'];
		}

		if (in_array($facetConfiguration['sortBy'], array('alpha', 'index', 'lex'))) {
			$this->facetParameters['f.' . $facetConfiguration['field'] . '.facet.sort'] = 'lex';
		}
	}

	/**
	 * Adds filters specified through HTTP GET as filter query parameters to
	 * the Solr query.
	 *
	 */
	protected function addFacetQueryFilters() {
		$resultParameters = t3lib_div::_GET('tx_solr');

			// format for filter URL parameter:
			// tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
		if (is_array($resultParameters['filter'])) {
			$filters = array_map('urldecode', $resultParameters['filter']);
				// $filters look like array('name:value1','name:value2','fieldname2:lorem')
			$configuredFacets = $this->getConfigurredFacets();

				// first group the filters by filterName - so that we can
				// dicide later wether we need to do AND or OR for multiple
				// filters for a certain field
				// $filtersByFieldName look like array('name' => array ('value1', 'value2'), 'fieldname2' => array('lorem'))
			$filtersByFieldName = array();
			foreach ($filters as $filter) {
				list($filterFieldName, $filterValue) = explode(':', $filter);
				if (in_array($filterFieldName, $configuredFacets)) {
					$filtersByFieldName[$filterFieldName][] = $filterValue;
				}
			}

			foreach ($filtersByFieldName as $fieldName => $filterValues) {
				$fieldConfiguration = $this->configuration['search.']['faceting.']['facets.'][$fieldName . '.'];

				$tag = '';
				if ($fieldConfiguration['keepAllOptionsOnSelection'] == 1) {
					$tag = '{!tag=' . addslashes( $fieldConfiguration['field'] ) . '}';
				}

				$filterParts = array();
				foreach ($filterValues as $filterValue) {
					if ($fieldConfiguration['filterParameterParser']) {
						$parserClassname = 'tx_solr_query_filterparser_' .
							ucfirst($fieldConfiguration['filterParameterParser']);
						$filterParser = t3lib_div::makeInstance($parserClassname);

						$filterOptions= $fieldConfiguration['renderer.'];

						$filterValue = $filterParser->parseFilter($filterValue, $filterOptions);
						$filterParts[] = $fieldConfiguration['field'] . ':' . addslashes( $filterValue );
					} else {
						$filterParts[] = $fieldConfiguration['field'] . ':"' . addslashes( $filterValue ) . '"';
					}
				}

				$operator = ($fieldConfiguration['operator'] == 'OR') ? ' OR ' : ' AND ';
				$this->facetFilters[] = $tag . '(' . implode($operator, $filterParts) . ')';
			}
		}
	}

	/**
	 * Gets the facets as configured through TypoScript
	 *
	 * @return	array	An array of facet names as specified in TypoScript
	 */
	protected function getConfigurredFacets() {
		$configuredFacets = $this->configuration['search.']['faceting.']['facets.'];
		$facets = array();

		foreach ($configuredFacets as $facetName => $facetConfiguration) {
			$facetName = substr($facetName, 0, -1);

			if (empty($facetConfiguration['field'])) {
					// TODO later check for query and date, too
				continue;
			}

			$facets[] = $facetName;
		}

		return $facets;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/query/modifier/class.tx_solr_query_modifier_faceting.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/query/modifier/class.tx_solr_query_modifier_faceting.php']);
}

?>