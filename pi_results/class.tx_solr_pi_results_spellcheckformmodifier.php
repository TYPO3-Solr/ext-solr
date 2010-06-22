<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Ingo Renner <ingo@typo3.org>
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
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_pi_results_SpellcheckFormModifier implements tx_solr_FormModifier {

	/**
	 * an instance of tx_solr_Search
	 *
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * constructor for class tx_solr_pi_results_SpellcheckFormModifier
	 */
	public function __construct() {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');
	}

	public function modifyForm(array $markers, tx_solr_Template $template) {
		if ($this->search->hasSearched()) {
			$suggestions = $this->search->getSpellcheckingSuggestions();

			if($suggestions) {
				$query      = clone $this->search->getQuery();
				$suggestion = $query->getKeywords();

				foreach($suggestions as $word => $meta) {
					$suggestion = str_ireplace($word, $meta->suggestion[0], $suggestion);
				}
				$query->setKeywords($suggestion);

				$markers['suggestion'] = tslib_cObj::noTrimWrap(
					$query->getQueryLink($query->getKeywords()),
					$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['spellchecking.']['wrap']
				);
			}
		}

		return $markers;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_spellcheckformmodifier.php']);
}

?>