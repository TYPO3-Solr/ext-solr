<?php
namespace ApacheSolrForTypo3\Solr\System\Service;

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

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager as ExtbaseConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Extbase\Service\FlexFormService;
use TYPO3\CMS\Extbase\Service\TypoScriptService;

/**
 * Service to ease work with configurations.
 *
 * @author Daniel Siepmann <coding@daniel-siepmann.de>
 */
class ConfigurationService
{
    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @inject
     */
    protected $flexFormService;

    /**
     * @var \TYPO3\CMS\Extbase\Service\TypoScriptService
     * @inject
     */
    protected $typoScriptService;

    /**
     * @param \TYPO3\CMS\Extbase\Service\FlexFormService $flexFormService
     */
    public function setFlexFormService($flexFormService)
    {
        $this->flexFormService = $flexFormService;
    }

    /**
     * @param \TYPO3\CMS\Extbase\Service\TypoScriptService $typoScriptService
     */
    public function setTypoScriptService($typoScriptService)
    {
        $this->typoScriptService = $typoScriptService;
    }

    /**
     * Override the given solrConfiguration with flex form configuration.
     *
     * @param string $flexFormData The raw data from database.
     * @param TypoScriptConfiguration $solrTypoScriptConfiguration
     *
     * @return void
     */
    public function overrideConfigurationWithFlexFormSettings($flexFormData, TypoScriptConfiguration $solrTypoScriptConfiguration)
    {
        if (empty($flexFormData)) {
            return;
        }

        $flexFormConfiguration = $this->flexFormService->convertflexFormContentToArray($flexFormData);
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
    protected function overrideFilter(array $flexFormConfiguration)
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
    protected function getFilterFromFlexForm(array $flexFormConfiguration)
    {
        $filterConfiguration = [];
        $filters = ObjectAccess::getPropertyPath($flexFormConfiguration, 'search.query.filter');

        if (empty($filters)) {
            return $filterConfiguration;
        }

        foreach ($filters as $filter) {
            $filter = $filter['field'];
            $filterConfiguration[] = $filter['field'] . ':' . $filter['value'];
        }
        return $filterConfiguration;
    }
}
