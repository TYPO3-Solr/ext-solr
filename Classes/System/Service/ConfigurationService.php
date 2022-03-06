<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\System\Service;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * Service to ease work with configurations.
 *
 * @author Daniel Siepmann <coding@daniel-siepmann.de>
 */
class ConfigurationService
{
    /**
     * @var FlexFormService
     */
    protected FlexFormService $flexFormService;

    /**
     * @var TypoScriptService
     */
    protected TypoScriptService $typoScriptService;

    public function __construct()
    {
        $this->flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $this->typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
    }

    /**
     * @param FlexFormService $flexFormService
     */
    public function setFlexFormService(FlexFormService $flexFormService)
    {
        $this->flexFormService = $flexFormService;
    }

    /**
     * @param TypoScriptService $typoScriptService
     */
    public function setTypoScriptService(TypoScriptService $typoScriptService)
    {
        $this->typoScriptService = $typoScriptService;
    }

    /**
     * Override the given solrConfiguration with flex form configuration.
     *
     * @param string $flexFormData The raw data from database.
     * @param TypoScriptConfiguration $solrTypoScriptConfiguration
     */
    public function overrideConfigurationWithFlexFormSettings(string $flexFormData, TypoScriptConfiguration $solrTypoScriptConfiguration)
    {
        if (empty($flexFormData)) {
            return;
        }

        $flexFormConfiguration = $this->flexFormService->convertFlexFormContentToArray($flexFormData);
        $flexFormConfiguration = $this->overrideFilter($flexFormConfiguration);
        $flexFormConfiguration = $this->typoScriptService->convertPlainArrayToTypoScriptArray($flexFormConfiguration);

        $solrTypoScriptConfiguration->mergeSolrConfiguration($flexFormConfiguration, true, false);
    }

    /**
     * Override filter in configuration.
     *
     * Will parse the filter from flex form structure and rewrite it as typoscript structure.
     *
     * @param array $flexFormConfiguration
     *
     * @return array
     */
    protected function overrideFilter(array $flexFormConfiguration): array
    {
        $filter = $this->getFilterFromFlexForm($flexFormConfiguration);
        unset($flexFormConfiguration['search']['query']['filter']);
        if (empty($filter)) {
            return $flexFormConfiguration;
        }

        return array_merge_recursive(
            $flexFormConfiguration,
            [
                'search' => [
                    'query' => [
                        'filter' => $filter,
                    ],
                ],
            ]
        );
    }

    /**
     * Returns filter in typoscript form from flex form.
     *
     * @param array $flexFormConfiguration
     *
     * @return array
     */
    protected function getFilterFromFlexForm(array $flexFormConfiguration): array
    {
        $filterConfiguration = [];
        $filters = ObjectAccess::getPropertyPath($flexFormConfiguration, 'search.query.filter');

        if (empty($filters)) {
            return $filterConfiguration;
        }

        foreach ($filters as $filter) {
            $filter = $filter['field'];

            $fieldName = $filter['field'];
            $fieldValue = $filter['value'];

            if (!is_numeric($fieldValue) && strpos($fieldValue, '?') === false && strpos($fieldValue, '*') === false) {
                $fieldValue = '"' . str_replace('"', '\"', $fieldValue) . '"';
            }

            $filterConfiguration[] =  $fieldName . ':' . $fieldValue;
        }
        return $filterConfiguration;
    }
}
