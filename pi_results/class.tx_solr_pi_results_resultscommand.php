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
 * Results view command
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_pi_results_ResultsCommand implements tx_solr_Command {

	/**
	 * @var tx_solr_Search
	 */
	protected $search;

	protected $parentPlugin;

	/**
	 * constructor for class tx_solr_pi_results_ResultsCommand
	 */
	public function __construct(tslib_pibase $parentPlugin) {
		$this->search = t3lib_div::makeInstance('tx_solr_Search');

		$this->parentPlugin = $parentPlugin;
	}

	public function execute() {
		$numberOfResults = $this->search->getNumberOfResults();

		$searchedFor = strtr(
			$this->parentPlugin->pi_getLL('results_searched_for'),
			array(
				'@searchWord' => htmlentities(trim($this->parentPlugin->piVars['q']), ENT_QUOTES, $GLOBALS['TSFE']->metaCharset)
			)
		);

		return array(
			'searched_for' => $searchedFor,
			'range' => $this->getPageBrowserRange(),
			'count' => $this->search->getNumberOfResults(),
			'offset' => ($this->search->getResultOffset() + 1),
			'query_time' => $this->search->getQueryTime(),
				/* construction of the array key:
				 * loop_ : tells the plugin that the content of that field should be processed in a loop
				 * result_documents : is the loop name as in the template
				 * result_document : is the marker name for the single items in the loop
				 */
			'loop_result_documents|result_document' => $this->getResultDocuments(),
			'pagebrowser' => $this->getPageBrowser($numberOfResults),
			'subpart_results_per_page_switch' => $this->getResultsPerPageSwitch()
		);
	}

	protected function getResultDocuments() {
		$processingInstructions = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['results.']['fieldProcessingInstructions.'];
		$searchResponse  = $this->search->getResponse();
		$resultDocuments = array();

			// TODO check whether highlighting is enabled in TS at all
		$highlightedContent = $this->search->getHighlightedContent();

		foreach ($searchResponse->docs as $resultDocument) {
			$temporaryResult = array();
			$temporaryResult = $this->processDocumentFieldsToArray($resultDocument);

			if ($highlightedContent->{$resultDocument->id}->content[0]) {
				$temporaryResult['content'] = $this->utf8Decode(
					$highlightedContent->{$resultDocument->id}->content[0]
				);
			}

				// TODO add a hook to further modify the search result document

			$resultDocuments[] = $this->renderDocumentFields($temporaryResult);
			unset($temporaryResult);
		}

		return $resultDocuments;
	}

	/**
	 * takes a search result document and processes its fields according to the
	 * instructions configured in TS. Currently available instructions are
	 * 	* timestamp - converts a date field into a unix timestamp
	 * 	* utf8Decode - decodes utf8
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

				// TODO allow to have multiple (commaseparated) instructions for each field
			switch ($processingInstruction) {
				case 'timestamp':
					$parsedTime = strptime($document->{$fieldName}, '%Y-%m-%dT%TZ');
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
					$processedFieldValue = $this->utf8Decode($document->{$fieldName});
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
		$renderingInstructions = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['results.']['fieldRenderingInstructions.'];
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
		$configuration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];

		$resultsPerPage = $this->parentPlugin->getNumberOfResultsPerPage();
		$numberOfPages  = intval($numberOfResults / $resultsPerPage)
			+ (($numberOfResults % $resultsPerPage) == 0 ? 0 : 1);

		$pageBrowserConfiguration = array_merge(
			$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pagebrowse_pi1.'],
			$configuration['search.']['results.']['pagebrowser.'],
			array(
				'pageParameterName' => 'tx_solr|page',
				'numberOfPages'     => $numberOfPages,
				'extraQueryString'  => '&tx_solr[q]=' . $this->search->getQuery()->getKeywords(),
				'disableCacheHash'  => true,
			)
		);

			// Get page browser
		$cObj = t3lib_div::makeInstance('tslib_cObj');
		$cObj->start(array(), '');

		$pageBrowser = $cObj->cObjGetSingle('USER_INT', $pageBrowserConfiguration);

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
			$selected = '';
			if ($option == $currentNumberOfResultsShown) {
				$selected = ' selected="selected"';
			}

			$selectOptions[] = array(
				'value'    => $option,
				'selected' => $selected
			);
		}
		$template->addLoop('options', 'option', $selectOptions);

		$form = array('action' => $this->parentPlugin->pi_linkTP_keepPIvars_url());
		$template->addVariable('form', $form);

		return $template->render();
	}

	protected function utf8Decode($string) {
		if ($GLOBALS['TSFE']->metaCharset !== 'utf-8') {
			$string = $GLOBALS['TSFE']->csConvObj->utf8_decode($string, $GLOBALS['TSFE']->renderCharset);
		}

		return $string;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_resultscommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_resultscommand.php']);
}

?>