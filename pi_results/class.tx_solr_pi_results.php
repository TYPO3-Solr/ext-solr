<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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
			// perform the current search.
		$this->search();
	}

	/**
	 * Executes the actual search.
	 *
	 */
	protected function search() {
		if (!is_null($this->query)
			&& ($this->query->getQueryString()
				|| $this->conf['search.']['initializeWithEmptyQuery']
				|| $this->conf['search.']['showResultsOfInitialEmptyQuery']
		)) {
			$currentPage = max(0, intval($this->piVars['page']));

				// if the number of results per page has been changed by the current request, reset the pagebrowser
			if($this->resultsPerPageChanged) {
				$currentPage = 0;
			}

			$offSet = $currentPage * $this->query->getResultsPerPage();

				// performing the actual search, sending the query to the Solr server
			$this->search->search($this->query, $offSet, NULL);
			$response = $this->search->getResponse();

			$this->processResponse($this->query, $response);
		}
	}

	/**
	 * Provides a hook for other classes to process the search's response.
	 *
	 * @param	tx_solr_Query	The query that has been searched for.
	 * @param	Apache_Solr_Response	The search's reponse.
	 */
	protected function processResponse(tx_solr_Query $query, Apache_Solr_Response &$response) {
		$rawUserQuery = $this->getRawUserQuery();

		if ($this->conf['search.']['initializeWithEmptyQuery']
			&& !$this->conf['search.']['showResultsOfInitialEmptyQuery']
			&& empty($rawUserQuery)
		) {
				// explicitly set number of results to 0 as we just wanted
				// facets and the like according to configuration
				// @see	getNumberOfResultsPerPage()
			$response->response->numFound = 0;
		}

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'] as $classReference) {
				$responseProcessor = t3lib_div::getUserObj($classReference);

				if ($responseProcessor instanceof tx_solr_ResponseProcessor) {
					$responseProcessor->processResponse($query, $response);
				}
			}
		}
	}

	/**
	 * Implementation of preRender() method. Used to include CSS files.
	 *
	 * @see	classes/pluginbase/tx_solr_pluginbase_PluginBase#preRender()
	 */
	protected function preRender() {
		if($this->conf['cssFiles.']['results']) {
			$cssFile = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['cssFiles.']['results']);
			$GLOBALS['TSFE']->additionalHeaderData['tx_solr-resultsCss'] =
				'<link href="' . $cssFile . '" rel="stylesheet" type="text/css" />';
		}

		if($this->conf['cssFiles.']['pagebrowser']) {
			$cssFile = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['cssFiles.']['pagebrowser']);
			$GLOBALS['TSFE']->additionalHeaderData['tx_solr-pageBrowserCss'] =
				'<link href="' . $cssFile . '" rel="stylesheet" type="text/css" />';
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

		$rawUserQuery = $this->getRawUserQuery();

			// TODO check whether a search has been conducted already?
		if ($this->solrAvailable && (isset($rawUserQuery) || $this->conf['search.']['initializeWithEmptyQuery'])) {

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['searchWords']) {
				t3lib_div::devLog('received search query', 'solr', 0, array($rawUserQuery));
			}

			$query = t3lib_div::makeInstance('tx_solr_Query', $rawUserQuery);
			/* @var	$query	tx_solr_Query */

			$resultsPerPage = $this->getNumberOfResultsPerPage();
			$query->setResultsPerPage($resultsPerPage);

			$searchComponents = t3lib_div::makeInstance('tx_solr_search_SearchComponentManager')->getSearchComponents();
			foreach ($searchComponents as $searchComponent) {
				$searchComponent->setSearchConfiguration($this->conf['search.']);

				if ($searchComponent instanceof tx_solr_QueryAware) {
					$searchComponent->setQuery($query);
				}

				if ($searchComponent instanceof  tx_solr_PluginAware) {
					$searchComponent->setParentPlugin($this);
				}

				$searchComponent->initializeSearchComponent();
			}

			if ($this->conf['search.']['initializeWithEmptyQuery'] || $this->conf['search.']['query.']['allowEmptyQuery']) {
					// empty main query, but using a "return everything"
					// alternative query in q.alt
				$query->setAlternativeQuery('*:*');
			}

			foreach($this->additionalFilters as $additionalFilter) {
				$query->addFilter($additionalFilter);
			}

			$this->query = $query;
		}
	}

	/**
	 * Initializes additional filters configured through TypoScript and
	 * Flexforms for use in regular queries and suggest queries.
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

			// flexform overwrites _all_ filters set through TypoScript
		$flexformFilters = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'filter', 'sQuery');
		if (!empty($flexformFilters)) {
			$additionalFilters = t3lib_div::trimExplode('|', $flexformFilters);
		}

		$this->additionalFilters = $additionalFilters;
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
			// initialize with empty query, useful when no search has been
			// conducted yet but needs to show facets already.
		$initializeWithEmptyQuery = $this->pi_getFFvalue(
			$this->cObj->data['pi_flexform'],
			'initializeWithEmptyQuery',
			'sQuery'
		);
		if ($initializeWithEmptyQuery) {
			$this->conf['search.']['initializeWithEmptyQuery'] = 1;
		}

		$showResultsOfInitialEmptyQuery = $this->pi_getFFvalue(
			$this->cObj->data['pi_flexform'],
			'showResultsOfInitialEmptyQuery',
			'sQuery'
		);
		if ($showResultsOfInitialEmptyQuery) {
			$this->conf['search.']['showResultsOfInitialEmptyQuery'] = 1;
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

			// sorting
		$flexformSorting = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'sortBy', 'sQuery');
		if ($flexformSorting) {
			$this->conf['search.']['query.']['sortBy'] = $flexformSorting;
		}

			// results per page
		$resultsPerPage = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'resultsPerPage', 'sQuery');
		if ($resultsPerPage) {
			$this->conf['search.']['results.']['resultsPerPage'] = $resultsPerPage;
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
		$template->addVariable('tx_solr', $this->getSolrVariables());

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
			$queryLinkBuilder = t3lib_div::makeInstance('tx_solr_query_LinkBuilder', $this->search->getQuery());
			$currentUrl = $queryLinkBuilder->getQueryUrl();
		}

		return array(
			'prefix'          => $this->prefixId,
			'query_parameter' => tx_solr_query_LinkBuilder::getQueryGetParameter(),
			'current_url'     => $currentUrl,
			'q'               => $this->getCleanUserQuery()
		);
	}

	/**
	 * Returns the number of results per Page.
	 *
	 * Also influences how many result documents are returned by the Solr
	 * server as the return value is used in the Solr "rows" GET parameter.
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

		$defaultNumberOfResultsShown = $this->conf['search.']['results.']['resultsPerPage'];
		$userSetNumberOfResultsShown = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_solr_resultsPerPage');

		$currentNumberOfResultsShown = $defaultNumberOfResultsShown;
		if (!is_null($userSetNumberOfResultsShown) && in_array($userSetNumberOfResultsShown, $resultsPerPageSwitchOptions)) {
			$currentNumberOfResultsShown = (int) $userSetNumberOfResultsShown;
		}

		$rawUserQuery = $this->getRawUserQuery();

		if ($this->conf['search.']['initializeWithEmptyQuery']
			&& !$this->conf['search.']['showResultsOfInitialEmptyQuery']
			&& empty($rawUserQuery)
		) {
				// initialize search with an empty query, which would by default return all documents
				// anyway, tell Solr to not return any result documents
				// Solr will still return facets though
			$currentNumberOfResultsShown = 0;
		}

		return $currentNumberOfResultsShown;
	}

	/**
	 * Gets the plugin's configuration.
	 *
	 * @return array Configuration
	 */
	public function getConfiguration() {
		return $this->conf;
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
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results.php']);
}

?>