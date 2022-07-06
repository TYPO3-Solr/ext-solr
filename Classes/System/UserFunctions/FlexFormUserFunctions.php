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

namespace ApacheSolrForTypo3\Solr\System\UserFunctions;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use function str_starts_with;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class contains all user functions for flexforms.
 *
 * @author Daniel Siepmann <coding@daniel-siepmann.de>
 */
class FlexFormUserFunctions
{
    /**
     * @var FrontendEnvironment
     */
    protected $frontendEnvironment;

    public function __construct(FrontendEnvironment $frontendEnvironment = null)
    {
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Provides all facet fields for a flexform select, enabling the editor to select one of them.
     *
     * @param array $parentInformation
     * @throws DBALDriverException
     * @throws NoSolrConnectionFoundException
     */
    public function getFacetFieldsFromSchema(array &$parentInformation)
    {
        $pageRecord = $parentInformation['flexParentDatabaseRow'];
        // @todo: Fix type hinting issue properly on whole call chain.
        $configuredFacets = $this->getConfiguredFacetsForPage($pageRecord['pid'] ?? null);

        if (!is_array($pageRecord)) {
            $parentInformation['items'] = [];
            return;
        }

        $newItems = $this->getParsedSolrFieldsFromSchema($configuredFacets, $pageRecord);
        $parentInformation['items'] = $newItems;
    }

    /**
     * This method parses the solr schema fields into the required format for the backend flexform.
     *
     * @param array $configuredFacets
     * @param array $pageRecord
     * @return array
     * @throws DBALDriverException
     * @throws NoSolrConnectionFoundException
     */
    protected function getParsedSolrFieldsFromSchema(array $configuredFacets, array $pageRecord): array
    {
        $newItems = [];

        array_map(function ($fieldName) use (&$newItems, $configuredFacets) {
            $value = $fieldName;
            $label = $fieldName;

            $facetNameFilter = function ($facet) use ($fieldName) {
                return $facet['field'] === $fieldName;
            };
            $configuredFacets = array_filter($configuredFacets, $facetNameFilter);
            if (!empty($configuredFacets)) {
                $configuredFacet = array_values($configuredFacets);
                $label = $configuredFacet[0]['label'];
                // try to translate LLL: label or leave it unchanged
                if (str_starts_with($label, 'LLL:') && $this->getTranslation($label) != '') {
                    $label = $this->getTranslation($label);
                } elseif (!str_starts_with($label, 'LLL:') && ($configuredFacet[0]['label.'] ?? null)) {
                    $label = sprintf('cObject[...faceting.facets.%slabel]', array_keys($configuredFacets)[0]);
                }
                $label = sprintf('%s (Facet Label: "%s")', $value, $label);
            }

            $newItems[$value] = [$label, $value];
        }, $this->getFieldNamesFromSolrMetaDataForPage($pageRecord));

        ksort($newItems, SORT_NATURAL);
        return $newItems;
    }

    /**
     * Retrieves the configured facets for a page.
     *
     * @param int|null $pid
     * @return array
     * @throws DBALDriverException
     * @todo: Fix type hinting properly
     */
    protected function getConfiguredFacetsForPage(?int $pid = null): ?array
    {
        if ($pid === null) {
            return null;
        }
        $typoScriptConfiguration = $this->getConfigurationFromPageId($pid);
        return $typoScriptConfiguration->getSearchFacetingFacets();
    }

    /**
     * Retrieves the translation with the LocalizationUtility.
     *
     * @param string $label
     * @return string|null
     */
    protected function getTranslation(string $label): ?string
    {
        return LocalizationUtility::translate($label);
    }

    /**
     * Get solr connection.
     *
     * @param array $pageRecord
     *
     * @return SolrConnection
     * @throws DBALDriverException
     * @throws NoSolrConnectionFoundException
     */
    protected function getConnection(array $pageRecord): SolrConnection
    {
        return GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($pageRecord['pid'], $pageRecord['sys_language_uid']);
    }

    /**
     * Retrieves all fieldnames that occurs in the solr schema for one page.
     *
     * @param array $pageRecord
     * @return array
     * @throws DBALDriverException
     * @throws NoSolrConnectionFoundException
     */
    protected function getFieldNamesFromSolrMetaDataForPage(array $pageRecord): array
    {
        return array_keys((array)$this->getConnection($pageRecord)->getAdminService()->getFieldsMetaData());
    }

    /**
     * @param array $parentInformation
     * @throws DBALDriverException
     */
    public function getAvailableTemplates(array &$parentInformation)
    {
        $pageRecord = $parentInformation['flexParentDatabaseRow'];
        if (!is_array($pageRecord) || !isset($pageRecord['pid'])) {
            $parentInformation['items'] = [];
            return;
        }

        $pageId = $pageRecord['pid'];

        $templateKey = $this->getTypoScriptTemplateKeyFromFieldName($parentInformation);
        $availableTemplate = $this->getAvailableTemplateFromTypoScriptConfiguration($pageId, $templateKey);
        $newItems = $this->buildSelectItemsFromAvailableTemplate($availableTemplate);

        $parentInformation['items'] = $newItems;
    }

    /**
     * @param array $parentInformation
     */
    public function getAvailablePluginNamespaces(array &$parentInformation)
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $namespaces = [];
        foreach ($extensionConfiguration->getAvailablePluginNamespaces() as $namespace) {
            $label = $namespace === 'tx_solr' ? 'Default' : '';
            $namespaces[$namespace] = [$label, $namespace];
        }
        $parentInformation['items'] = $namespaces;
    }

    /**
     * @param array $parentInformation
     * @return string|string[]
     */
    protected function getTypoScriptTemplateKeyFromFieldName(array $parentInformation)
    {
        $field = $parentInformation['field'];
        return str_replace('view.templateFiles.', '', $field);
    }

    /**
     * @param int|null $pid
     * @return TypoScriptConfiguration|null
     * @throws DBALDriverException
     * @todo: Fix type hinting properly
     */
    protected function getConfigurationFromPageId(?int $pid = null): ?TypoScriptConfiguration
    {
        if ($pid === null) {
            return null;
        }
        return $this->frontendEnvironment->getSolrConfigurationFromPageId($pid);
    }

    /**
     * Retrieves the configured templates from TypoScript.
     *
     * @param int $pageId
     * @param string $templateKey
     * @return array
     * @throws DBALDriverException
     */
    protected function getAvailableTemplateFromTypoScriptConfiguration(int $pageId, string $templateKey): array
    {
        $configuration = $this->getConfigurationFromPageId($pageId);
        return $configuration->getAvailableTemplatesByFileKey($templateKey);
    }

    /**
     * Returns the available templates as needed for the flexform.
     *
     * @param array $availableTemplates
     * @return array
     */
    protected function buildSelectItemsFromAvailableTemplate(array $availableTemplates): array
    {
        $newItems = [];
        $newItems['Use Default'] = ['Use Default', null];
        foreach ($availableTemplates as $availableTemplate) {
            $label = $availableTemplate['label'] ?? '';
            $value = $availableTemplate['file'] ?? '';
            $newItems[$label] = [$label, $value];
        }

        return $newItems;
    }
}
