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
 * Suggest form modifier, suggests queries through auto completion / AJAX.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_results_SuggestFormModifier implements tx_solr_FormModifier, tx_solr_CommandPluginAware {

	/**
	 * Configuration
	 *
	 * @var	array
	 */
	protected $configuration;

	/**
	 * The currently active plugin
	 *
	 * @var	tx_solr_pluginbase_CommandPluginBase
	 */
	protected $parentPlugin;

	/**
	 * Constructor for class tx_solr_pi_results_SuggestFormModifier
	 *
	 */
	public function __construct() {
		$this->configuration = tx_solr_Util::getSolrConfiguration();
	}

	/**
	 * Sets the currently active parent plugin.
	 *
	 * @param tx_solr_pluginbase_CommandPluginBase Currently active parent plugin
	 */
	public function setParentPlugin(tx_solr_pluginbase_CommandPluginBase $parentPlugin) {
		$this->parentPlugin = $parentPlugin;
	}

	/**
	 * Modifies the search form by providing an additional marker providing the
	 * suggest eID script URL and adding javascript to the page's header.
	 *
	 * @param	array	An array of existing form markers
	 * @param	tx_solr_Template	An instance of the template engine
	 * @return	array	Array with additional markers for suggestions
	 */
	public function modifyForm(array $markers, tx_solr_Template $template) {
		$suggestionsEnabled = $this->configuration['suggest'];

		if ($suggestionsEnabled) {
			$this->addSuggestStylesheets();
			$this->addSuggestJavascript();
			$suggestEidUrl = $this->getSuggestEidUrl();

			$markers['suggest_url'] = '<script type="text/javascript">
				/*<![CDATA[*/
				var tx_solr_suggestUrl = \'' . $suggestEidUrl . '\';
				/*]]>*/
				</script>
			';
		}

		return $markers;
	}

	/**
	 * Adds the stylesheets necessary for the suggestions
	 *
	 */
	protected function addSuggestStylesheets() {
		if ($this->configuration['cssFiles.']['ui'] && !$GLOBALS['TSFE']->additionalHeaderData['tx_solr-uiCss']) {
			$cssFile = $GLOBALS['TSFE']->tmpl->getFileName($this->configuration['cssFiles.']['ui']);
			$GLOBALS['TSFE']->additionalHeaderData['tx_solr-uiCss'] =
				'<link href="' . $cssFile . '" rel="stylesheet" type="text/css" media="all" />';
		}
	}

	/**
	 * Adds the Javascript necessary for the suggestions
	 *
	 */
	protected function addSuggestJavascript() {
		$javascriptManager = $this->parentPlugin->getJavascriptManager();

		$javascriptManager->loadFile('library');
		$javascriptManager->loadFile('ui');
		$javascriptManager->loadFile('ui.autocomplete');
		$javascriptManager->loadFile('suggest');
	}

	/**
	 * Returns the eID URL for the AJAX suggestion request.
	 *
	 * @author Mario Rimann <mario.rimann@internezzo.ch>
	 * @return string the full URL to the eID script including the needed parameters
	 */
	protected function getSuggestEidUrl() {
		$suggestUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL');

		if ($this->configuration['suggest.']['forceHttps']) {
			$suggestUrl = str_replace('http://', 'https://', $suggestUrl);
		}

		$suggestUrl .= '?eID=tx_solr_suggest&id=' . $GLOBALS['TSFE']->id;

			// add filters
		$additionalFilters = $this->parentPlugin->getAdditionalFilters();
		if (!empty($additionalFilters)) {
			$additionalFilters = json_encode($additionalFilters);
			$additionalFilters = rawurlencode($additionalFilters);

			$suggestUrl .= '&filters=' . $additionalFilters;
		}

			// adds the language parameter to the suggest URL
		if ($GLOBALS['TSFE']->sys_language_uid > 0) {
			$suggestUrl .= '&L=' . $GLOBALS['TSFE']->sys_language_uid;
		}

		return $suggestUrl;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_suggestformmodifier.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_suggestformmodifier.php']);
}

?>