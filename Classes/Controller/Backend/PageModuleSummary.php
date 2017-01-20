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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Summary to display flexform settings in the page layout backend module.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class PageModuleSummary
{
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
        $this->initialize($parameters['row']);

        $this->addTargetPage();
        $this->addSettingFromFlexForm('Filter', 'filter');
        $this->addSettingFromFlexForm('Sorting', 'sortBy');
        $this->addSettingFromFlexForm('Results per Page', 'resultsPerPage');
        $this->addSettingFromFlexForm('Boost Function', 'boostFunction');
        $this->addSettingFromFlexForm('Boost Query', 'boostQuery');
        $this->addSettingFromFlexForm('Template', 'templateFile', 'sOptions');

        return $this->render();
    }

    /**
     * @param array $contentElement
     */
    protected function initialize(array $contentElement)
    {
        $this->pluginContentElement = $contentElement;

        $flexformAsArray = GeneralUtility::xml2array($contentElement['pi_flexform']);
        $this->flexformData = $flexformAsArray['data'];
    }

    /**
     * Adds the target page to the settings.
     */
    protected function addTargetPage()
    {
        $targetPageId = $this->getFieldFromFlexform('targetPage');
        if (!empty($targetPageId)) {
            $page = BackendUtility::getRecord('pages', $targetPageId, 'title');
            $this->settings['Target Page'] = '[' . (int)$targetPageId . '] ' . $page['title'];
        }
    }

    /**
     * @param string $settingName
     * @param string $flexFormField
     * @param string $sheetName
     */
    protected function addSettingFromFlexForm($settingName, $flexFormField, $sheetName = 'sQuery')
    {
        $templateFile = $this->getFieldFromFlexform($flexFormField, $sheetName);

        if (!empty($templateFile)) {
            $this->settings[$settingName] = $templateFile;
        }
    }

    /**
     * Gets a field's value from flexform configuration, will check if
     * flexform configuration is available.
     *
     * @param string $fieldName name of the field
     * @param string $sheetName name of the sheet, defaults to "sDEF"
     * @return string if nothing found, value if found
     */
    protected function getFieldFromFlexform($fieldName, $sheetName = 'sDEF')
    {
        $fieldValue = '';

        if (array_key_exists($sheetName,
                $this->flexformData) && array_key_exists($fieldName,
                $this->flexformData[$sheetName]['lDEF'])
        ) {
            $fieldValue = $this->flexformData[$sheetName]['lDEF'][$fieldName]['vDEF'];
        }

        return $fieldValue;
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
            'hidden' => $this->pluginContentElement['hidden'],
            'settings' => $this->settings,
        ]);

        return $standaloneView->render();
    }
}
