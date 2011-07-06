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
 * form command class to render the "simple" search form
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_results_FormCommand implements tx_solr_PluginCommand {

	protected $cObj;

	/**
	 * Parent plugin
	 *
	 * @var	tx_solr_pi_results
	 */
	protected $parentPlugin;

	/**
	 * Configuration
	 *
	 * @var	array
	 */
	protected $configuration;

	/**
	 * Constructor for class tx_solr_pi_results_FormCommand
	 *
	 * @param	tslib_pibase	$parentPlugin parent plugin
	 */
	public function __construct(tslib_pibase $parentPlugin) {
		$this->cObj = t3lib_div::makeInstance('tslib_cObj');

		$this->parentPlugin  = $parentPlugin;
		$this->configuration = $parentPlugin->conf;
	}

	/**
	 * Provides the values for the markers in the simple form template
	 *
	 * @return	array	an array containing values for markers in the simple form template
	 */
	public function execute() {
		$marker = array(
			'action'                    => $this->cObj->getTypoLink_URL($this->parentPlugin->conf['search.']['targetPage']),
			'action_id'                 => intval($this->parentPlugin->conf['search.']['targetPage']),
			'action_language'           => intval($GLOBALS['TSFE']->sys_page->sys_language_uid),
			'action_language_parameter' => 'L', // FIXME L is not necessarily the language parameter
			'accept-charset'            => $GLOBALS['TSFE']->metaCharset,
			'q'                         => $this->parentPlugin->getCleanUserQuery()
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

				if ($formModifier instanceof tx_solr_FormModifier) {
					if ($formModifier instanceof tx_solr_PluginAware) {
						$formModifier->setParentPlugin($this->parentPlugin);
					}

					$marker = $formModifier->modifyForm($marker, $this->parentPlugin->getTemplate());
				} else {
					throw new InvalidArgumentException(
						'Form modifier "' . $classReference . '" must implement the tx_solr_FormModifier interface.',
						1262864703
					);
				}
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

			// add filters
		$additionalFilters = $this->parentPlugin->getAdditionalFilters();
		if (!empty($additionalFilters)) {
			$additionalFilters = json_encode($additionalFilters);
			$additionalFilters = urlencode($additionalFilters);

			$suggestUrl .= '&filters=' . $additionalFilters;
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
		if ($this->parentPlugin->conf['addDefaultCss'] && !$GLOBALS['TSFE']->additionalHeaderData['tx_solr_jQueryUIStylesheet']) {
			$GLOBALS['TSFE']->additionalHeaderData['tx_solr_jQueryUIStylesheet'] .=
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
			if ($this->parentPlugin->conf['suggest.']['loadJQuery'] && !$GLOBALS['TSFE']->additionalHeaderData['tx_solr_jQuery']) {
				$GLOBALS['TSFE']->additionalHeaderData[$this->parentPlugin->prefixId . '_jQuery'] .=
					'<script type="text/javascript" src="'
					. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['javaScriptFiles.']['library'])
					. '"></script>';
			}

			if (!$GLOBALS['TSFE']->additionalHeaderData['tx_solr_jQuerySuggest']) {
				$GLOBALS['TSFE']->additionalHeaderData['tx_solr_jQuerySuggest'] .=
					'<script type="text/javascript" src="'
					. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['javaScriptFiles.']['ui'])
					. '"></script>';
			}

			if (!$GLOBALS['TSFE']->additionalHeaderData['tx_solr_suggest']) {
				$GLOBALS['TSFE']->additionalHeaderData['tx_solr_suggest'] .=
					'<script type="text/javascript" src="'
					. $GLOBALS['TSFE']->tmpl->getFileName($this->parentPlugin->conf['suggest.']['javaScriptFiles.']['suggest'])
					. '"></script>';
			}
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php']);
}

?>