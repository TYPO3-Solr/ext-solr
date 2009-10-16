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
 * Modifies a query to add faceting parameters
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_querymodifier_Faceting implements tx_solr_QueryModifier {

	protected $configuration;
	protected $facetParameters = array();
	protected $facetFilters    = array();

	/**
	 * constructor for class tx_solr_querymodifier_Faceting
	 */
	public function __construct() {
		$this->configuration = tx_solr_Util::getSolrConfiguration();
	}

	/**
	 * Modifies the given query and adds the parameters necessary for faceted
	 * search
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
	 * Builds facet parameters for date field facets
	 *
	 * @param	array	A facet configuration
	 * @return	void
	 */
	protected function buildFacetDateParameters() {
		// not implemented yet
	}

	/**
	 * Builds facet parameters for field facets
	 *
	 * @param	array	A facet configuration
	 * @return	void
	 */
	protected function buildFacetFieldParameters(array $facetConfiguration) {
			// very simple for now, may add overrides f.<field_name>.facet.* later
		$this->facetParameters['facet.field'][] = $facetConfiguration['field'];
	}

	/**
	 * Builds facet parameters for query facets
	 *
	 * @param	array	A facet configuration
	 * @return	void
	 */
	protected function buildFacetQueryParameters() {

	}

	/**
	 * Adds filters specified through HTTP GET as filter query parameters to
	 * the Solr query.
	 *
	 * @return void
	 */
	protected function addFacetQueryFilters() {
		$resultParameters = t3lib_div::_GET('tx_solr');

			// format for filter URL parameter:
			// tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
		if (isset($resultParameters['filter'])) {
			$filters = json_decode($resultParameters['filter']);
			$configuredFacets = $this->getConfigurredFacets();

			foreach ($filters as $filter) {
				list($filterName, $filterValue) = explode(':', $filter);

				if (in_array($filterName, $configuredFacets)) {
						// TODO support query and date facets
					$this->facetFilters[] = $this->configuration['search.']['faceting.']['facets.'][$filterName . '.']['field']
						. ':"' . $filterValue . '"';
				}
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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/querymodifier/class.tx_solr_querymodifier_faceting.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/querymodifier/class.tx_solr_querymodifier_faceting.php']);
}

?>