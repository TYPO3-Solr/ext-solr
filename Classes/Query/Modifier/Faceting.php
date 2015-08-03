<?php
namespace ApacheSolrForTypo3\Solr\Query\Modifier;

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

use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Util;
use Tx_Solr_QueryModifier;


/**
 * Modifies a query to add faceting parameters
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Daniel Poetzinger <poetzinger@aoemedia.de>
 * @author Sebastian Kurfuerst <sebastian@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Faceting implements Tx_Solr_QueryModifier {

	protected $configuration;

	protected $facetParameters = array();

	protected $facetFilters = array();

	protected $facetRendererFactory = NULL;


	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		$this->configuration = Util::getSolrConfiguration();
		$this->facetRendererFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('Tx_Solr_Facet_FacetRendererFactory', $this->configuration['search.']['faceting.']['facets.']);
	}

	/**
	 * Modifies the given query and adds the parameters necessary for faceted
	 * search.
	 *
	 * @param Query $query The query to modify
	 * @return Query The modified query with faceting parameters
	 */
	public function modifyQuery(Query $query) {
		$query->setFaceting();
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
	 */
	protected function buildFacetingParameters() {
		$facetParameters  = array();
		$configuredFacets = $this->configuration['search.']['faceting.']['facets.'];

		foreach ($configuredFacets as $facetName => $facetConfiguration) {
			$facetName = substr($facetName, 0, -1);

			$facetParameterBuilder = $this->facetRendererFactory->getQueryFacetBuilderByFacetName($facetName);
			if (!is_null($facetParameterBuilder)) {
				$facetParameters = $facetParameterBuilder->buildFacetParameters($facetName, $facetConfiguration);
			} else {
				$facetParameters = $this->buildFacetParameters($facetConfiguration);
			}

			$this->facetParameters = array_merge_recursive($this->facetParameters, $facetParameters);
		}
	}

	/**
	 * Builds facet parameters for field facets
	 *
	 * @param array $facetConfiguration The facet's configuration
	 * @return array
	 */
	protected function buildFacetParameters(array $facetConfiguration) {
		$facetParameters = array();

		// simple for now, may add overrides f.<field_name>.facet.* later

		if ($this->configuration['search.']['faceting.']['keepAllFacetsOnSelection'] == 1) {
			$facets = array();
			foreach ($this->configuration['search.']['faceting.']['facets.'] as $facet) {
				$facets[] = $facet['field'];
			}

			$facetParameters['facet.field'][] =
				'{!ex=' . implode(',', $facets) . '}'
				. $facetConfiguration['field'];
		} elseif ($facetConfiguration['keepAllOptionsOnSelection'] == 1) {
			$facetParameters['facet.field'][] =
				'{!ex=' . $facetConfiguration['field'] . '}'
				. $facetConfiguration['field'];
		} else {
			$facetParameters['facet.field'][] = $facetConfiguration['field'];
		}

		if (in_array($facetConfiguration['sortBy'], array('alpha', 'index', 'lex'))) {
			$facetParameters['f.' . $facetConfiguration['field'] . '.facet.sort'] = 'lex';
		}

		return $facetParameters;
	}

	/**
	 * Adds filters specified through HTTP GET as filter query parameters to
	 * the Solr query.
	 *
	 */
	protected function addFacetQueryFilters() {
		$resultParameters = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET('tx_solr');

		// format for filter URL parameter:
		// tx_solr[filter]=$facetName0:$facetValue0,$facetName1:$facetValue1,$facetName2:$facetValue2
		if (is_array($resultParameters['filter'])) {
			$filters = array_map('urldecode', $resultParameters['filter']);
			// $filters look like array('name:value1','name:value2','fieldname2:foo')
			$configuredFacets = $this->getConfiguredFacets();

			// first group the filters by facetName - so that we can
			// decide later whether we need to do AND or OR for multiple
			// filters for a certain facet/field
			// $filtersByFacetName look like array('name' => array ('value1', 'value2'), 'fieldname2' => array('foo'))
			$filtersByFacetName = array();
			foreach ($filters as $filter) {
				// only split by the first colon to allow using colons in the filter value itself
				list($filterFacetName, $filterValue) = explode(':', $filter, 2);
				if (in_array($filterFacetName, $configuredFacets)) {
					$filtersByFacetName[$filterFacetName][] = $filterValue;
				}
			}

			foreach ($filtersByFacetName as $facetName => $filterValues) {
				$facetConfiguration = $this->configuration['search.']['faceting.']['facets.'][$facetName . '.'];

				$filterEncoder = $this->facetRendererFactory->getFacetFilterEncoderByFacetName($facetName);

				$tag = '';
				if ($facetConfiguration['keepAllOptionsOnSelection'] == 1
					|| $this->configuration['search.']['faceting.']['keepAllFacetsOnSelection'] == 1
				) {
					$tag = '{!tag=' . addslashes($facetConfiguration['field']) . '}';
				}

				$filterParts = array();
				foreach ($filterValues as $filterValue) {
					if (!is_null($filterEncoder)) {
						$filterOptions = $facetConfiguration[$facetConfiguration['type'] . '.'];
						if (empty($filterOptions)) {
							$filterOptions = array();
						}

						$filterValue = $filterEncoder->decodeFilter($filterValue, $filterOptions);
						$filterParts[] = $facetConfiguration['field'] . ':' . $filterValue;
					} else {
						$filterParts[] = $facetConfiguration['field'] . ':"' . addslashes($filterValue) . '"';
					}
				}

				$operator = ($facetConfiguration['operator'] == 'OR') ? ' OR ' : ' AND ';
				$this->facetFilters[] = $tag . '(' . implode($operator, $filterParts) . ')';
			}
		}
	}

	/**
	 * Gets the facets as configured through TypoScript
	 *
	 * @return array An array of facet names as specified in TypoScript
	 */
	protected function getConfiguredFacets() {
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

