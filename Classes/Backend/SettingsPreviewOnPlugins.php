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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Fluid\View\StandaloneView;

use function str_starts_with;

/**
 * Summary to display flexform settings of EXT:solr plugin in BE page module.
 */
class SettingsPreviewOnPlugins
{
    protected array $pluginsTtContentRecord;
    private array $flexformData;

    protected array $settings = [];

    public function __construct(
        protected FlexFormService $flexFormService
    ) {
    }

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        $this->pluginsTtContentRecord = $event->getRecord();
        if (
            $event->getTable() !== 'tt_content'
            || !str_starts_with($this->pluginsTtContentRecord['list_type'], 'solr_pi_')
        ) {
            return;
        }
        $this->flexformData = $this->flexFormService->convertFlexFormContentToArray($this->pluginsTtContentRecord['pi_flexform'] ?? '');
        $event->setPreviewContent($this->getPreviewContent());
    }

    /**
     * @return string
     */
    protected function getPreviewContent(): string
    {
        $this->collectSummary();

        /* @var StandaloneView $standaloneView */
        $standaloneView = GeneralUtility::makeInstance(StandaloneView::class);
        $standaloneView->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:solr/Resources/Private/Templates/Backend/PageModule/Summary.html')
        );

        $standaloneView->assignMultiple([
            'pluginLabel' => $this->getPluginLabel(),
            'hidden' => $this->pluginsTtContentRecord['hidden'] ?? 0,
            'settings' => $this->settings,
        ]);
        return $standaloneView->render();
    }

    /**
     * Returns information about a plugin's flexform configuration
     */
    public function collectSummary(): void
    {
        $this->addTargetPage();
        $this->addSettingFromFlexForm('Filter', 'search.query.filter');
        $this->addSettingFromFlexForm('Sorting', 'search.query.sortBy');
        $this->addSettingFromFlexForm('Results per Page', 'search.results.resultsPerPage');
        $this->addSettingFromFlexForm('Boost Function', 'search.query.boostFunction');
        $this->addSettingFromFlexForm('Boost Query', 'search.query.boostQuery');
        $this->addSettingFromFlexForm('Tie Breaker', 'search.query.tieParameter');
        $this->addSettingFromFlexForm('Template', 'view.templateFiles.results');
    }

    /**
     * Adds the target page to the settings.
     */
    protected function addTargetPage(): void
    {
        $targetPageId = $this->getFieldFromFlexform('search.targetPage');
        if (!empty($targetPageId)) {
            $page = BackendUtility::getRecord('pages', $targetPageId, 'title')
                ?? ['title' => 'ERROR: page is gone'];
            $this->settings['Target Page'] = '[' . (int)$targetPageId . '] ' . $page['title'];
        }
    }

    /**
     * @param string $settingName
     * @param string $flexFormField
     */
    protected function addSettingFromFlexForm(string $settingName, string $flexFormField): void
    {
        $value = $this->getFieldFromFlexform($flexFormField);

        if (is_array($value)) {
            $this->addSettingFromFlexFormArray($settingName, $value);
            return;
        }
        $this->addSettingIfNotEmpty($settingName, (string)$value);
    }

    /**
     * @param string $settingName
     * @param array $values
     */
    protected function addSettingFromFlexFormArray(string $settingName, array $values): void
    {
        foreach ($values as $item) {
            if (!isset($item['field'])) {
                continue;
            }
            $field = $item['field'];

            $label = $settingName . ' ';
            $label .= $field['field'] ?? '';
            $fieldValue = $field['value'] ?? '';
            $this->addSettingIfNotEmpty($label, (string)$fieldValue);
        }
    }

    /**
     * @param string $settingName
     * @param string $value
     */
    protected function addSettingIfNotEmpty(string $settingName, string $value): void
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
     * @return mixed|null if nothing found, value if found
     */
    protected function getFieldFromFlexform(string $path): mixed
    {
        return ObjectAccess::getPropertyPath($this->flexformData, $path);
    }

    /**
     * Returns the plugin label
     *
     * @return string
     */
    protected function getPluginLabel(): string
    {
        $label = BackendUtility::getLabelFromItemListMerged($this->pluginsTtContentRecord['pid'], 'tt_content', 'list_type', $this->pluginsTtContentRecord['list_type']);
        if (!empty($label)) {
            $label = $this->getLanguageService()->sL($label);
        } else {
            $label = sprintf($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'), $this->pluginsTtContentRecord['list_type']);
        }

        return $label;
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
