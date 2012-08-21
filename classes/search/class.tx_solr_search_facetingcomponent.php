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
 * Faceting search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_search_FacetingComponent extends tx_solr_search_AbstractComponent {

	/**
	 * Initializes the search component.
	 *
	 */
	public function initializeSearchComponent() {
		if ($this->searchConfiguration['faceting']) {
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery']['faceting'] = 'tx_solr_query_modifier_Faceting';
		}
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_facetingcomponent.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_facetingcomponent.php']);
}

?>