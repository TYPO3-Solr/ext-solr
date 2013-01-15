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
 * Access search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_search_AccessComponent extends tx_solr_search_AbstractComponent implements tx_solr_QueryAware {

	/**
	 * Solr query
	 *
	 * @var tx_solr_Query
	 */
	protected $query;


	/**
	 * Initializes the search component.
	 *
	 *
	 */
	public function initializeSearchComponent() {
		$allowedSites = str_replace(
			'__solr_current_site',
			tx_solr_Site::getSiteByPageId($GLOBALS['TSFE']->id)->getDomain(),
			$this->searchConfiguration['query.']['allowedSites']
		);
		$this->query->setSiteHashFilter($allowedSites);

		$this->query->setUserAccessGroups(explode(',', $GLOBALS['TSFE']->gr_list));

			// must generate default endtime, @see http://forge.typo3.org/issues/44276
		$this->query->addFilter('(endtime:[NOW/MINUTE TO *] OR endtime:"' . tx_solr_Util::timestampToIso(0) . '")');
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


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_accesscomponent.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_accesscomponent.php']);
}

?>