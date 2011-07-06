<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo@typo3.org>
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
 * Spellcheck form modifier, suggests spell checked queries
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_results_SpellcheckFormModifier implements tx_solr_FormModifier {

	/**
	 * Search instance
	 *
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * Configuration
	 *
	 * @var	array
	 */
	protected $configuration;

	/**
	 * Constructor for class tx_solr_pi_results_SpellcheckFormModifier
	 *
	 */
	public function __construct() {
		$this->search        = t3lib_div::makeInstance('tx_solr_Search');
		$this->configuration = tx_solr_Util::getSolrConfiguration();
	}

	/**
	 * Modifies the search form by providing an additional marker linking to a
	 * new query with the suggestions provided by Solr as the search terms.
	 *
	 * @param	array	An array of existing form markers
	 * @param	tx_solr_Template	An instance of the template engine
	 * @return	array	Array with additional markers for suggestions
	 */
	public function modifyForm(array $markers, tx_solr_Template $template) {
		$suggestionsLink = $this->getSpellcheckingSuggestions();

		if (!empty($suggestionsLink)) {
			$markers['suggestion'] = $suggestionsLink;
		}

		return $markers;
	}

	/**
	 * Generates a link with spell checking suggestions if it is activated and
	 * spell checking suggestions are returned by Solr.
	 *
	 * @return	string	A link to start over with a new search using the suggested keywords.
	 */
	public function getSpellcheckingSuggestions() {
		$suggestionsLink = '';

		if ($this->configuration['search.']['spellchecking'] && $this->search->hasSearched()) {
			$suggestions = $this->search->getSpellcheckingSuggestions();

			if($suggestions) {
				$query = clone $this->search->getQuery();
				$query->setKeywords($suggestions['collation']);

				$suggestionsLink = tslib_cObj::noTrimWrap(
					$query->getQueryLink($query->getKeywords()),
					$this->configuration['search.']['spellchecking.']['wrap']
				);
			}
		}

		return $suggestionsLink;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php']);
}

?>