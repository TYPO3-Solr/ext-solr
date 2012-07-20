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
 * Results view command
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_results_ResultsCommand implements tx_solr_PluginCommand {

	/**
	 * @var tx_solr_Search
	 */
	protected $search;

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
	 * Constructor.
	 *
	 * @param tx_solr_pluginbase_CommandPluginBase Parent plugin object.
	 */
	public function __construct(tx_solr_pluginbase_CommandPluginBase $parentPlugin) {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');

		$this->parentPlugin  = $parentPlugin;
		$this->configuration = $parentPlugin->conf;
	}

	public function execute() {
		$numberOfResults = $this->search->getNumberOfResults();
		$rawQuery        = $this->parentPlugin->getRawUserQuery();
		$query           = $this->parentPlugin->getCleanUserQuery();

		$searchedFor = strtr(
			$this->parentPlugin->pi_getLL('results_searched_for'),
			array('@searchWord' => '<span class="tx-solr-search-word">' . $query . '</span>')
		);

		$foundResultsInfo = strtr(
			$this->parentPlugin->pi_getLL('results_found'),
			array(
				'@resultsTotal' => $this->search->getNumberOfResults(),
				'@resultsTime'  => $this->search->getQueryTime()
			)
		);

		return array(
			'searched_for'                    => $searchedFor,
			'query'                           => $query,
			'query_urlencoded'                => rawurlencode($rawQuery),
			'query_raw'                       => $rawQuery,
			'found'                           => $foundResultsInfo,
			'range'                           => $this->getPageBrowserRange(),
			'count'                           => $this->search->getNumberOfResults(),
			'offset'                          => ($this->search->getResultOffset() + 1),
			'query_time'                      => $this->search->getQueryTime(),
			'pagebrowser'                     => $this->getPageBrowser($numberOfResults),
			'subpart_results_per_page_switch' => $this->getResultsPerPageSwitch(),
			'filtered'                        => $this->isFiltered(),
			'filtered_by_user'                => $this->isFilteredByUser(),
				/* construction of the array key:
				 * loop_ : tells the plugin that the content of that field should be processed in a loop
				 * result_documents : is the loop name as in the template
				 * result_document : is the marker name for the single items in the loop
				 */
			'loop_result_documents|result_document' => $this->getResultDocuments()
		);
	}

	protected function getResultDocuments() {
		$responseDocuments = $this->search->getResultDocuments();
		$resultDocuments   = array();


		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyResultSet'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyResultSet'] as $classReference) {
				$resultSetModifier = t3lib_div::getUserObj($classReference);

				if ($resultSetModifier instanceof tx_solr_ResultSetModifier) {
					$responseDocuments = $resultSetModifier->modifyResultSet($this, $responseDocuments);
				} else {
					throw new UnexpectedValueException(
						get_class($resultSetModifier) . ' must implement interface tx_solr_ResultSetModifier',
						1310386927
					);
				}
			}
		}

		foreach ($responseDocuments as $resultDocument) {
			$temporaryResultDocument = array();
			$temporaryResultDocument = $this->processDocumentFieldsToArray($resultDocument);

			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyResultDocument'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyResultDocument'] as $classReference) {
					$resultDocumentModifier = t3lib_div::getUserObj($classReference);

					if ($resultDocumentModifier instanceof tx_solr_ResultDocumentModifier) {
						$temporaryResultDocument = $resultDocumentModifier->modifyResultDocument($this, $temporaryResultDocument);
					} else {
						throw new UnexpectedValueException(
							get_class($resultDocumentModifier) . ' must implement interface tx_solr_ResultDocumentModifier',
							1310386725
						);
					}
				}
			}

			$resultDocuments[] = $this->renderDocumentFields($temporaryResultDocument);
			unset($temporaryResultDocument);
		}

		return $resultDocuments;
	}

	/**
	 * takes a search result document and processes its fields according to the
	 * instructions configured in TS. Currently available instructions are
	 * 	* timestamp - converts a date field into a unix timestamp
	 * 	* utf8Decode - decodes utf8
	 * 	* serialize - uses serialize() to encode multivalue fields which then can be put out using the MULTIVALUE view helper
	 * 	* skip - skips the whole field so that it is not available in the result, usefull for the spell field f.e.
	 * The default is to do nothing and just add the document's field to the
	 * resulting array.
	 *
	 * @param	Apache_Solr_Document	$document the Apache_Solr_Document result document
	 * @return	array	An array with field values processed like defined in TS
	 */
	protected function processDocumentFieldsToArray(Apache_Solr_Document $document) {
		$processingInstructions = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['results.']['fieldProcessingInstructions.'];
		$availableFields = $document->getFieldNames();
		$result = array();

		foreach ($availableFields as $fieldName) {
			$processingInstruction = $processingInstructions[$fieldName];

				// TODO switch to field processors
				// TODO allow to have multiple (commaseparated) instructions for each field
			switch ($processingInstruction) {
				case 'timestamp':
						// FIXME use DateTime::createFromFormat (PHP 5.3+)
					$parsedTime = strptime($document->{$fieldName}, '%Y-%m-%dT%H:%M:%SZ');

					$processedFieldValue = mktime(
						$parsedTime['tm_hour'],
						$parsedTime['tm_min'],
						$parsedTime['tm_sec'],
							// strptime returns the "Months since January (0-11)"
							// while mktime expects the month to be a value
							// between 1 and 12. Adding 1 to solve the problem
						$parsedTime['tm_mon'] + 1,
						$parsedTime['tm_mday'],
							// strptime returns the "Years since 1900"
						$parsedTime['tm_year'] + 1900
					);
					break;
				case 'utf8Decode':
					$processedFieldValue = tx_solr_Util::utf8Decode($document->{$fieldName});
					break;
				case 'serialize':
					if(!empty($document->{$fieldName})){
						$processedFieldValue = serialize($document->{$fieldName});
					} else {
						$processedFieldValue = '';
					}
					break;
				case 'skip':
					continue 2;
				default:
					$processedFieldValue = $document->{$fieldName};
			}

			$result[$fieldName] = $processedFieldValue;
		}

		return $result;
	}

	protected function renderDocumentFields(array $document) {
		$renderingInstructions = $this->configuration['search.']['results.']['fieldRenderingInstructions.'];
		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start($document);

		foreach ($renderingInstructions as $renderingInstructionName => $renderingInstruction) {
			if (!is_array($renderingInstruction)) {
				$renderedField = $cObj->cObjGetSingle(
					$renderingInstructions[$renderingInstructionName],
					$renderingInstructions[$renderingInstructionName . '.']
				);

				$document[$renderingInstructionName] = $renderedField;
			}
		}

		return $document;
	}

	protected function getPageBrowser($numberOfResults) {
		$resultsPerPage = $this->parentPlugin->getNumberOfResultsPerPage();
		$numberOfPages  = intval($numberOfResults / $resultsPerPage)
			+ (($numberOfResults % $resultsPerPage) == 0 ? 0 : 1);

		$solrPageBrowserConfiguration = array();
		if (isset($this->configuration['search.']['results.']['pagebrowser.'])) {
			$solrPageBrowserConfiguration = $this->configuration['search.']['results.']['pagebrowser.'];
		}

		$pageBrowserConfiguration = array_merge(
			$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1.'],
			$solrPageBrowserConfiguration,
			array(
				'pageParameterName' => 'tx_solr|page',
				'numberOfPages'     => $numberOfPages,
				'extraQueryString'  => '&' . $this->parentPlugin->getQueryGetParameter() . '=' . $this->search->getQuery()->getKeywords(),
				'templateFile'      => $this->configuration['templateFiles.']['pagebrowser']
			)
		);

			// Get page browser
		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start(array(), '');

		$cObjectType = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1'];
		$pageBrowser = $cObj->cObjGetSingle($cObjectType, $pageBrowserConfiguration);

		return $pageBrowser;
	}

	protected function getPageBrowserRange() {
		$label = '';

		$resultsFrom  = $this->search->getResponse()->start + 1;
		$resultsTo    = $resultsFrom + count($this->search->getResponse()->docs) - 1;
		$resultsTotal = $this->search->getNumberOfResults();

		$label = strtr(
			$this->parentPlugin->pi_getLL('results_range'),
			array(
				'@resultsFrom'  => $resultsFrom,
				'@resultsTo'    => $resultsTo,
				'@resultsTotal' => $resultsTotal
			)
		);

		return $label;
	}

	protected function getResultsPerPageSwitch() {
		$template = clone $this->parentPlugin->getTemplate();
		$template->workOnSubpart('results_per_page_switch');
		$configuration = tx_solr_Util::getSolrConfiguration();

		$resultsPerPageSwitchOptions = t3lib_div::intExplode(',', $configuration['search.']['results.']['resultsPerPageSwitchOptions']);
		$currentNumberOfResultsShown = $this->parentPlugin->getNumberOfResultsPerPage();

		$selectOptions = array();
		foreach ($resultsPerPageSwitchOptions as $option) {
			$selected      = '';
			$selectedClass = '';
			if ($option == $currentNumberOfResultsShown) {
				$selected      = ' selected="selected"';
				$selectedClass = ' class="currentNumberOfResults"';
			}

			$selectOptions[] = array(
				'value'         => $option,
				'selected'      => $selected,
				'selectedClass' => $selectedClass,
				'url'           => $this->parentPlugin->pi_linkTP_keepPIvars_url(array('resultsPerPage' => $option)),
			);
		}
		$template->addLoop('options', 'option', $selectOptions);

		$form = array('action' => htmlentities($this->parentPlugin->pi_linkTP_keepPIvars_url(array(
			'resultsPerPage' => '',
			'page' => '',
		))));
		$template->addVariable('form', $form);

		return $template->render();
	}

	/**
	 * Gets the parent plugin.
	 *
	 * @return	tx_solr_pi_results
	 */
	public function getParentPlugin() {
		return $this->parentPlugin;
	}

	/**
	 * Determines whether filters have been applied to the query or not.
	 *
	 * @return	string	1 if filters are applied, 0 if not (for use in templates)
	 */
	protected function isFiltered() {
		$filters = $this->search->getQuery()->getFilters();
		$filtered = !empty($filters);

		return ($filtered ? '1' : '0');
	}

	/**
	 * Determines whether filters have been applied by the user (facets for
	 * example) to the query or not.
	 *
	 * @return	string	1 if filters are applied, 0 if not (for use in templates)
	 */
	protected function isFilteredByUser() {
		$userFiltered = FALSE;
		$resultParameters = t3lib_div::_GET('tx_solr');

		if (isset($resultParameters['filter'])) {
			$userFiltered = TRUE;
		}

		return ($userFiltered ? '1' : '0');
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_resultscommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_resultscommand.php']);
}

?>