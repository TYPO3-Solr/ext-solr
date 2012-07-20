<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ingo Renner <ingo.renner@dkd.de>
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
 * Spell checker / Did you mean
 *
 * @author Ingo Renner <ingo.renner@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_SpellChecker {

	/**
	 * Search instance
	 *
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * Configuration
	 *
	 * @var array
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
	 * Gets the raw spellchecking suggestions
	 *
	 * @return array Array of suggestions
	 */
	public function getSuggestions() {
		$suggestions = $this->search->getSpellcheckingSuggestions();

		return $suggestions;
	}

	/**
	 * Gets the collated suggestion
	 *
	 * @return string collated suggestion
	 */
	public function getCollatedSuggestion() {
		$suggestions = $this->search->getSpellcheckingSuggestions();

		return $suggestions['collation'];
	}

	/**
	 * Checks whether the user's query was correctly spelled.
	 *
	 * @return boolean TRUE if the query terms were correctly spelled, FALSE otherwise
	 */
	public function isIncorrectlySpelled() {
		$suggestions = $this->getSuggestions();

		return $suggestions['correctlySpelled'];
	}

	/**
	 * Query URL with a suggested/corrected query
	 *
	 * @return string Suggestion/spellchecked query URL
	 */
	public function getSuggestionQueryUrl() {
		$suggestions = $this->getSuggestions();

		$query = clone $this->search->getQuery();
		$query->setKeywords($suggestions['collation']);

		$queryLinkBuilder = t3lib_div::makeInstance('tx_solr_query_LinkBuilder', $query);
		$queryLinkBuilder->setLinkTargetPageId($GLOBALS['TSFE']->id);

		return $queryLinkBuilder->getQueryUrl();
	}

	/**
	 * Query link with a suggested/corrected query
	 *
	 * @return string Suggestion/spellchecked query link
	 */
	public function getSuggestionQueryLink() {
		$suggestions = $this->getSuggestions();

		$query = clone $this->search->getQuery();
		$query->setKeywords($suggestions['collation']);

		$queryLinkBuilder = t3lib_div::makeInstance('tx_solr_query_LinkBuilder', $query);
		$queryLinkBuilder->setLinkTargetPageId($GLOBALS['TSFE']->id);

		return $queryLinkBuilder->getQueryLink(htmlspecialchars($query->getKeywordsRaw()));
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

			$suggestions = $this->getSuggestions();
			if($suggestions && !$suggestions['correctlySpelled']) {
				$suggestionsLink = tslib_cObj::noTrimWrap(
					$this->getSuggestionQueryLink(),
					$this->configuration['search.']['spellchecking.']['wrap']
				);
			}
		}

		return $suggestionsLink;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_spellchecker.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_spellchecker.php']);
}

?>