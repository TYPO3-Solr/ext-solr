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
 * A facet
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class tx_solr_facet_Facet {

	/**
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * The facet's name as configured on TypoScript
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The index field the facet is built from.
	 *
	 * @var string
	 */
	protected $field;

	/**
	 * Facet configuration
	 *
	 * @var array
	 */
	protected $configuration;


	/**
	 * Constructor.
	 *
	 * @param string $facetName The facet's name
	 */
	public function __construct($facetName) {
		$this->name   = $facetName;
		$this->search = t3lib_div::makeInstance('tx_solr_Search');

		$this->initializeConfiguration();
	}

	/**
	 * Initializes/loads the facet configuration
	 *
	 */
	protected function initializeConfiguration() {
		$solrConfiguration = tx_solr_Util::getSolrConfiguration();
		$this->configuration = $solrConfiguration['search.']['faceting.']['facets.'][$this->name . '.'];

		$this->field = $this->configuration['field'];
	}

	/**
	 * Checks whether an option of the facet has been selected by the user by
	 * checking the URL GET parameters.
	 *
	 * @return boolean TRUE if any option of the facet is applied, FALSE otherwise
	 */
	public function isActive() {
		$isActive = FALSE;

		$resultParameters = t3lib_div::_GET('tx_solr');
		$filterParameters = array();
		if (isset($resultParameters['filter'])) {
			$filterParameters = (array) array_map('urldecode', $resultParameters['filter']);
		}

		foreach ($filterParameters as $filter) {
			list($filterName, $filterValue) = explode(':', $filter);

			if ($filterName == $this->name) {
				$isActive = TRUE;
				break;
			}
		}

		return $isActive;
	}

	/**
	 * Determines if a facet has any options.
	 *
	 * @return boolean TRUE if no facet options are given, FALSE if facet options are given
	 */
	public function isEmpty() {
		$isEmpty = FALSE;

		$options      = $this->getOptions();
		$optionsCount = count($options);

			// facet options include '_empty_', if no options are given
		if ($optionsCount == 0
			|| ($optionsCount == 1 && array_key_exists('_empty_', $options))
		) {
			$isEmpty = TRUE;
		}

		return $isEmpty;
	}

	/**
	 * Checks whether requirements are fullfilled
	 */
	public function isRenderingAllowed() {

	}

	/**
	 * Gets the facet's options
	 *
	 * @return array An array with facet options.
	 */
	public function getOptions() {
		return $this->search->getFacetFieldOptions($this->field);
	}

	/**
	 * Gets the number of options for a facet.
	 *
	 * @return integer Number of facet options for the current facet.
	 */
	public function getOptionsCount() {
		$facetCounts = $this->search->getFacetCounts();

		return count((array) $facetCounts->facet_fields->{$this->field});
	}

	/**
	 * Gets the facet's currently user-selected options
	 *
	 * @return array An array with user-selected facet options.
	 */
	public function getSelectedOptions() {

	}

	/**
	 * Gets the facet's name
	 *
	 * @return string The facet's name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Gets the field name the facet is operating on.
	 *
	 * @return string The name of the field the facet is operating on.
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Gets the facet's configuration.
	 *
	 * @return array The facet's configuration as an array.
	 */
	public function getConfiguration() {
		return $this->configuration;
	}

}

if (defined('TYPO3_MODE')
		&& $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facet.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/facet/class.tx_solr_facet_facet.php']);
}

?>
