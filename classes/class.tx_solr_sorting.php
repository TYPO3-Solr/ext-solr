<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Stefan Sprenger <stefan.sprenger@dkd.de>
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
 * Utility class for sorting.
 *
 * @author	Stefan Sprenger <stefan.sprenger@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_Sorting {

	protected $configuration;

	/**
	 * Constructor
	 *
	 * @param	array	$sortingConfiguration Raw configuration from plugin.tx_solr.search.sorting.options
	 */
	public function __construct(array $sortingConfiguration) {
		$this->configuration = $sortingConfiguration;
	}

	/**
	 * Gets a list of configured sorting fields.
	 *
	 *  @return	array	Array of (resolved) sorting field names.
	 */
	public function getSortFields() {
		$sortFields    = array();
		$contentObject = t3lib_div::makeInstance('tslib_cObj');

		foreach ($this->configuration as $optionName => $optionConfiguration) {
			$fieldName = $contentObject->stdWrap(
				$optionConfiguration['field'],
				$optionConfiguration['field.']
			);

			$sortFields[] = $fieldName;
		}

		return $sortFields;
	}

	/**
	 * Gets the sorting options with resolved field names in case stdWrap was
	 * used to define them.
	 *
	 * @return array The sorting options with resolved field names.
	 */
	public function getSortOptions() {
		$sortOptions   = array();
		$contentObject = t3lib_div::makeInstance('tslib_cObj');

		foreach ($this->configuration as $optionName => $optionConfiguration) {
			$optionField = $contentObject->stdWrap(
				$optionConfiguration['field'],
				$optionConfiguration['field.']
			);

			$optionLabel = $contentObject->stdWrap(
				$optionConfiguration['label'],
				$optionConfiguration['label.']
			);

			$optionName = substr($optionName, 0, -1);
			$sortOptions[$optionName] = array(
				'field'        => $optionField,
				'label'        => $optionLabel,
				'defaultOrder' => $optionConfiguration['defaultOrder']
			);
		}

		return $sortOptions;
	}

	/**
	 * Takes the tx_solr[sort] URL parameter containing the option names and
	 * directions to sort by and resolves it to the actual sort fields and
	 * directions as configured through TypoScript. Makes sure that only
	 * configured sorting options get applied to the query.
	 *
	 * @param string $urlParameters tx_solr[sort] URL parameter.
	 * @return string The actual index field configured to sort by for the given sort option name
	 */
	public function getSortFieldFromUrlParameter($urlParameters) {
		$sortFields           = array();
		$sortParameters       = t3lib_div::trimExplode(',', $urlParameters);
		$availableSortOptions = $this->getSortOptions();

		foreach ($sortParameters as $sortParameter){
			list($sortOption, $sortDirection) = explode(' ', $sortParameter);

			if (!array_key_exists($sortOption, $availableSortOptions)) {
				throw t3lib_div::makeInstance('InvalidArgumentException',
					'No sorting configuration found for option name ' . $sortOption,
					1316187644
				);
			}

			$sortField = $availableSortOptions[$sortOption]['field'];
			$sortFields[] = $sortField . ' ' . $sortDirection;
		}

		return implode(', ',$sortFields);
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_sorting.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_sorting.php']);
}

?>