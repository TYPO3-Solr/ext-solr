<?php

namespace ApacheSolrForTypo3\Solr\Controller\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Backend\View\PageLayoutView;

/**
 * Summary to display flexform settings in the page layout backend module.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class PageModuleSummary
{
    /**
     * PageLayoutView
     *
     * @var PageLayoutView
     */
    protected $pageLayoutView;

    /**
     * @var array
     */
    protected $pluginContentElement = [];

    /**
     * @var array
     */
    protected $flexformData = [];

    /**
     * @var array
     */
    protected $settings = [];

    /**
     * Returns information about a plugin's flexform configuration
     *
     * @param array $parameters Parameters to the hook
     * @return string Plugin configuration information
     */
    public function getSummary(array $parameters)
    {
        $this->initialize($parameters['row'], $parameters['pObj']);

        $this->addTargetPage();
        $this->addSettingFromFlexForm('Filter', 'search.query.filter');
        $this->addSettingFromFlexForm('Sorting', 'search.query.sortBy');
        $this->addSettingFromFlexForm('Results per Page', 'search.results.resultsPerPage');
        $this->addSettingFromFlexForm('Boost Function', 'search.query.boostFunction');
        $this->addSettingFromFlexForm('Boost Query', 'search.query.boostQuery');
        $this->addSettingFromFlexForm('Tie Breaker', 'search.query.tieParameter');
        $this->addSettingFromFlexForm('Template', 'view.templateFiles.results');
        return $this->render();
    }

    /**
     * @param array $contentElement
     * @param PageLayoutView $pObj
     */
    protected function initialize(array $contentElement, PageLayoutView $pObj)
    {
        $this->pageLayoutView = $pObj;

        /** @var $service \TYPO3\CMS\Core\Service\FlexFormService::class */
        $service = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Service\FlexFormService::class);
        $this->flexformData = $service->convertFlexFormContentToArray($contentElement['pi_flexform']);
        $this->pluginContentElement = $contentElement;
    }

    /**
     * Adds the target page to the settings.
     */
    protected function addTargetPage()
    {
        $targetPageId = $this->getFieldFromFlexform('search.targetPage');
        if (!empty($targetPageId)) {
            $page = BackendUtility::getRecord('pages', $targetPageId, 'title');
            $this->settings['Target Page'] = '[' . (int)$targetPageId . '] ' . $page['title'];
        }
    }

    /**
     * @param string $settingName
     * @param string $flexFormField
     */
    protected function addSettingFromFlexForm($settingName, $flexFormField)
    {
        $value = $this->getFieldFromFlexform($flexFormField);

        if (is_array($value)) {
            $value = $this->addSettingFromFlexFormArray($settingName, $value);
        }
        $this->addSettingIfNotEmpty($settingName, (string)$value);
    }

    /**
     * @param string $settingName
     * @param array $values
     * @return bool
     */
    protected function addSettingFromFlexFormArray($settingName, $values)
    {
        foreach ($values as $item) {
            if (!isset($item['field'])) {
                continue;
            }
            $field = $item['field'];

            $label = $settingName . ' ';
            $label .= isset($field['field']) ? $field['field'] : '';
            $fieldValue = isset($field['value']) ? $field['value'] : '';
            $this->addSettingIfNotEmpty($label, (string)$fieldValue);
        }
    }

    /**
     * @param string $settingName
     * @param string $value
     */
    protected function addSettingIfNotEmpty($settingName, $value)
    {
        if (!empty($value)) {
            $this->settings[$settingName] = $value;
        }
    }

    /**
     * Gets a field's value from flexform configuration, will check if
     * flexform configuration is available.
     *
     * @param string $path name of the field
     * @return string if nothing found, value if found
     */
    protected function getFieldFromFlexform($path)
    {
        return ObjectAccess::getPropertyPath($this->flexformData, $path);
    }

    /**
     * @return string
     */
    protected function render()
    {
        /** @var $standaloneView StandaloneView */
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Templates/Backend/PageModule/Summary.html')
        );

        $standaloneView->assignMultiple([
            'pluginLabel' => $this->getPluginLabel(),
            'hidden' => $this->pluginContentElement['hidden'],
            'settings' => $this->settings,
        ]);
        return $standaloneView->render();
    }

    /**
     * Returns the plugin label
     *
     * @return string
     */
    protected function getPluginLabel()
    {
        $label = BackendUtility::getLabelFromItemListMerged($this->pluginContentElement['pid'], 'tt_content', 'list_type', $this->pluginContentElement['list_type']);
        if (!empty($label)) {
            $label = $this->getLanguageService()->sL($label);
        } else {
            $label = sprintf($this->getLanguageService()->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'), $this->pluginContentElement['list_type']);
        }

        return $this->pageLayoutView->linkEditContent(htmlspecialchars($label), $this->pluginContentElement);
    }

    /**
     * Returns the language service
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
