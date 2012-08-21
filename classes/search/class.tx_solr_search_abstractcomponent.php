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
 * Abstract search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
abstract class tx_solr_search_AbstractComponent implements tx_solr_SearchComponent {

	/**
	 * Search configuration - plugin.tx_solr.search
	 *
	 * @var array
	 */
	protected $searchConfiguration = array();


	/**
	 * Sets the plugin's search configuration.
	 *
	 * @param array $configuration Configuration
	 * @see tx_solr_SearchComponent::setSearchConfiguration()
	 */
	public function setSearchConfiguration(array $configuration) {
		$this->searchConfiguration = $configuration;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_abstractcomponent.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/search/class.tx_solr_search_abstractcomponent.php']);
}

?>