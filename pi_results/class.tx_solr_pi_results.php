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
 * Plugin 'Solr Search' for the 'solr' extension.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	tx_solr
 */
class tx_solr_pi_results extends tslib_pibase {

	public $prefixId      = 'tx_solr';
	public $scriptRelPath = 'pi_results/class.tx_solr_pi_results.php';	// Path to this script relative to the extension dir.
	public $extKey        = 'solr';	// The extension key.

	/**
	 * an instance of tx_solr_Search
	 *
	 * @var tx_solr_Search
	 */
	protected $search;

	/**
	 * an instance of tx_solr_Template
	 *
	 * @var tx_solr_Template
	 */
	protected $template;

	/**
	 * Determines whether the solr server is available or not.
	 */
	protected $solrAvailable;

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	public function main($content, $configuration) {
		$this->initialize($configuration);

		$content = '';
		if ($this->solrAvailable) {
			$content = $this->render();
		} else {
			$content = $this->renderSolrError();
		}

		if ($this->conf['addDefaultCss']) {
			$pathToCssFile = $GLOBALS['TSFE']->config['config']['absRefPrefix']
				. t3lib_extMgm::siteRelPath($this->extKey)
				. 'resources/templates/pi_results/results.css';
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_defaultCss'] =
				'<link href="' . $pathToCssFile . '" rel="stylesheet" type="text/css" />';
		}

		return $this->pi_wrapInBaseClass($content);
	}

	protected function render() {
		$commandList = $this->getResultviewCommandList();

		$commandResolver = t3lib_div::makeInstance(
			'tx_solr_CommandResolver',
			$GLOBALS['PATH_solr'] . 'pi_results/',
			'tx_solr_pi_results_'
		);

		foreach ($commandList as $commandName) {
			$command = $commandResolver->getCommand($commandName, $this);
			$commandVariables = $command->execute();

			$subpartTemplate = clone $this->template;
			$subpartTemplate->setWorkingTemplateContent(
				$this->template->getSubpart('solr_search_' . $commandName)
			);

			if (!is_null($commandVariables)) {
				foreach ($commandVariables as $variableName => $commandVariable) {
					if (t3lib_div::isFirstPartOfStr($variableName, 'loop_')) {
						$dividerPosition  = strpos($variableName, '|');
						$loopName         = substr($variableName, 5, ($dividerPosition - 5));
						$loopedMarkerName = substr($variableName, ($dividerPosition + 1));

						$subpartTemplate->addLoop($loopName, $loopedMarkerName, $commandVariable);
					} else if (t3lib_div::isFirstPartOfStr($variableName, 'subpart_')) {
						$subpartName = substr($variableName, 8);
						$subpartTemplate->addSubpart($subpartName, $commandVariable);
					} else {
						$subpartTemplate->addVariable($commandName, $commandVariables);
					}
				}

				$this->template->addSubpart('solr_search_' . $commandName, $subpartTemplate->render());
			}

			unset($subpartTemplate);
		}

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['pi_results']['renderTemplate'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['pi_results']['renderTemplate'] as $classReference) {
				$templateModifier = &t3lib_div::getUserObj($classReference);

				if ($templateModifier instanceof tx_solr_TemplateModifier) {
					$templateModifier->modifyTemplate($this->template);
				}
			}
		}

		return $this->template->render();
	}

	protected function renderSolrError() {
		$this->template->workOnSubpart('solr_search_unavailable');

		return $this->template->render();
	}

	/**
	 * Initializes the plugin - configuration, language, caching, search...
	 *
	 * @param	array	configuration array as provided by the TYPO3 core
	 * @return	void
	 */
	protected function initialize($configuration) {
		$this->conf = $configuration;

		$this->conf = array_merge(
			$this->conf,
			$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']
		);

		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_initPIflexForm();
		$this->pi_USER_INT_obj = 1;	// disable caching

		$this->initializeSearch();
		$this->initializeTemplateEngine();

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
	}

	/**
	 * Initializes the template engine and returns the initialized instance.
	 *
	 * @return	tx_solr_Template
	 */
	protected function initializeTemplateEngine() {
		$templateFile = $this->conf['templateFile.']['results'];

		$flexformTemplateFile = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'templateFile', 'sOptions');
		if (!empty($flexformTemplateFile)) {
			$templateFile = $flexformTemplateFile;
		}

		$template = t3lib_div::makeInstance(
			'tx_solr_Template',
			$this->cObj,
			$templateFile,
			'solr_search'
		);

		$template->addViewHelperIncludePath($this->extKey, 'classes/viewhelper/');
		$template->addViewHelper('LLL', array(
			'languageFile' => $GLOBALS['PATH_solr'] . 'pi_results/locallang.xml',
			'llKey'        => $this->LLkey
		));

		$template->addVariable('solr', $this->getSolrVariables());

			// can be used for view helpers that need configuration during initialization
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['pi_results']['addViewHelpers'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['pi_results']['addViewHelpers'] as $classReference) {
				$viewHelperProvider = &t3lib_div::getUserObj($classReference);

				if ($viewHelperProvider instanceof tx_solr_ViewHelperProvider) {
					$viewHelpers = $viewHelperProvider->getViewHelpers();
					foreach ($viewHelpers as $helperName => $helperObject) {
						$helperAdded = $template->addViewHelperObject($helperName, $helperObject);
							// TODO check whether $helperAdded is true, throw an exception if not
					}
				} else {
					// TODO throw an exception
				}
			}
		}

		$this->template = $template;
	}

	protected function initializeSearch() {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');
		$this->solrAvailable = $this->search->ping();

			// TODO provide the option in TS, too
		$emptyQuery = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'emptyQuery', 'sQuery');

			// TODO check whether a search has been conducted already?
		if ($this->solrAvailable && (isset($this->piVars['q']) || $emptyQuery)) {
			$this->piVars['q'] = trim($this->piVars['q']);

			if ($emptyQuery) {
					// TODO set rows to retrieve when searching to 0
			}

			if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['query.']['searchWords']) {
				t3lib_div::devLog('received search query', 'tx_solr', 0, array($this->piVars['q']));
			}

			$query = t3lib_div::makeInstance('tx_solr_Query', $this->piVars['q']);

			if ($this->conf['search.']['highlighting']) {
				$query->setHighlighting(true, $this->conf['search.']['highlighting.']['fragmentSize']);
			}

			if ($this->conf['search.']['spellchecking']) {
				$query->setSpellchecking();
			}

			if ($this->conf['search.']['faceting']) {
				$query->setFaceting();
				$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery']['faceting'] = 'EXT:solr/classes/querymodifier/class.tx_solr_querymodifier_faceting.php:tx_solr_querymodifier_Faceting';
			}

			$query->setUserAccessGroups(explode(',', $GLOBALS['TSFE']->gr_list));
			$query->setSiteHash(tx_solr_Util::getSiteHash());

			$language = 0;
			if ($GLOBALS['TSFE']->sys_language_uid) {
				$language = $GLOBALS['TSFE']->sys_language_uid;
			}
			$query->addFilter('language:' . $language);

			$additionalFilters = $this->conf['search.']['filter'];
			$flexformFilters   = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'filter', 'sQuery');
			if (!empty($flexformFilters)) {
				$additionalFilters = $flexformFilters;
			}
			if (!empty($additionalFilters)) {
				$additionalFilters = explode('|', $additionalFilters);
				foreach($additionalFilters as $additionalFilter) {
					$query->addFilter($additionalFilter);
				}
			}

			$currentPage    = max(0, intval($this->piVars['page']));
			$resultsPerPage = $this->getNumberOfResultsPerPage();
			$offSet         = $currentPage * $resultsPerPage;

				// ignore page browser?
			$ignorePageBrowser = (boolean) $this->conf['search.']['results.']['ignorePageBrowser'];
			$flexformIgnorePageBrowser = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'ignorePageBrowser');
			if ($flexformIgnorePageBrowser) {
				$ignorePageBrowser = (boolean) $flexformIgnorePageBrowser;
			}
			if ($ignorePageBrowser) {
				$offSet = 0;
			}

				// sorting
			if ($this->conf['searchResultsViewComponents.']['sorting']) {
				$query->setSorting();
			}

			$flexformSorting = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'sortBy', 'sQuery');
			if (!empty($flexformSorting)) {
				$query->addQueryParameter('sort', $flexformSorting);
			}

			$query = $this->modifyQuery($query);

			$response = $this->search->search($query, $offSet, $resultsPerPage);
		}
	}

	/**
	 * retrievs the list of commands we have to process for the results view
	 *
	 * @return array	array of command names to process for the result view
	 */
	protected function getResultviewCommandList() {
		$commandList = array();
		$formStyle   = $this->conf['search.']['form'];

			// always show the form
		if ($formStyle == 'simple') {
			$commandList[] = 'form';
		} elseif($formStyle == 'advanced') {
			$commandList[] = 'advanced_form';
		}

			// check which commands / components of the result view to show
		if ($this->search->hasSearched()) {
			if ($this->search->getNumberOfResults() > 0) {
				foreach ($this->conf['searchResultsViewComponents.'] as $commandName => $enabled) {
					if ($enabled) {
						$commandList[] = $commandName;
					}
				}

				$commandList[] = 'results';
			} else {
				$commandList[] = 'no_results';
			}
		}

		return $commandList;
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
			'prefix' => $this->prefixId,
			'current_url' => $currentUrl
		);
	}

	public function getNumberOfResultsPerPage() {
		$configuration = tx_solr_Util::getSolrConfiguration();
		$resultsPerPageSwitchOptions = t3lib_div::intExplode(',', $configuration['search.']['results.']['resultsPerPageSwitchOptions']);

		$solrPostParameters = t3lib_div::_POST('tx_solr');
		if (isset($solrPostParameters['resultsPerPage']) && in_array($solrPostParameters['resultsPerPage'], $resultsPerPageSwitchOptions)) {
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_solr_resultsPerPage', intval($solrPostParameters['resultsPerPage']));
		}

		$defaultNumberOfResultsShown = $configuration['search.']['results.']['resultsPerPage'];
		$userSetNumberOfResultsShown = $GLOBALS['TSFE']->fe_user->getKey('ses', 'tx_solr_resultsPerPage');

		$currentNumberOfResultsShown = $defaultNumberOfResultsShown;
		if (!is_null($userSetNumberOfResultsShown) && in_array($userSetNumberOfResultsShown, $resultsPerPageSwitchOptions)) {
			$currentNumberOfResultsShown = (int) $userSetNumberOfResultsShown;
		}

		return $currentNumberOfResultsShown;
	}

	protected function modifyQuery($query) {
			// hook to modify the search query
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'] as $classReference) {
				$queryModifier = t3lib_div::getUserObj($classReference);

				if ($queryModifier instanceof tx_solr_QueryModifier) {
					$query = $queryModifier->modifyQuery($query);
				}
			}
		}

		return $query;
	}

	public function getTemplate() {
		return $this->template;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results.php']);
}

?>