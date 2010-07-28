<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2010 Ingo Renner <ingo@typo3.org>
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

		$testSearchWord = t3lib_div::_GPmerged('tx_solr');
		if (trim($testSearchWord['q'])) {
			$searchWord = t3lib_div::removeXSS(trim($testSearchWord['q']));
			$searchWord = htmlentities($searchWord, ENT_QUOTES, $GLOBALS['TSFE']->metaCharset);
		}

		$marker = array(
			'action'         => $this->cObj->getTypoLink_URL($this->parentPlugin->conf['search.']['targetPage']),
			'action_id'      => $this->parentPlugin->conf['search.']['targetPage'],
			'accept-charset' => $GLOBALS['TSFE']->metaCharset,
			'q'              => $searchWord
		);

			// TODO maybe move into a form modifier
		if ($this->parentPlugin->conf['suggest']) {
			$this->addSuggestStylesheets();
			$this->addSuggestJavascript();
			$marker['suggest_url'] = '<script type="text/javascript">
				/*<![CDATA[*/
				var tx_solr_suggestUrl = \'' . $this->getSuggestUrl() . '\';
				/*]]>*/
				</script>
			';
		}

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

	/**
	 * Returns the URL to which the Ajax request for the suggest functionality should be sent.
	 *
	 * @author Mario Rimann <mario.rimann@internezzo.ch>
	 * @return string the full URL to the eID script including the needed parameters
	 */
	protected function getSuggestUrl() {
		$suggestUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL');

		if ($this->parentPlugin->conf['suggest.']['forceHttps']) {
			$suggestUrl = str_replace('http://', 'https://', $suggestUrl);
		}

		$suggestUrl .= '?eID=tx_solr_suggest&id=' . $GLOBALS['TSFE']->id;

			// adds the language parameter to the suggest URL
		if ($GLOBALS['TSFE']->sys_language_uid > 0) {
			$suggestUrl .= '&L=' . $GLOBALS['TSFE']->sys_language_uid;
		}

			// adds the language parameter to the suggest URL
		if ($GLOBALS['TSFE']->sys_language_uid > 0) {
			$suggestUrl .= '&L=' . $GLOBALS['TSFE']->sys_language_uid;
		}

		return $suggestUrl;
	}

	/**
	 * Adds the stylesheets necessary for the suggestions
	 */
	protected function addSuggestStylesheets() {
		if ($this->parentPlugin->conf['addDefaultCss']) {
			$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_jQueryUIStylesheet'] .=
				'<link rel="stylesheet" type="text/css" href="'
				. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['stylesheet'])
				. '" media="all" />';
		}
	}

	/**
	 * Adds the Javascript necessary for the suggestions
	 *
	 * By default will also load jQuery, but this can be disabled through
	 * TypoScript.
	 */
	protected function addSuggestJavascript() {
		if ($this->parentPlugin->conf['addDefaultJs']) {
			if ($this->parentPlugin->conf['suggest.']['loadJQuery']) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_jQuery'] .=
					'<script type="text/javascript" src="'
					. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['javaScriptFiles.']['library'])
					. '"></script>';
			}

			$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_jQuerySuggest'] .=
				'<script type="text/javascript" src="'
				. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['javaScriptFiles.']['ui'])
				. '"></script>';

			$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_suggest'] .=
				'<script type="text/javascript" src="'
				. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['javaScriptFiles.']['suggest'])
				. '"></script>';
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php']);
}

?>