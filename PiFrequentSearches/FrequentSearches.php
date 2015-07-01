<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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
 * Plugin 'Frequent Searches' for the 'solr' extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_PiFrequentSearches_FrequentSearches extends Tx_Solr_PluginBase_CommandPluginBase{

	/**
	 * Path to this script relative to the extension dir.
	 */
	public $scriptRelPath = 'PiFrequentSearches/FrequentSearches.php';

	/**
	 * Returns an initialized commandResolver. In this case we use the command
	 * of the results view.
	 *
	 * @todo	currently the commands of the resultview are used, we should discuss if we use own command here
	 * @see Tx_Solr_PluginBase_CommandPluginBase#getCommandResolver()
	 * @return Tx_Solr_CommandResolver A command resolver
	 */
	protected function getCommandResolver(){
		return t3lib_div::makeInstance('Tx_Solr_CommandResolver');
	}

	/**
	 * Retrievs the list of commands we have to process for the results view
	 *
	 * @return array Array of command names to process for the result view
	 */
	protected function getCommandList() {
		$commandList = Tx_Solr_CommandResolver::getPluginCommands(
			'frequentsearches'
		);

		return $commandList;
	}

	/**
	 * Gets a list of EXT:solr variables like the prefix ID.
	 *
	 * @todo	refactor into baseclass
	 * @return array array of EXT:solr variables
	 */
	protected function getSolrVariables() {
		$currentUrl = $this->pi_linkTP_keepPIvars_url();

		if ($this->solrAvailable && $this->search->hasSearched()) {
			$queryLinkBuilder = t3lib_div::makeInstance('Tx_Solr_Query_LinkBuilder', $this->search->getQuery());
			$currentUrl = $queryLinkBuilder->getQueryUrl();
		}

		return array(
			'prefix'      => $this->prefixId,
			'current_url' => $currentUrl
		);
	}

	/**
	 * Perform the action for the plugin. In this case it doesn't do anything
	 * as the plugin simply renders the frequent searches command.
	 *
	 * @return void
	 */
	protected function performAction() {}

	/**
	 * Post initialization of the template engine.
	 *
	 * @see Tx_Solr_PluginBase_PluginBase#postInitializeTemplateEngine($template)
	 */
	protected function postInitializeTemplateEngine($template) {
		$template->addVariable('tx_solr', $this->getSolrVariables());

		return $template;
	}

	/**
	 * Provides the typoscript key, which is used to determine the template file
	 * for this view.
	 *
	 * @see Tx_Solr_PluginBase_PluginBase#getTemplateFileKey()
	 * @return string TypoScript key used to determine the template file.
 	 */
	protected function getTemplateFileKey() {
		return 'frequentSearches';
	}

	/**
	 * Return the plugin key, used to initialize the template engine.
	 *
	 * @see Tx_Solr_PluginBase_PluginBase#getPluginKey()
	 * @return string Plugin key used during initialization of the template engine
	 */
	protected function getPluginKey() {
		return 'PiFrequentSearches';
	}

	/**
	 * Returns the name of the template subpart used by the plugin.
	 *
	 * @see Tx_Solr_pluginBase_PluginBase#getSubpart()
	 * @return string Name of the template subpart to use for rendering
	 */
	protected function getSubpart() {
		return 'solr_search';
	}

	/**
	 * Implementation of preRender() method. Used to include CSS files.
	 *
	 * @see Tx_Solr_PluginBase_PluginBase#preRender()
	 */
	protected function preRender() {
		if($this->conf['cssFiles.']['results']) {
			$cssFile = t3lib_div::createVersionNumberedFilename($GLOBALS['TSFE']->tmpl->getFileName($this->conf['cssFiles.']['results']));
			$GLOBALS['TSFE']->additionalHeaderData['tx_solr-resultsCss'] =
					'<link href="' . $cssFile . '" rel="stylesheet" type="text/css" />';
		}
	}

}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiFrequentSearches/FrequentSearches.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/PiFrequentSearches/FrequentSearches.php']);
}

?>