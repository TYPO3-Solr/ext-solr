<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Backend;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use TYPO3\CMS\Backend\Form\Exception as BackendFormException;
use TYPO3\CMS\Backend\Form\FormResultFactory;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue indexing configuration selector form field.
 */
class IndexingConfigurationSelectorField
{
    /**
     * Site used to determine indexing configurations
     */
    protected Site $site;

    /**
     * Form element name
     */
    protected string $formElementName = 'tx_solr-index-queue-indexing-configuration-selector';

    /**
     * Selected values
     */
    protected array $selectedValues = [];

    /**
     * Constructor
     *
     * @param Site $site The site to use to determine indexing configurations
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Sets the form element name.
     *
     * @param string $formElementName Form element name
     */
    public function setFormElementName(string $formElementName): void
    {
        $this->formElementName = $formElementName;
    }

    /**
     * Gets the form element name.
     *
     * @return string form element name
     * @noinspection PhpUnused
     */
    public function getFormElementName(): string
    {
        return $this->formElementName;
    }

    /**
     * Sets the selected values.
     */
    public function setSelectedValues(array $selectedValues): void
    {
        $this->selectedValues = $selectedValues;
    }

    /**
     * Gets the selected values.
     *
     * @noinspection PhpUnused
     */
    public function getSelectedValues(): array
    {
        return $this->selectedValues;
    }

    /**
     * Renders a field to select which indexing configurations to initialize.
     *
     * @return string Markup for the select field
     * @throws BackendFormException
     */
    public function render(): string
    {
        // transform selected values into the format used by TCEforms
        $selectedValues = $this->selectedValues;
        $tablesToIndex = $this->getIndexQueueConfigurationTableMap();

        $formField = $this->renderSelectCheckbox($this->buildSelectorItems($tablesToIndex), $selectedValues);

        // need to wrap the field in a TCEforms table to make the CSS apply
        $form = [];
        $form[] = '<div class="typo3-TCEforms tx_solr-TCEforms">';
        $form[] = $formField;
        $form[] = '</div>';

        return implode(LF, $form);
    }

    /**
     * Builds a map of indexing configuration names to tables to index.
     *
     * @return array Indexing configuration to database table map
     */
    protected function getIndexQueueConfigurationTableMap(): array
    {
        $indexingTableMap = [];

        $solrConfiguration = $this->site->getSolrConfiguration();
        $configurationNames = $solrConfiguration->getEnabledIndexQueueConfigurationNames();
        foreach ($configurationNames as $configurationName) {
            $indexingTableMap[$configurationName] = $solrConfiguration->getIndexQueueTypeOrFallbackToConfigurationName($configurationName);
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
    protected function buildSelectorItems(array $tablesToIndex): array
    {
        $selectorItems = [];
        /** @var IconRegistry $iconRegistry */
        $iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $defaultIcon = 'mimetypes-other-other';

        foreach ($tablesToIndex as $configurationName => $tableName) {
            if (isset($GLOBALS['TCA'][$tableName])) {
                $icon = $iconFactory->mapRecordTypeToIconIdentifier($tableName, []);
                if ($icon === $iconRegistry->getDefaultIconIdentifier() || !$iconRegistry->isRegistered($icon)) {
                    $icon = $defaultIcon;
                }
            } else {
                $icon = $defaultIcon;
            }

            $labelTableName = '';
            if ($configurationName !== $tableName) {
                $labelTableName = ' (' . $tableName . ')';
            }

            $selectorItems[] = [
                'label' => $configurationName . $labelTableName,
                'value' => $configurationName,
                'icon' => $icon,
            ];
        }

        return $selectorItems;
    }

    /**
     * @throws BackendFormException
     */
    protected function renderSelectCheckbox(array $items, ?array $selectedValues = []): string
    {
        $parameterArray = [
            'fieldChangeFunc' => [],
            'itemFormElID' => $this->formElementName,
            'itemFormElName' => $this->formElementName,
            'itemFormElValue' => $selectedValues,
            'fieldConf' => ['label' => '', 'config' => ['items' => $items]],
            'fieldTSConfig' => ['noMatchingValue_label' => ''],
        ];

        /** @var NodeFactory $nodeFactory */
        $nodeFactory = GeneralUtility::makeInstance(NodeFactory::class);
        $options = [
            'type' => 'select',
            'renderType' => 'selectCheckBox',
            'table' => 'tx_solr_classes_backend_indexingconfigurationselector',
            'tableName' => 'tx_solr_classes_backend_indexingconfigurationselector',
            'fieldName' => 'additionalFields',
            'databaseRow' => ['uid' => 0],
            'parameterArray' => $parameterArray,
            'processedTca' => ['columns' => ['additionalFields' => ['config' => ['type' => 'select']]]],
        ];
        $options['parameterArray']['fieldConf']['config']['items'] = $items;
        $options['parameterArray']['fieldTSConfig']['noMatchingValue_label'] = '';

        $selectCheckboxResult = $nodeFactory->create($options)->render();
        $formResult = GeneralUtility::makeInstance(FormResultFactory::class)->create($selectCheckboxResult);

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        foreach ($formResult->stylesheetFiles as $stylesheetFile) {
            $pageRenderer->addCssFile($stylesheetFile);
        }
        foreach ($formResult->javaScriptModules as $module) {
            $pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction($module);
        }

        return $formResult->html;
    }
}
