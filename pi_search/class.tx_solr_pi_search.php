<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Timo Schmidt <timo.schmidt@aoemedia.de>
*  (c) 2012 Ingo Renner <ingo@typo3.org>
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
 * Plugin 'Solr Searchbox' for the 'solr' extension. A cached plugin version of
 * just the search input field.
 *
 * @author	Timo Schmidt <timo.schmidt@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_search extends tx_solr_pluginbase_CommandPluginBase{

	/**
	 * Path to this script relative to the extension dir.
	 */
	public $scriptRelPath = 'pi_search/class.tx_solr_pi_search.php';

	/**
	 * Additional filters, which will be added to suggest queries.
	 *
	 * @var	 array
	 */
	protected $additionalFilters = array();

	/**
	 * Returns an initialized commandResolver. In this case we use the command
	 * of the results view.
	 *
	 * @todo	currently the commands of the resultview are used, we should discuss if we use own command here
	 * @see	classes/pluginbasebase/tx_solr_pluginbase_CommandPluginBase#getCommandResolver()
	 * @return	tx_solr_CommandResolver	A command resolver
	 */
	protected function getCommandResolver(){
		return t3lib_div::makeInstance('tx_solr_CommandResolver');
	}

	/**
	 * Retrievs the list of commands we have to process for the results view
	 *
	 * @return	array	Array of command names to process for the result view
	 */
	protected function getCommandList() {
		$commandList = tx_solr_CommandResolver::getPluginCommands(
			'search'
		);

		return $commandList;
	}

	/**
	 * Performs special search initialization for the result plugin.
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_PluginBase#initializeSearch()
	 */
	protected function initializeSearch() {
		parent::initializeSearch();
		$this->initializeAdditionalFilters();
	}

	/**
	 * Initializes additional filters configured through TypoScript for use in
	 * suggest queries.
	 *
	 */
	protected function initializeAdditionalFilters() {
		$additionalFilters = array();

		if(!empty($this->conf['search.']['query.']['filter.'])) {
			foreach($this->conf['search.']['query.']['filter.'] as $filterKey => $filter) {
				if (!is_array($this->conf['search.']['query.']['filter.'][$filterKey])) {
					if (is_array($this->conf['search.']['query.']['filter.'][$filterKey . '.'])) {
						$filter = $this->cObj->stdWrap(
							$this->conf['search.']['query.']['filter.'][$filterKey],
							$this->conf['search.']['query.']['filter.'][$filterKey . '.']
						);
					}

					$additionalFilters[$filterKey] = $filter;
				}
			}
		}

		$this->additionalFilters = $additionalFilters;
	}

	/**
	 * Gets additional filters configured through TypoScript.
	 *
	 * @return	array	An array of additional filters to use for queries.
	 */
	public function getAdditionalFilters() {
		return $this->additionalFilters;
	}

	/**
	 * Gets a list of EXT:solr variables like the prefix ID.
	 *
	 * @todo	refactor into baseclass
	 * @return	array	array of EXT:solr variables
	 */
	protected function getSolrVariables() {
		$currentUrl = $this->pi_linkTP_keepPIvars_url();

		if ($this->solrAvailable && $this->search->hasSearched()) {
			$queryLinkBuilder = t3lib_div::makeInstance('tx_solr_query_LinkBuilder', $this->search->getQuery());
			$currentUrl = $queryLinkBuilder->getQueryUrl();
		}

		return array(
			'prefix'          => $this->prefixId,
			'query_parameter' => tx_solr_query_LinkBuilder::getQueryGetParameter(),
			'current_url'     => $currentUrl
		);
	}

	/**
	 * Perform the action for the plugin. In this case it doesn't do anything
	 * as the plugin simply renders the search form.
	 *
	 * @return void
	 */
	protected function performAction() {}

	/**
	 * Post initialization of the template engine.
	 *
	 * @see classes/pluginbase/tx_solr_pluginbase_PluginBase#postInitializeTemplateEngine($template)
	 */
	protected function postInitializeTemplateEngine($template) {
		$template->addVariable('tx_solr', $this->getSolrVariables());

		return $template;
	}

	/**
	 * Provides the typoscript key, which is used to determine the template file
	 * for this view.
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_PluginBase#getTemplateFileKey()
	 * @return	string	TypoScript key used to determine the template file.
 	 */
	protected function getTemplateFileKey() {
		return 'search';
	}

	/**
	 * Return the plugin key, used to initialize the template engine.
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_PluginBase#getPluginKey()
	 * @return	string	Plugin key used during initialization of the template engine
	 */
	protected function getPluginKey() {
		return 'pi_search';
	}

	/**
	 * Returns the name of the template subpart used by the plugin.
	 *
	 * @see classes/pluginbase/tx_solr_pluginbase_PluginBase#getSubpart()
	 * @return	string	Name of the template subpart to use for rendering
	 */
	protected function getSubpart() {
		return 'solr_search';
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_search/class.tx_solr_pi_search.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_search/class.tx_solr_pi_search.php']);
}

?>