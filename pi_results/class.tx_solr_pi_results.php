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
 * Plugin 'Solr Search' for the 'solr' extension.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @author	Timo Schmidt <timo.schmidt@aoemedia.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_results extends tx_solr_pluginbase_CommandPluginBase {

	/**
	 * Path to this script relative to the extension dir.
	 *
	 * @var	string
	 */
	public $scriptRelPath = 'pi_results/class.tx_solr_pi_results.php';

	/**
	 * The plugin's query
	 *
	 * @var	tx_solr_Query
	 */
	protected $query = NULL;

	/**
	 * Additional filters, which will be added to the query, as well as to
	 * suggest queries.
	 *
	 * @var	 array
	 */
	protected $additionalFilters = array();

	/**
	 * Track, if the number of results per page has been changed by the current request
	 *
	 * @var	boolean
	 */
	protected $resultsPerPageChanged = FALSE;


	/**
	 * Perform the action for the plugin. In this case it calls the search()
	 * method which internally performs the search.
	 *
	 * @return	void
	 */
	protected function performAction() {
			//perform the current search.
		$this->search();
	}

	/**
	 * Executes the actual search.
	 *
	 */
	protected function search() {
		if (!is_null($this->query)) {
			$currentPage    = max(0, intval($this->piVars['page']));
			$resultsPerPage = $this->getNumberOfResultsPerPage();

				// if the number of results per page has been changed by the current request, reset the pagebrowser
			if($this->resultsPerPageChanged) {
				$currentPage = 0;
			}

			$offSet = $currentPage * $resultsPerPage;

				// ignore page browser?
			$ignorePageBrowser = (boolean) $this->conf['search.']['results.']['ignorePageBrowser'];
			$flexformIgnorePageBrowser = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'ignorePageBrowser');
			if ($flexformIgnorePageBrowser) {
				$ignorePageBrowser = (boolean) $flexformIgnorePageBrowser;
			}
			if ($ignorePageBrowser) {
				$offSet = 0;
			}

			$query    = $this->modifyQuery($this->query);
				// performing the actual search, sending the query to the Solr server
			$response = $this->search->search($query, $offSet, $resultsPerPage);

			$this->processResponse($query, $response);
		}
	}

	/**
	 * Provides a hook for other classes to process the search's response.
	 *
	 * @param	tx_solr_Query	The query that has been searched for.
	 * @param	Apache_Solr_Response	The search's reponse.
	 */
	protected function processResponse(tx_solr_Query $query, Apache_Solr_Response &$response) {
		if ($this->conf['search.']['allowEmptyQuery'] && empty($this->piVars['q'])) {
				// set number of results to 0 for empty queries as we've set number of rows to 0 too
			$response->response->numFound = 0;
		}

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'] as $classReference) {
				$responseProcessor = t3lib_div::getUserObj($classReference);

				if ($responseProcessor instanceof tx_solr_ResponseProcessor) {
					$query = $responseProcessor->processResponse($query, $response);
				}
			}
		}
	}

	/**
	 * Implementation of preRender() method. Used to include css
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_CommandPluginBase#preRender()
	 */
	protected function preRender() {
		if ($this->conf['addDefaultCss']) {
			$pathToResultsCssFile = $GLOBALS['TSFE']->config['config']['absRefPrefix']
				. t3lib_extMgm::siteRelPath($this->extKey)
				. 'resources/templates/'.$this->getPluginKey().'/results.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_defaultResultsCss'] =
				'<link href="' . $pathToResultsCssFile . '" rel="stylesheet" type="text/css" />';

			$pathToPageBrowserCssFile = $GLOBALS['TSFE']->config['config']['absRefPrefix']
				. t3lib_extMgm::siteRelPath('pagebrowse')
				. 'res/styles_min.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_defaultPageBrowserCss'] =
				'<link href="' . $pathToPageBrowserCssFile . '" rel="stylesheet" type="text/css" />';
		}
	}

	/**
	 * Returns an initialized CommandResolver.
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_CommandPluginBase#getCommandResolver()
	 */
	protected function getCommandResolver() {
		return t3lib_div::makeInstance('tx_solr_CommandResolver');
	}

	/**
	 * Retrieves the list of commands to process for the results view.
	 *
	 * @return	array	An array of command names to process for the result view
	 */
	protected function getCommandList() {
		$requirements = tx_solr_PluginCommand::REQUIREMENT_NONE;
		$commandList  = array();

		if ($this->search->hasSearched()) {
			$requirements = tx_solr_PluginCommand::REQUIREMENT_HAS_SEARCHED;

			if ($this->search->getNumberOfResults() > 0) {
				$requirements += tx_solr_PluginCommand::REQUIREMENT_HAS_RESULTS;
			} else {
				$requirements += tx_solr_PluginCommand::REQUIREMENT_NO_RESULTS;
			}
		}

		$commandList = tx_solr_CommandResolver::getPluginCommands(
			'results',
			$requirements
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

			// TODO check whether a search has been conducted already?
		if ($this->solrAvailable && (isset($this->piVars['q']) || $this->conf['search.']['allowEmptyQuery'])) {
			$this->piVars['q'] = trim($this->piVars['q']);

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['searchWords']) {
				t3lib_div::devLog('received search query', 'tx_solr', 0, array($this->piVars['q']));
			}

			$query = t3lib_div::makeInstance('tx_solr_Query', $this->piVars['q']);

			if (isset($this->conf['search.']['query.']['minimumMatch'])
				&& strlen($this->conf['search.']['query.']['minimumMatch'])) {
				$query->setMinimumMatch($this->conf['search.']['query.']['minimumMatch']);
			}

			if (!empty($this->conf['search.']['query.']['boostFunction'])) {
				$query->setBoostFunction($this->conf['search.']['query.']['boostFunction']);
			}

			if (!empty($this->conf['search.']['query.']['boostQuery'])) {
				$query->setBoostQuery($this->conf['search.']['query.']['boostQuery']);
			}

			if ($this->conf['enableDebugMode']) {
				$query->setDebugMode();
			}

			if ($this->conf['search.']['highlighting']) {
				$query->setHighlighting(TRUE, $this->conf['search.']['highlighting.']['fragmentSize']);
			}

			if ($this->conf['search.']['spellchecking']) {
				$query->setSpellchecking();
			}

			if ($this->conf['search.']['faceting']) {
				$query->setFaceting();
				$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery']['faceting'] = 'EXT:solr/classes/query/modifier/class.tx_solr_query_modifier_faceting.php:tx_solr_query_modifier_Faceting';
			}

				// access
			$query->setUserAccessGroups(explode(',', $GLOBALS['TSFE']->gr_list));
			$query->setSiteHash(tx_solr_Util::getSiteHash());

			$language = 0;
			if ($GLOBALS['TSFE']->sys_language_uid) {
				$language = $GLOBALS['TSFE']->sys_language_uid;
			}
			$query->addFilter('language:' . $language);

			foreach($this->additionalFilters as $additionalFilter) {
				$query->addFilter($additionalFilter);
			}

				// sorting
			if ($this->conf['searchResultsViewComponents.']['sorting']) {
				$query->setSorting();
			}

			$flexformSorting = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'sortBy', 'sQuery');
			if (!empty($flexformSorting)) {
				$query->addQueryParameter('sort', $flexformSorting);
			}

			$this->query = $query;
		}
	}

	/**
	 * Initializes additional filters configured through TypoScript and
	 * Flexforms for use in regular queries and suggest queries.
	 *
	 * @return	void
	 */
	protected function initializeAdditionalFilters() {
		$additionalFilters = $this->conf['search.']['filter'];

		$flexformFilters   = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'filter', 'sQuery');
		if (!empty($flexformFilters)) {
			$additionalFilters = $flexformFilters;
		}

		if (!empty($additionalFilters)) {
			$this->additionalFilters = explode('|', $additionalFilters);
		}
	}

	/**
	 * Gets additional filters configured through TypoScript and
	 * Flexforms.
	 *
	 * @return	array	An array of additional filters to use for queries.
	 */
	public function getAdditionalFilters() {
		return $this->additionalFilters;
	}

	/**
	 * Performs post initialization.
	 *
	 * @see classes/pibase/tx_solr_pibase#postInitialize()
	 */
	protected function postInitialize() {
			// disable caching
		$this->pi_USER_INT_obj = 1;
	}

	/**
	 * Overrides certain TypoScript configuration options with their values
	 * from FlexForms.
	 *
	 */
	protected function overrideTyposcriptWithFlexformSettings() {
			// empty query, useful when no search has been conducted yet but one
			// wants to show facets already. Then rows needs to be set to 0
		$emptyQueryAllowed = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'emptyQuery', 'sQuery');
		if ($emptyQueryAllowed) {
			$this->conf['search.']['allowEmptyQuery'] = 1;
		}

			// target page
		$targetPage = (int) $this->conf['search.']['targetPage'];
		$flexformTargetPage = (int) $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'targetPage');
		if ($flexformTargetPage) {
			$targetPage = $flexformTargetPage;
		}
		if (!empty($targetPage)) {
			$this->conf['search.']['targetPage'] = $targetPage;
		} else {
			$this->conf['search.']['targetPage'] = $GLOBALS['TSFE']->id;
		}

			// boost function
		$boostFunction = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'boostFunction', 'sQuery');
		if ($boostFunction) {
			$this->conf['search.']['query.']['boostFunction'] = $boostFunction;
		}

			// boost query
		$boostQuery = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'boostQuery', 'sQuery');
		if ($boostQuery) {
			$this->conf['search.']['query.']['boostQuery'] = $boostQuery;
		}
	}

	/**
	 * Post initialization of the template engine, adding some Solr variables.
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_PluginBase#postInitializeTemplate($template)
	 * @param	tx_solr_Template	The template object as initialized thus far.
	 * @return	tx_solr_Template	The modified template instance with additional variables available for rendering.
	 */
	protected function postInitializeTemplateEngine($template) {
		$template->addVariable('solr', $this->getSolrVariables());

		return $template;
	}

	/**
	 * Gets a list of EXT:solr variables like theprefix ID.
	 *
	 * @return	array	array of EXT:solr variables
	 */
	protected function getSolrVariables() {
		$currentUrl = $this->pi_linkTP_keepPIvars_url();

		if ($this->solrAvailable && $this->search->hasSearched()) {
			$currentUrl = $this->search->getQuery()->getQueryUrl();
		}

		return array(
			'prefix'      => $this->prefixId,
			'current_url' => $currentUrl
		);
	}

	/**
	 * Returns the number of results per Page.
	 *
	 * @return	int	number of results to show per page
	 */
	public function getNumberOfResultsPerPage() {
		$configuration = tx_solr_Util::getSolrConfiguration();
		$resultsPerPageSwitchOptions = t3lib_div::intExplode(',', $configuration['search.']['results.']['resultsPerPageSwitchOptions']);

		$solrParameters     = array();
		$solrPostParameters = t3lib_div::_POST('tx_solr');
		$solrGetParameters  = t3lib_div::_GET('tx_solr');

			// check for GET parameters, POST takes precedence
		if (isset($solrGetParameters) && is_array($solrGetParameters)) {
			$solrParameters = $solrGetParameters;
		}
		if (isset($solrPostParameters) && is_array($solrPostParameters)) {
			$solrParameters = $solrPostParameters;
		}

		if (isset($solrParameters['resultsPerPage']) && in_array($solrParameters['resultsPerPage'], $resultsPerPageSwitchOptions)) {
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_solr_resultsPerPage', intval($solrParameters['resultsPerPage']));
			$this->resultsPerPageChanged = TRUE;
		}

		$defaultNumberOfResultsShown = $configuration['search.']['results.']['resultsPerPage'];
		$userSetNumberOfResultsShown = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_solr_resultsPerPage');

		$currentNumberOfResultsShown = $defaultNumberOfResultsShown;
		if (!is_null($userSetNumberOfResultsShown) && in_array($userSetNumberOfResultsShown, $resultsPerPageSwitchOptions)) {
			$currentNumberOfResultsShown = (int) $userSetNumberOfResultsShown;
		}

		if ($this->conf['search.']['allowEmptyQuery'] && empty($this->piVars['q'])) {
				// set number of rows to return to 0
			$currentNumberOfResultsShown = 0;
		}

		return $currentNumberOfResultsShown;
	}

	/**
	 * Returns the key which is used to determine the templatefile from the typoscript setup.
	 *
	 * @see classes/pibase/tx_solr_pibase#getTemplateFileKey()
	 * @return string
	 */
	protected function getTemplateFileKey() {
		return 'results';
	}

	/**
	 * Returns the plugin key, used in various base methods.
	 *
	 * @see classes/pibase/tx_solr_pibase#getPluginKey()
	 * @return string
	 */
	protected function getPluginKey() {
		return 'pi_results';
	}

	/**
	 * Returns the main subpart to work on.
	 *
	 * @see classes/pibase/tx_solr_pibase#getSubpart()
	 */
	protected function getSubpart() {
		return 'solr_search';
	}

	/**
	 * Gets the tx_solr_Search instance used for the query. Mainly used as a
	 * helper function for result document modifiers.
	 *
	 * @return	tx_solr_Search
	 */
	public function getSearch() {
		return $this->search;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results.php']);
}

?>