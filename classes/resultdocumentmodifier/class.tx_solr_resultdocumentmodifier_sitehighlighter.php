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
 * Provides highlighting of the search words on the document's actual page by
 * adding parameters to a doument's URL property.
 *
 *
 * @author	Stefan Sprenger <stefan.sprenger@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_resultdocumentmodifier_SiteHighlighter implements tx_solr_ResultDocumentModifier {

	/**
	 * Modifies the given result document's url field by appending parameters
	 * which will result in having the current search terms highlighted on the
	 * target page.
	 *
	 * @param	tx_solr_pi_results_ResultsCommand	The search result command
	 * @param	array	$resultDocument The result document's fields as an array
	 * @return	array	The document with fields as array
	 */
	public function modifyResultDocument($resultCommand, array $resultDocument) {
		$searchWords = $resultCommand->getParentPlugin()->getSearch()->getQuery()->getKeywordsCleaned();

			// remove quotes from phrase searches - they've been escaped by getCleanUserQuery()
		$searchWords = str_replace('&quot;', '', $searchWords);
		$searchWords = t3lib_div::trimExplode(' ', $searchWords, TRUE);

		$url = $resultDocument['url'];
		$fragment = '';
		if (strpos($url, '#') !== FALSE) {
			$explodedUrl = explode('#', $url);

			$fragment = $explodedUrl[1];
			$url      = $explodedUrl[0];
		}
		$url .= (strpos($url, '?') !== FALSE) ? '&' : '?';
		$url .= 'sword_list[]=' . array_shift($searchWords);

		foreach($searchWords as $word) {
			$url .= '&sword_list[]=' . $word;
		}

		$url .= '&no_cache=1' . ($fragment ? '#' . $fragment : '');

			// eventually, replace the document's URL with the one that enables highlighting
		$resultDocument['url'] = $url;

		return $resultDocument;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/resultdocumentmodifier/class.tx_solr_resultdocumentmodifier_sitehighlighter.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/resultdocumentmodifier/class.tx_solr_resultdocumentmodifier_sitehighlighter.php']);
}

?>