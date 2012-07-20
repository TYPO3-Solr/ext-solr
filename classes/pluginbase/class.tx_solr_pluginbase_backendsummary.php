<?php
/***************************************************************
*  Copyright notice
*
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
 * Summary to display flexform settings in the page layout backend module.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_pluginbase_BackendSummary {

	protected $pluginContentElement = array();
	protected $flexformData = array();
	protected $settings = array();


	protected function initialize(array $contentElement) {
		$this->pluginContentElement = $contentElement;

		$flexformAsArray = t3lib_div::xml2array($contentElement['pi_flexform']);
		$this->flexformData = $flexformAsArray['data'];
	}

	/**
	 * Returns information about a plugin's flexform configruation
	 *
	 * @param array $parameters Parameters to the hook
	 * @return string Plugin configuration information
	 */
	public function getSummary(array $parameters) {
		$this->initialize($parameters['row']);

		$this->getTargetPage();
		$this->getFilter();
		$this->getSorting();
		$this->getResultsPerPage();
		$this->getBoostFuntion();
		$this->getBoostQuery();
		$this->getTemplateFile();

		return $this->renderSettings();
	}

	protected function renderSettings() {
		$content = '';

		if (!empty($this->settings)) {
			$isVisibleRecord = !$this->pluginContentElement['hidden'];

			$tableStyle = 'width:100%;';
			if (!$isVisibleRecord) {
				$tableStyle .= ' background: none; border-color: #e5e5e5; color: #bbb';
			}

			$content = '<table class="typo3-dblist" style="' . $tableStyle . '">';

			$i = 0;
			foreach ($this->settings as $label => $value) {
				$classAttribute  = 'class="';
				$classAttribute .= ($i++ % 2 == 0) ? 'bgColor4' : 'bgColor3';
				$classAttribute .= '"';

				$content .= '
					<tr ' . ($isVisibleRecord ? $classAttribute : '') . '>
						<td style="' . ($isVisibleRecord ? 'font-weight:bold; ' : '') . 'width:40%; padding-right: 3px;">' . $label . '</td>
						<td>' . $value . '</td>
					</tr>
				';
			}

			$content .= '</table>';
		}

		return $content;
	}


	protected function getTargetPage() {
		$targetPageId = $this->getFieldFromFlexform('targetPage');

		if (!empty($targetPageId)) {
			$page = t3lib_BEfunc::getRecord('pages', $targetPageId, 'title');
			$this->settings['Target Page'] = '[' . (int) $targetPageId . '] ' . $page['title'];
		}
	}

	protected function getFilter() {
		$filter = $this->getFieldFromFlexform('filter', 'sQuery');

		if (!empty($filter)) {
			$this->settings['Filter'] = $filter;
		}
	}

	protected function getSorting() {
		$sorting = $this->getFieldFromFlexform('sortBy', 'sQuery');

		if (!empty($sorting)) {
			$this->settings['Sorting'] = $sorting;
		}
	}

	protected function getResultsPerPage() {
		$resultsPerPage = $this->getFieldFromFlexform('resultsPerPage', 'sQuery');

		if (!empty($resultsPerPage)) {
			$this->settings['Results per Page'] = $resultsPerPage;
		}
	}

	protected function getBoostFuntion() {
		$boostFunction = $this->getFieldFromFlexform('boostFunction', 'sQuery');

		if (!empty($boostFunction)) {
			$this->settings['Boost Function'] = $boostFunction;
		}
	}

	protected function getBoostQuery() {
		$boostQuery = $this->getFieldFromFlexform('boostQuery', 'sQuery');

		if (!empty($boostQuery)) {
			$this->settings['Boost Query'] = $boostQuery;
		}
	}

	protected function getTemplateFile() {
		$templateFile = $this->getFieldFromFlexform('templateFile', 'sOptions');

		if (!empty($templateFile)) {
			$this->settings['Template'] = $templateFile;
		}
	}


	/**
	 * Gets a field's value from flexform configuration,
	 * will check if flexform configuration is available.
	 *
	 * @param string $key name of the key
	 * @param string $sheet name of the sheet
	 * @return NULL if nothing found, value if found
	 */
	protected function getFieldFromFlexform($fieldName, $sheetName = 'sDEF') {
		return $this->flexformData[$sheetName]['lDEF'][$fieldName]['vDEF'];
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/pluginbase/class.tx_solr_pluginbase_backendsummary.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/pluginbase/class.tx_solr_pluginbase_backendsummary.php']);
}

?>