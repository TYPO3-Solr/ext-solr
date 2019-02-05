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
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use TYPO3\CMS\Backend\Form\FormResultCompiler;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue indexing configuration selector form field.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexingConfigurationSelectorField
{

    /**
     * Site used to determine indexing configurations
     *
     * @var Site
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
    protected $selectedValues = [];

    /**
     * Constructor
     *
     * @param Site $site The site to use to determine indexing configurations
     */
    public function __construct(Site $site = null)
    {
        $this->site = $site;
    }

    /**
     * Sets the form element name.
     *
     * @param string $formElementName Form element name
     */
    public function setFormElementName($formElementName)
    {
        $this->formElementName = $formElementName;
    }

    /**
     * Gets the form element name.
     *
     * @return string form element name
     */
    public function getFormElementName()
    {
        return $this->formElementName;
    }

    /**
     * Sets the selected values.
     *
     * @param array $selectedValues
     */
    public function setSelectedValues(array $selectedValues)
    {
        $this->selectedValues = $selectedValues;
    }

    /**
     * Gets the selected values.
     *
     * @return array
     */
    public function getSelectedValues()
    {
        return $this->selectedValues;
    }

    /**
     * Renders a field to select which indexing configurations to initialize.
     *
     * Uses \TYPO3\CMS\Backend\Form\FormEngine.
     *
     * @return string Markup for the select field
     */
    public function render()
    {
        // transform selected values into the format used by TCEforms
        $selectedValues = $this->selectedValues;
        $tablesToIndex = $this->getIndexQueueConfigurationTableMap();

        $formField = $this->renderSelectCheckbox($this->buildSelectorItems($tablesToIndex), $selectedValues);

        // need to wrap the field in a TCEforms table to make the CSS apply
        $form[] = '<div class="typo3-TCEforms tx_solr-TCEforms">';
        $form[] = $formField;
        $form[] = '</div>';

        return implode(LF, $form);
    }

    /**
     * Builds a map of indexing configuration names to tables to to index.
     *
     * @return array Indexing configuration to database table map
     */
    protected function getIndexQueueConfigurationTableMap()
    {
        $indexingTableMap = [];

        $solrConfiguration = $this->site->getSolrConfiguration();
        $configurationNames = $solrConfiguration->getEnabledIndexQueueConfigurationNames();
        foreach ($configurationNames as $configurationName) {
            $indexingTableMap[$configurationName] = $solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($configurationName);
        }

        return $indexingTableMap;
    }

    /**
     * Builds the items to render in the TCEforms select field.
     *
     * @param array $tablesToIndex A map of indexing configuration to database tables
     *
     * @return array Selectable items for the TCEforms select field
     */
    protected function buildSelectorItems(array $tablesToIndex)
    {
        $selectorItems = [];

        foreach ($tablesToIndex as $configurationName => $tableName) {
            $icon = 'tcarecords-' . $tableName . '-default';
            if ($tableName === 'pages') {
                $icon = 'apps-pagetree-page-default';
            }

            $labelTableName = '';
            if ($configurationName != $tableName) {
                $labelTableName = ' (' . $tableName . ')';
            }

            $selectorItems[] = [$configurationName . $labelTableName, $configurationName, $icon];
        }

        return $selectorItems;
    }

    /**
     * @param array $items
     * @param string $selectedValues
     *
     * @return string
     * @throws \TYPO3\CMS\Backend\Form\Exception
     */
    protected function renderSelectCheckbox($items, $selectedValues)
    {
        $parameterArray = [
            'fieldChangeFunc' => [],
            'itemFormElName' => $this->formElementName,
            'itemFormElValue' => $selectedValues,
            'fieldConf' => ['config' => ['items' => $items]],
            'fieldTSConfig' => ['noMatchingValue_label' => '']
        ];

        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        $options = [
            'renderType' => 'selectCheckBox', 'table' => 'tx_solr_classes_backend_indexingconfigurationselector',
            'fieldName' => 'additionalFields', 'databaseRow' => [], 'parameterArray' => $parameterArray
        ];
        $options['parameterArray']['fieldConf']['config']['items'] = $items;
        $options['parameterArray']['fieldTSConfig']['noMatchingValue_label'] = '';

        $selectCheckboxResult = $nodeFactory->create($options)->render();
        $formResultCompiler = GeneralUtility::makeInstance(FormResultCompiler::class);
        $formResultCompiler->mergeResult($selectCheckboxResult);

        $formHtml = isset($selectCheckboxResult['html']) ? $selectCheckboxResult['html'] : '';
        $content = $formResultCompiler->addCssFiles() . $formHtml . $formResultCompiler->printNeededJSFunctions();

        return $content;
    }
}
