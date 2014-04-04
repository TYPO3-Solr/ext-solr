<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * Highlighting result document modifier, highlights query terms in result
 * documents.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_PiResults_HighlightingResultDocumentModifier implements Tx_Solr_ResultDocumentModifier {

	/**
	 * @var Tx_Solr_Search
	 */
	protected $search;

	/**
	 * Modifies the given document and returns the modified document as result.
	 *
	 * @param Tx_Solr_PiResults_ResultsCommand $resultCommand The search result command
	 * @param array $resultDocument Result document as array
	 * @return array The document with fields as array
	 */
	public function modifyResultDocument($resultCommand, array $resultDocument) {
		$this->search  = $resultCommand->getParentPlugin()->getSearch();
		$configuration = Tx_Solr_Util::getSolrConfiguration();

		$highlightedContent = $this->search->getHighlightedContent();

		$highlightFields = t3lib_div::trimExplode(',', $configuration['search.']['results.']['resultsHighlighting.']['highlightFields'], TRUE);
		foreach ($highlightFields as $highlightField) {
			if (!empty($highlightedContent->{$resultDocument['id']}->{$highlightField}[0])) {
				$fragments = array();
				foreach ($highlightedContent->{$resultDocument['id']}->{$highlightField} as $fragment) {
					$fragments[] = tx_solr_Template::escapeMarkers($fragment);
				}
				$resultDocument[$highlightField] = implode(
					' ' . $configuration['search.']['results.']['resultsHighlighting.']['fragmentSeparator'] . ' ',
					$fragments
				);
			}
		}

		return $resultDocument;
	}

}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/HighlightingResultDocumentModifier.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiResults/HighlightingResultDocumentModifier.php']);
}

?>