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

namespace ApacheSolrForTypo3\Solr\EventListener\Backend;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\Exception\FlexFieldPropertyException;
use TYPO3\CMS\Core\Domain\Exception\FlexFieldPropertyNotFoundException;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Localization\LanguageService;

use function str_starts_with;

/**
 * Summary to display FlexForm settings of EXT:solr plugin in BE page module.
 */
final readonly class SettingsPreviewOnPluginsEventListener
{
    public function __construct(
        private BackendViewFactory $backendViewFactory,
    ) {}

    #[AsEventListener(
        identifier: 'solr.plugin.be.settings.preview',
    )]
    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        $pluginsTtContentRecord = $event->getRecord()->toArray();

        if (
            $event->getTable() !== 'tt_content'
            || !str_starts_with($pluginsTtContentRecord['CType'], 'solr_pi_')
        ) {
            return;
        }

        $event->setPreviewContent(
            $this->getPreviewContent($event, $pluginsTtContentRecord),
        );
    }

    private function getPreviewContent(
        PageContentPreviewRenderingEvent $event,
        array $pluginsTtContentRecord,
    ): string {
        $settings = $this->collectSummary($pluginsTtContentRecord['pi_flexform']);

        $request = $event->getPageLayoutContext()->getCurrentRequest();

        $view = $this->backendViewFactory->create($request, ['apache-solr-for-typo3/solr']);
        $view->assignMultiple([
            'pluginLabel' => $this->getPluginLabel($pluginsTtContentRecord),
            'hidden' => $pluginsTtContentRecord['hidden'] ?? 0,
            'settings' => $settings,
        ]);

        return $view->render('Backend/PageModule/Summary');
    }

    /**
     * Returns information about a plugin's FlexForm configuration
     */
    private function collectSummary(FlexFormFieldValues $flexFormData): array
    {
        $settings = [];

        $this->addTargetPage($settings, $flexFormData);

        $this->addSettingFromFlexForm('Filter', 'search.query.filter', $flexFormData, $settings);
        $this->addSettingFromFlexForm('Sorting', 'search.query.sortBy', $flexFormData, $settings);
        $this->addSettingFromFlexForm('Results per Page', 'search.results.resultsPerPage', $flexFormData, $settings);
        $this->addSettingFromFlexForm('Boost Function', 'search.query.boostFunction', $flexFormData, $settings);
        $this->addSettingFromFlexForm('Boost Query', 'search.query.boostQuery', $flexFormData, $settings);
        $this->addSettingFromFlexForm('Tie Breaker', 'search.query.tieParameter', $flexFormData, $settings);
        $this->addSettingFromFlexForm('Template', 'view.templateFiles.results', $flexFormData, $settings);

        return $settings;
    }

    /**
     * Adds the target page to the settings.
     */
    private function addTargetPage(array &$settings, FlexFormFieldValues $flexFormData): void
    {
        $targetPageId = (int)(string)$this->getFieldFromFlexForm('search.targetPage', $flexFormData);
        if ($targetPageId > 0) {
            $page = BackendUtility::getRecord('pages', $targetPageId, 'title')
                ?? ['title' => 'ERROR: page is gone'];

            $settings['Target Page'] = '[' . $targetPageId . '] ' . $page['title'];
        }
    }

    private function addSettingFromFlexForm(
        string $settingName,
        string $flexFormField,
        FlexFormFieldValues $flexFormData,
        array &$settings,
    ): void {
        $value = $this->getFieldFromFlexForm($flexFormField, $flexFormData);

        if (is_array($value)) {
            $this->addSettingFromFlexFormArray($settingName, $value, $settings);
            return;
        }

        $this->addSettingIfNotEmpty($settingName, (string)$value, $settings);
    }

    private function addSettingFromFlexFormArray(string $settingName, array $values, array &$settings): void
    {
        foreach ($values as $item) {
            if (!isset($item['field'])) {
                continue;
            }

            $field = $item['field'];

            $label = $settingName . ' ';
            $label .= $field['field'] ?? '';
            $fieldValue = $field['value'] ?? '';
            $this->addSettingIfNotEmpty($label, (string)$fieldValue, $settings);
        }
    }

    private function addSettingIfNotEmpty(string $settingName, string $value, array &$settings): void
    {
        if ($value !== '') {
            $settings[$settingName] = $value;
        }
    }

    /**
     * Gets a field's value from FlexForm configuration, will check if
     * FlexForm configuration is available.
     *
     * @param string $path name of the field
     * @return mixed|null if nothing found, value if found
     */
    private function getFieldFromFlexForm(string $path, FlexFormFieldValues $flexFormData): mixed
    {
        try {
            return $flexFormData->get($path);
        } catch (FlexFieldPropertyException|FlexFieldPropertyNotFoundException|NotFoundExceptionInterface|ContainerExceptionInterface) {
        }

        return null;
    }

    /**
     * Returns the plugin label
     */
    private function getPluginLabel(array $pluginsTtContentRecord): string
    {
        $label = BackendUtility::getLabelFromItemListMerged(
            $pluginsTtContentRecord['pid'],
            'tt_content',
            'CType',
            $pluginsTtContentRecord['CType'],
            $pluginsTtContentRecord,
        );

        if (!empty($label)) {
            $label = $this->getLanguageService()->sL($label);
        } else {
            $label = sprintf(
                $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.noMatchingValue'),
                $pluginsTtContentRecord['CType'],
            );
        }

        return $label;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
