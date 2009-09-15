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
 * form command class to render the "simple" search form
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_pi_results_FormCommand implements tx_solr_Command {

	protected $cObj;
	protected $parentPlugin;

	public function __construct(tslib_pibase $parentPlugin) {
		$this->cObj = t3lib_div::makeInstance('tslib_cObj');
		$this->parentPlugin = $parentPlugin;
	}

	/**
	 * Provides the values for the markers in the simple form template
	 *
	 * @return array	an array containing values for markers in the simple form template
	 */
	public function execute() {
		$searchWord = '';

		$testSearchWord = t3lib_div::GParrayMerged('tx_solr');
		if (trim($testSearchWord['q'])) {
			$searchWord = trim($testSearchWord['q']);
		}

		$marker = array(
			'action'         => $this->cObj->getTypoLink_URL($this->parentPlugin->conf['search.']['targetPage']),
			'action_id'      => $this->parentPlugin->conf['search.']['targetPage'],
			'accept-charset' => $GLOBALS['TSFE']->metaCharset,
			'q'              => $searchWord
		);

			// hook to modify the search form
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchForm'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchForm'] as $classReference) {
				$formModifier = t3lib_div::getUserObj($classReference);

					// FIXME, check whether the search form modifier interface is implemented
					// maybe add (inject) the template during instanciation

				$marker = $formModifier->modifyForm($marker, $this->parentPlugin->getTemplate());
			}
		}

		return $marker;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php']);
}

?>