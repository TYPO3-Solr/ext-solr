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
 * Sorting search component
 *
 * TODO maybe merge tx_solr_Sorting into tx_solr_search_SortingComponent
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_search_SortingComponent extends tx_solr_search_AbstractComponent implements tx_solr_QueryAware {

	/**
	 * Solr query
	 *
	 * @var tx_solr_Query
	 */
	protected $query;


	/**
	 * Initializes the search component.
	 *
	 * Sets the sorting query parameters
	 *
	 */
	public function initializeSearchComponent() {
		if (!empty($this->searchConfiguration['query.']['sortBy'])) {
			$this->query->addQueryParameter('sort', $this->searchConfiguration['query.']['sortBy']);
		}

		$solrGetParameters = t3lib_div::_GET('tx_solr');

		if (!empty($this->searchConfiguration['sorting'])
			&& !empty($solrGetParameters['sort'])
			&& preg_match('/^([a-z0-9_]+ (asc|desc)[, ]*)*([a-z0-9_]+ (asc|desc))+$/i', $solrGetParameters['sort'])
		) {
			$sortHelper = t3lib_div::makeInstance('tx_solr_Sorting', $this->searchConfiguration['sorting.']['options.']);
			$sortField = $sortHelper->getSortFieldFromUrlParameter($solrGetParameters['sort']);

			$this->query->setSorting($sortField);
		}
	}

	/**
	 * Provides the extension component with an instance of the current query.
	 *
	 * @param tx_solr_Query $query Current query
	 */
	public function setQuery(tx_solr_Query $query) {
		$this->query = $query;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_sortingcomponent.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_sortingcomponent.php']);
}

?>