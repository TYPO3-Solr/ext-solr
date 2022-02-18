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
use TYPO3\CMS\Backend\Form\FormResultCompiler;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Core selector form field.
 *
 * @author Jens Jacobsen <typo3@jens-jacobsen.de>
 */
class CoreSelectorField
{
    /**
     * Site used to determine cores
     *
     * @var Site
     */
    protected Site $site;

    /**
     * Form element name
     *
     * @var string
     */
    protected string $formElementName = 'tx_solr-index-optimize-core-selector';

    /**
     * Selected values
     *
     * @var array
     */
    protected array $selectedValues = [];

    /**
     * Constructor
     *
     * @param Site $site The site to use to determine cores
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
    public function setFormElementName(string $formElementName)
    {
        $this->formElementName = $formElementName;
    }

    /**
     * Gets the form element name.
     *
     * @return string form element name
     */
    public function getFormElementName(): string
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
    public function getSelectedValues(): array
    {
        return $this->selectedValues;
    }

    /**
     * Renders a field to select which cores to optimize.
     *
     * @return string Markup for the select field
     * @throws BackendFormException
     */
    public function render(): string
    {
        // transform selected values into the format used by TCEforms
        $selectedValues = $this->selectedValues;
        $cores = $this->getLanguageUidCoreMap();

        $formField = $this->renderSelectCheckbox($this->buildSelectorItems($cores), $selectedValues);

        // need to wrap the field in a TCEforms table to make the CSS apply
        $form = [];
        $form[] = '<div class="typo3-TCEforms tx_solr-TCEforms">';
        $form[] = $formField;
        $form[] = '</div>';

        return implode(LF, $form);
    }

    /**
     * Builds a map of language uids to corepaths to optimize.
     *
     * @return array language uids to core paths map
     */
    protected function getLanguageUidCoreMap(): array
    {
        $coreTableMap = [];
        $cores = $this->site->getAllSolrConnectionConfigurations();
        foreach ($cores as $languageUid => $core) {
            $corePath = $core['write']['path'];
            $coreTableMap[$languageUid] = $corePath;
        }
        return $coreTableMap;
    }

    /**
     * Builds the items to render in the TCEforms select field.
     *
     * @param array $coresToOptimize A map of indexing configuration to database tables
     *
     * @return array Selectable items for the TCEforms select field
     */
    protected function buildSelectorItems(array $coresToOptimize): array
    {
        $selectorItems = [];

        foreach ($coresToOptimize as $corePath) {
            $icon = 'module-searchbackend_SolrCoreoptimization';
            $corePath = rtrim($corePath, '/');
            $selectorItems[] = [$corePath, $corePath, $icon];
        }

        return $selectorItems;
    }

    /**
     * @param array $items
     * @param array $selectedValues
     *
     * @return string
     * @throws BackendFormException
     */
    protected function renderSelectCheckbox(array $items, array $selectedValues): string
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
            'renderType' => 'selectCheckBox', 'table' => 'tx_solr_classes_backend_coreselector',
            'fieldName' => 'additionalFields', 'databaseRow' => [], 'parameterArray' => $parameterArray
        ];

        $selectCheckboxResult = $nodeFactory->create($options)->render();
        $formResultCompiler = GeneralUtility::makeInstance(FormResultCompiler::class);
        $formResultCompiler->mergeResult($selectCheckboxResult);

        $formHtml = $selectCheckboxResult['html'] ?? '';
        return $formResultCompiler->addCssFiles() . $formHtml . $formResultCompiler->printNeededJSFunctions();
    }
}
