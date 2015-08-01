<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
 * View helper class to turn a result document's relevance score into a nicer
 * visual bar
 *
 * Replaces view helpers ###RELEVANCE_BAR:###RESULT_DOCUMENT######
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class RelevanceBar extends Relevance {

	/**
	 * Renders the HTML for the relevance bar.
	 *
	 * @param float $documentScore The current document's score
	 * @param float $maximumScore The maximum score to relate to.
	 * @return string Relevance as percentage value
	 */
	protected function render($documentScore, $maximumScore) {
		$content = '';

		if ($maximumScore > 0) {
			$score           = floatval($documentScore);
			$scorePercentage = round($score * 100 / $maximumScore);

			$content = '<div class="tx-solr-relevance-bar"><div class="tx-solr-relevance themeColorBackground" style="width: '
				. $scorePercentage . '%">&nbsp;</div><div class="tx-solr-relevance-fill" style="width: '
				. (100 - $scorePercentage) . '%"></div></div>';
		}

		return $content;
	}
}

