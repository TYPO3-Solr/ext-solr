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
 * viewhelper class to turn a result's relevance score into a nicer visual bar
 * Replaces viewhelpers ###RELEVANCE_BAR:Score###
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_viewhelper_RelevanceBar implements tx_solr_ViewHelper {

	/**
	 * instance of tx_solr_Search
	 *
	 * @var tx_solr_Search
	 */
	protected $search = NULL;

	protected $maxScore = 0;

	/**
	 * constructor for class tx_solr_viewhelper_RelevanceBar
	 */
	public function __construct(array $arguments = array()) {
		if(is_null($this->search)) {
			$this->search   = t3lib_div::makeInstance('tx_solr_Search');
			$this->maxScore = $this->search->getMaximumResultScore();
		}
	}

	/**
	 * Creates the HTML for the relevance bar
	 *
	 * @param	array	Array of arguments, [0] is expected to contain the result's score
	 * @return	string	complete relevance bar HTML
	 */
	public function execute(array $arguments = array()) {
		$content = '';

		if ($this->maxScore > 0) {
			$score           = floatval($arguments[0]);
			$scorePercentage = round($score * 100 / $this->maxScore);

			$content = '<div class="tx-solr-relevance-bar"><div class="tx-solr-relevance themeColorBackground" style="width: '
				. $scorePercentage . '%">&nbsp;</div><div class="tx-solr-relevance-fill" style="width: '
				. (100 - $scorePercentage) . '%"></div></div>';
		}

		return $content;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_relevancebar.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_relevancebar.php']);
}

?>