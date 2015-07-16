<?php
namespace ApacheSolrForTypo3\Solr\Backend;
/***************************************************************
*  Copyright notice
*
*  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue indexing configuration selector form field.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class IndexingConfigurationSelectorField {

	/**
	 * Site used to determine indexing configurations
	 *
	 * @var \Tx_Solr_Site
	 */
	protected $site;

	/**
	 * Form element name
	 *
	 * @var string
	 */
	protected $formElementName = 'tx_solr-index-queue-indexing-configuration-selector';

	/**
	 * Selected values
	 *
	 * @var array
	 */
	protected $selectedValues = array();


	/**
	 * Constructor
	 *
	 * @param \Tx_Solr_Site $site The site to use to determine indexing configurations
	 */
	public function __construct(\Tx_Solr_Site $site = NULL) {
		$this->site = $site;
	}

	/**
	 * Sets the form element name.
	 *
	 * @param string $formElementName Form element name
	 */
	public function setFormElementName($formElementName) {
		$this->formElementName = $formElementName;
	}

	/**
	 * Gets the form element name.
	 *
	 * @return string form element name
	 */
	public function getFormElementName() {
		return $this->formElementName;
	}

	/**
	 * Sets the selected values.
	 *
	 * @param array $selectedValues
	 */
	public function setSelectedValues(array $selectedValues) {
		$this->selectedValues = $selectedValues;
	}

	/**
	 * Gets the selected values.
	 *
	 * @return array
	 */
	public function getSelectedValues() {
		return $this->selectedValues;
	}

	/**
	 * Renders a field to select which indexing configurations to initialize.
	 *
	 * Uses \TYPO3\CMS\Backend\Form\FormEngine.
	 *
	 *  @return string Markup for the select field
	 */
	public function render() {
			// transform selected values into the format used by TCEforms
		$selectedValues = array();
		foreach ($this->selectedValues as $selectedValue) {
			$selectedValues[] = $selectedValue . '|1';
		}
		$selectedValues = implode(',', $selectedValues);

		$tablesToIndex = $this->getIndexQueueConfigurationTableMap();

		$formField = $this->renderSelectCheckbox($this->buildSelectorItems($tablesToIndex), $selectedValues);

			// need to wrap the field in a TCEforms table to make the CSS apply
		$form = '
		<table class="typo3-TCEforms tx_solr-TCEforms">
			<tr>
				<td>' . "\n" . $formField . "\n" . '</td>
			</tr>
		</table>
		';

		return $form;
	}

	/**
	 * Builds a map of indexing configuration names to tables to to index.
	 *
	 * @return array Indexing configuration to database table map
	 */
	protected function getIndexQueueConfigurationTableMap() {
		$indexingTableMap = array();

		$solrConfiguration = \Tx_Solr_Util::getSolrConfigurationFromPageId($this->site->getRootPageId());

		foreach ($solrConfiguration['index.']['queue.'] as $name => $configuration) {
			if (is_array($configuration)) {
				$name = substr($name, 0, -1);

				if ($solrConfiguration['index.']['queue.'][$name]) {
					$table = $name;
					if ($solrConfiguration['index.']['queue.'][$name . '.']['table']) {
						$table = $solrConfiguration['index.']['queue.'][$name . '.']['table'];
					}

					$indexingTableMap[$name] = $table;
				}
			}
		}

		return $indexingTableMap;
	}

	/**
	 * Builds the items to render in the TCEforms select field.
	 *
	 * @param array $tablesToIndex A map of indexing configuration to database tables
	 * @return array Selectable items for the TCEforms select field
	 */
	protected function buildSelectorItems(array $tablesToIndex) {
		$selectorItems = array();

		foreach ($tablesToIndex as $configurationName => $tableName) {
			$icon = 'tcarecords-' . $tableName . '-default';
			if ($tableName == 'pages') {
				$icon = 'apps-pagetree-page-default';
			}

			$labelTableName = '';
			if ($configurationName != $tableName) {
				$labelTableName = ' (' . $tableName . ')';
			}

			$selectorItems[] = array(
				$configurationName . $labelTableName,
				$configurationName,
				$icon
			);
		}

		return $selectorItems;
	}

	/**
	 * @param array $items
	 * @param string $selectedValues
	 * @return string
	 * @throws \TYPO3\CMS\Backend\Form\Exception
	 */
	protected function renderSelectCheckbox($items, $selectedValues) {
		$parameterArray = array(
			'fieldChangeFunc' => array(),
			'itemFormElName'  => $this->formElementName,
			'itemFormElValue' => $selectedValues,
			'fieldConf' => array(
				'config' => array(
					'items' => $items
				)
			),
			'fieldTSConfig' => array(
				'noMatchingValue_label' => ''
			)
		);

		$selectFieldRenderer = $formEngine = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Form\\FormEngine');
		if (!method_exists($selectFieldRenderer, 'getSingleField_typeSelect_checkbox')) {
			if (class_exists('TYPO3\\CMS\Backend\\Form\\Element\\SelectElement')) {
				// TYPO3 CMS 7.2
				$selectFieldRenderer = GeneralUtility::makeInstance('TYPO3\\CMS\Backend\\Form\\Element\\SelectElement', $formEngine);
			} elseif (class_exists('TYPO3\\CMS\\Backend\\Form\\Element\\SelectCheckBoxElement')) {
				// TYPO3 CMS >= 7.3
				/** @var \TYPO3\CMS\Backend\Form\NodeFactory $nodeFactory */
				$nodeFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Form\\NodeFactory');
				$options = array(
					'renderType' => 'selectCheckBox',
					'table' => 'tx_solr_classes_backend_indexingconfigurationselector',
					'fieldName' => 'additionalFields',
					'databaseRow' => array(),
					'parameterArray' => $parameterArray
				);
				$options['parameterArray']['fieldConf']['config']['items'] = $items;
				$options['parameterArray']['fieldTSConfig']['noMatchingValue_label'] = '';
				$selectCheckboxResult = $nodeFactory->create($options)->render();

				return $selectCheckboxResult['html'];
			}
		}

		if (method_exists($selectFieldRenderer, 'getSingleField_typeSelect_checkbox')) {
			return $selectFieldRenderer->getSingleField_typeSelect_checkbox(
				'', // table
				'', // field
				'', // row
				$parameterArray, // array with additional configuration options
				array(), // config,
				$items, // items
				'' // Label for no-matching-value
			);
		}

		return '';
	}

}