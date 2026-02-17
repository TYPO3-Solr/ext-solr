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
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Localization\LanguageService;

use function str_starts_with;

/**
 * Summary to display flexform settings of EXT:solr plugin in BE page module.
 */
class SettingsPreviewOnPlugins
{
    protected array $pluginsTtContentRecord;

    protected FlexFormFieldValues $flexformData;

    protected array $settings = [];

    protected PageContentPreviewRenderingEvent $event;

    public function __construct(
        protected BackendViewFactory $backendViewFactory,
    ) {}

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        $this->pluginsTtContentRecord = $event->getRecord()->toArray();
        if (
            $event->getTable() !== 'tt_content'
            || !str_starts_with($this->pluginsTtContentRecord['CType'], 'solr_pi_')
        ) {
            return;
        }
        $this->event = $event;
        $this->flexformData = $this->pluginsTtContentRecord['pi_flexform'];
        $event->setPreviewContent($this->getPreviewContent());
    }

    protected function getPreviewContent(): string
    {
        $this->collectSummary();

        $request = $this->event->getPageLayoutContext()->getCurrentRequest();
        $view = $this->backendViewFactory->create($request, ['apache-solr-for-typo3/solr']);
        $view->assignMultiple([
            'pluginLabel' => $this->getPluginLabel(),
            'hidden' => $this->pluginsTtContentRecord['hidden'] ?? 0,
            'settings' => $this->settings,
        ]);

        return $view->render('Backend/PageModule/Summary');
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
        $targetPageId = (int)(string)$this->getFieldFromFlexform('search.targetPage');
        if ($targetPageId > 0) {
            $page = BackendUtility::getRecord('pages', $targetPageId, 'title')
                ?? ['title' => 'ERROR: page is gone'];
            $this->settings['Target Page'] = '[' . $targetPageId . '] ' . $page['title'];
        }
    }

    protected function addSettingFromFlexForm(string $settingName, string $flexFormField): void
    {
        $value = $this->getFieldFromFlexform($flexFormField);

        if (is_array($value)) {
            $this->addSettingFromFlexFormArray($settingName, $value);
            return;
        }
        $this->addSettingIfNotEmpty($settingName, (string)$value);
    }

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
        if ($this->flexformData->has($path)) {
            return $this->flexformData->get($path);
        }
        return null;
    }

    /**
     * Returns the plugin label
     */
    protected function getPluginLabel(): string
    {
        $label = BackendUtility::getLabelFromItemListMerged(
            $this->pluginsTtContentRecord['pid'],
            'tt_content',
            'CType',
            $this->pluginsTtContentRecord['CType'],
            $this->pluginsTtContentRecord,
        );
        if (!empty($label)) {
            $label = $this->getLanguageService()->sL($label);
        } else {
            $label = sprintf(
                $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'),
                $this->pluginsTtContentRecord['CType'],
            );
        }

        return $label;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
