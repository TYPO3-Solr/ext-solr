<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ingo Renner <ingo@typo3.org>
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
 * A facet option
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class tx_solr_facet_FacetOption {

	/**
	 * The query which is going to be sent to Solr when a user selects a facet.
	 *
	 * @var tx_solr_Query
	 */
	protected $query;

	/**
	 * Facet name.
	 *
	 * @var string
	 */
	protected $facetName;

	/**
	 * Facet option value
	 *
	 * @var integer|string
	 */
	protected $value;

	/**
	 * Number of results that will be returned when applying this facet
	 * option's filter to the query.
	 *
	 * @var integer
	 */
	protected $numberOfResults;


	/**
	 * Constructor.
	 *
	 * @param tx_solr_Query $query Query instance used to build links.
	 * @param string $facetName Facet Name
	 * @param integer|string $facetOptionValue Facet option value
	 */
	public function __construct(tx_solr_Query $query, $facetName, $facetOptionValue, $facetOptionNumberOfResults) {
		$this->query = $query;

		$this->facetName       = $facetName;
		$this->value           = $facetOptionValue;
		$this->numberOfResults = intval($facetOptionNumberOfResults);
	}

	/**
	 * Renders a single facet option according to the facet's rendering
	 * instructions that may have been configured.
	 *
	 * @param array $facetConfiguration The facet's configuration
	 * @return string The facet option rendered according to rendering instructions if available
	 */
	public function render(array $facetConfiguration = array()) {
		$renderedFacetOption = $this->value;

		if (isset($facetConfiguration['renderingInstruction'])) {
			$contentObject = t3lib_div::makeInstance('tslib_cObj');
			$contentObject->start(array('optionValue' => $this->value));

			$renderedFacetOption = $contentObject->cObjGetSingle(
				$facetConfiguration['renderingInstruction'],
				$facetConfiguration['renderingInstruction.']
			);
		}

		return $renderedFacetOption;
	}

	/**
	 * Creates a link tag to apply a facet option to a search result.
	 *
	 * @param string $linkText The link text
	 * @return string Html link tag to apply a facet option to a search result
	 */
	public function getAddFacetOptionLink($linkText) {
		$typolinkOptions  = $this->getTypolinkOptions();
		$filterParameters = $this->addFacetAndEncodeFilterParameters();

		return $this->query->getQueryLink($linkText, array('filter' => $filterParameters), $typolinkOptions);
	}

	/**
	 * Creates the URL to apply a facet option to a search result.
	 *
	 * @return string URL to apply a facet option to a search result
	 */
	public function getAddFacetOptionUrl() {
		$filterParameters = $this->addFacetAndEncodeFilterParameters();

		return $this->query->getQueryUrl(array('filter' => $filterParameters));
	}

	/**
	 * Retrieves the filter parmeters from the url and adds an additional facet
	 * option to create a link to apply additional facet options to a
	 * search result.
	 *
	 * @return array An array of filter parameters
	 */
	protected function addFacetAndEncodeFilterParameters() {
		$solrConfiguration = tx_solr_Util::getSolrConfiguration();
		$resultParameters = t3lib_div::_GPmerged('tx_solr');
		$filterParameters = array();

		if (isset($resultParameters['filter'])
		&& !$solrConfiguration['search.']['faceting.']['singleFacetMode']) {
			$filterParameters = array_map('urldecode', $resultParameters['filter']);
		}

		$filterParameters[] = $this->facetName . ':' . $this->value;
		$filterParameters   = array_unique($filterParameters);
		$filterParameters   = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}

	/**
	 * Creates a link tag with a link to remove a facet option from the search result.
	 *
	 * @param string $linkText link text
	 * @return string Html tag with link to remove a facet
	 */
	public function getRemoveFacetOptionLink($linkText) {
		$typolinkOptions  = $this->getTypolinkOptions();
		$filterParameters = $this->removeFacetAndEncodeFilterParameters();

		return $this->query->getQueryLink($linkText, array('filter' => $filterParameters), $typolinkOptions);
	}

	/**
	 * Creates a URL to remove a facet option from a search result.
	 *
	 * @return string URL to remove a facet
	 */
	public function getRemoveFacetOptionUrl() {
		$filterParameters = $this->removeFacetAndEncodeFilterParameters();

		return $this->query->getQueryUrl(array('filter' => $filterParameters));
	}

	/**
	 * Removes a facet option from to filter query.
	 *
	 * @return array An array of filter parameters
	 */
	protected function removeFacetAndEncodeFilterParameters() {
		$resultParameters = t3lib_div::_GPmerged('tx_solr');
		$filterParameters = array();
		$indexToRemove    = FALSE;

		if (isset($resultParameters['filter'])) {
				// urldecode the array to get the original representation
			$filterParameters = array_values((array) array_map('urldecode', $resultParameters['filter']));
			$filterParameters = array_unique($filterParameters);
			$indexToRemove    = array_search($this->facetName . ':' . $this->value, $filterParameters);
		}

		if ($indexToRemove !== FALSE) {
			unset($filterParameters[$indexToRemove]);
		}

		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}

	/**
	 * Creates a link tag with a link that will replace the current facet's
	 * option with this option applied to the search result instead.
	 *
	 * @param string $linkText link text
	 * @return string Html tag with link to replace a facet's active option with this option
	 */
	public function getReplaceFacetOptionLink($linkText) {
		$typolinkOptions  = $this->getTypolinkOptions();
		$filterParameters = $this->replaceFacetAndEncodeFilterParameters();

		return $this->query->getQueryLink($linkText, array('filter' => $filterParameters), $typolinkOptions);
	}

	/**
	 * Creates URL that will replace the current facet's option with this option
	 * applied to the search result instead.
	 *
	 * @return string URL to replace a facet's active option with this option
	 */
	public function getReplaceFacetOptionUrl() {
		$filterParameters = $this->replaceFacetAndEncodeFilterParameters();

		return $this->query->getQueryUrl(array('filter' => $filterParameters));
	}

	/**
	 * Replaces a facet option in a filter query.
	 *
	 * @return array Array of filter parameters
	 */
	protected function replaceFacetAndEncodeFilterParameters() {
		$resultParameters = t3lib_div::_GPmerged('tx_solr');
		$filterParameters = array();
		$indexToReplace   = FALSE;

		if (isset($resultParameters['filter'])) {
				// urlencode the array to get the original representation
			$filterParameters = array_values((array) array_map('urldecode', $resultParameters['filter']));
			$filterParameters = array_unique($filterParameters);

				// find the currently used option for this facet
			foreach ($filterParameters as $key => $filter) {
				list($filterName, $filterValue) = explode(':', $filter);

				if ($filterName == $this->facetName) {
					$indexToReplace = $key;
					break;
				}
			}
		}

		if ($indexToReplace !== FALSE) {
				// facet found, replace facet
				// move facet to the end of the uri so it may be manipulated using JavaScript
			unset($filterParameters[$indexToReplace]);
			$filterParameters[] = $this->facetName . ':' . $this->value;
		} else {
				// facet not found, add facet
			$filterParameters[] = $this->facetName . ':' . $this->value;
		}

		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}

	/**
	 * Checks for the TypoScript option facetLinkATagParams and
	 * creates an option array.
	 *
	 * @return array $typolinkOptions Array were the options ATagParams may included
	 */
	protected function getTypolinkOptions() {
		$typolinkOptions   = array();
		$solrConfiguration = tx_solr_Util::getSolrConfiguration();

		if (!empty($solrConfiguration['search.']['faceting.']['facetLinkATagParams'])) {
			$typolinkOptions['ATagParams'] = $solrConfiguration['search.']['faceting.']['facetLinkATagParams'];
		}

		if (!empty($this->facetConfiguration['facetLinkATagParams'])) {
			$typolinkOptions['ATagParams'] = $this->facetConfiguration['facetLinkATagParams'];
		}

		return $typolinkOptions;
	}

	/**
	 * Gets the option's value.
	 *
	 * @return integer|string The option's value.
	 */
	public function getValue() {
		return $this->value;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetoption.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facetoption.php']);
}

?>
