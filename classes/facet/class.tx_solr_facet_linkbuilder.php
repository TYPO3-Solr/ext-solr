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
 * Link builder for facet options
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class tx_solr_facet_LinkBuilder extends tx_solr_query_LinkBuilder {

	/**
	 * Facet name.
	 *
	 * @var string
	 */
	protected $facetName;

	/**
	 * Facet option.
	 *
	 * @var tx_solr_facet_FacetOption
	 */
	protected $facetOption;

	/**
	 * The current facet's configuration.
	 *
	 * @var array
	 */
	protected $facetConfiguration;


	/**
	 * Constructor.
	 *
	 * @param tx_solr_Query $query Solr query
	 * @param string $facetName Facet name
	 * @param tx_solr_facet_FacetOption Facet option
	 */
	public function __construct(tx_solr_Query $query, $facetName, tx_solr_facet_FacetOption $facetOption) {
		parent::__construct($query);

		$this->facetName          = $facetName;
		$this->facetConfiguration = $this->solrConfiguration['search.']['faceting.']['facets.'][$this->facetName . '.'];
		$this->facetOption        = $facetOption;

		if ($this->solrConfiguration['search.']['faceting.']['facetLinkUrlParameters']) {
			$this->addUrlParameters($this->solrConfiguration['search.']['faceting.']['facetLinkUrlParameters']);
		}
	}


	// adding facet filters


	/**
	 * Creates a link tag to apply a facet option to a search result.
	 *
	 * @param string $linkText The link text
	 * @return string Html link tag to apply a facet option to a search result
	 */
	public function getAddFacetOptionLink($linkText) {
		$typolinkOptions  = $this->getTypolinkOptions();
		$filterParameters = $this->addFacetAndEncodeFilterParameters();

		return $this->getQueryLink($linkText, array('filter' => $filterParameters), $typolinkOptions);
	}

	/**
	 * Creates the URL to apply a facet option to a search result.
	 *
	 * @return string URL to apply a facet option to a search result
	 */
	public function getAddFacetOptionUrl() {
		$filterParameters = $this->addFacetAndEncodeFilterParameters();

		return $this->getQueryUrl(array('filter' => $filterParameters));
	}

	/**
	 * Retrieves the filter parmeters from the url and adds an additional facet
	 * option to create a link to apply additional facet options to a
	 * search result.
	 *
	 * @return array An array of filter parameters
	 */
	protected function addFacetAndEncodeFilterParameters() {
		$resultParameters = t3lib_div::_GPmerged('tx_solr');
		$filterParameters = array();

		if (isset($resultParameters['filter'])
		&& !$this->solrConfiguration['search.']['faceting.']['singleFacetMode']) {
			$filterParameters = array_map('urldecode', $resultParameters['filter']);
		}

		$filterParameters[] = $this->facetName . ':' . $this->facetOption->getUrlValue();

		$filterParameters = array_unique($filterParameters);
		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}


	// removing facet filters


	/**
	 * Creates a link tag with a link to remove a facet option from the search result.
	 *
	 * @param string $linkText link text
	 * @return string Html tag with link to remove a facet
	 */
	public function getRemoveFacetOptionLink($linkText) {
		$typolinkOptions  = $this->getTypolinkOptions();
		$filterParameters = $this->removeFacetAndEncodeFilterParameters();

		return $this->getQueryLink($linkText, array('filter' => $filterParameters), $typolinkOptions);
	}

	/**
	 * Creates a URL to remove a facet option from a search result.
	 *
	 * @return string URL to remove a facet
	 */
	public function getRemoveFacetOptionUrl() {
		$filterParameters = $this->removeFacetAndEncodeFilterParameters();

		return $this->getQueryUrl(array('filter' => $filterParameters));
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
			$indexToRemove    = array_search($this->facetName . ':' . $this->facetOption->getUrlValue(), $filterParameters);
		}

		if ($indexToRemove !== FALSE) {
			unset($filterParameters[$indexToRemove]);
		}

		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}


	// replace facet filters


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

		return $this->getQueryLink($linkText, array('filter' => $filterParameters), $typolinkOptions);
	}

	/**
	 * Creates URL that will replace the current facet's option with this option
	 * applied to the search result instead.
	 *
	 * @return string URL to replace a facet's active option with this option
	 */
	public function getReplaceFacetOptionUrl() {
		$filterParameters = $this->replaceFacetAndEncodeFilterParameters();

		return $this->getQueryUrl(array('filter' => $filterParameters));
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
			$filterParameters[] = $this->facetName . ':' . $this->facetOption->getUrlValue();
		} else {
				// facet not found, add facet
			$filterParameters[] = $this->facetName . ':' . $this->facetOption->getUrlValue();
		}

		$filterParameters = array_map('urlencode', $filterParameters);

		return $filterParameters;
	}


	// helpers


	/**
	 * Checks for the TypoScript option facetLinkATagParams and
	 * creates an option array.
	 *
	 * @return array $typolinkOptions Array were the options ATagParams may included
	 */
	protected function getTypolinkOptions() {
		$typolinkOptions   = array();

		if (!empty($this->solrConfiguration['search.']['faceting.']['facetLinkATagParams'])) {
			$typolinkOptions['ATagParams'] = $this->solrConfiguration['search.']['faceting.']['facetLinkATagParams'];
		}

		if (!empty($this->facetConfiguration['facetLinkATagParams'])) {
			$typolinkOptions['ATagParams'] = $this->facetConfiguration['facetLinkATagParams'];
		}

		return $typolinkOptions;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_linkbuilder.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_linkbuilder.php']);
}

?>