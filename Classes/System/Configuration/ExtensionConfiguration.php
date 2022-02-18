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

namespace ApacheSolrForTypo3\Solr\System\Configuration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as CoreExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class encapsulates the access to the extension configuration.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ExtensionConfiguration
{
    /**
     * Extension Configuration
     *
     * @var array
     */
    protected $configuration = [];

    /**
     * ExtensionConfiguration constructor.
     *
     * @param array $configurationToUse
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct(array $configurationToUse = [])
    {
        if (empty($configurationToUse)) {
            $this->configuration = GeneralUtility::makeInstance(CoreExtensionConfiguration::class)->get('solr');
        } else {
            $this->configuration = $configurationToUse;
        }
    }

    /**
     * Get configuration for useConfigurationFromClosestTemplate
     *
     * @return bool
     */
    public function getIsUseConfigurationFromClosestTemplateEnabled(): bool
    {
        return (bool)$this->getConfigurationOrDefaultValue('useConfigurationFromClosestTemplate', false);
    }

    /**
     * Get configuration for useConfigurationTrackRecordsOutsideSiteroot
     *
     * @return bool
     */
    public function getIsUseConfigurationTrackRecordsOutsideSiteroot(): bool
    {
        return (bool)$this->getConfigurationOrDefaultValue('useConfigurationTrackRecordsOutsideSiteroot', true);
    }

    /**
     * Get configuration for allowSelfSignedCertificates
     *
     * @return bool
     */
    public function getIsSelfSignedCertificatesEnabled(): bool
    {
        return (bool)$this->getConfigurationOrDefaultValue('allowSelfSignedCertificates', false);
    }

    /**
     * Get configuration for useConfigurationMonitorTables
     *
     * @return array of table names
     */
    public function getIsUseConfigurationMonitorTables(): array
    {
        $monitorTables = [];
        $monitorTablesList = $this->getConfigurationOrDefaultValue('useConfigurationMonitorTables', '');

        if (empty($monitorTablesList)) {
            return $monitorTables;
        }

        return GeneralUtility::trimExplode(',', $monitorTablesList);
    }

    /**
     * Returns a list of available/whitelisted EXT:solr plugin namespaces.
     * Builds from "pluginNamespaces" extension configuration setting.
     *
     * @return array
     */
    public function getAvailablePluginNamespaces(): array
    {
        $pluginNamespacesList = 'tx_solr,' . $this->getConfigurationOrDefaultValue(
                'pluginNamespaces'
            );
        return array_unique(GeneralUtility::trimExplode(',', $pluginNamespacesList));
    }

    /**
     * Returns a list of cacheHash-excludedParameters matching the EXT:solr plugin namespaces.
     *
     * Builds from "pluginNamespaces" and takes "includeGlobalQParameterInCacheHash"
     * extension configuration settings into account.
     *
     * @return array
     */
    public function getCacheHashExcludedParameters(): array
    {
        $pluginNamespaces = array_map(
            function($pluginNamespace) {
                return '^' . $pluginNamespace . '[';
            },
            $this->getAvailablePluginNamespaces()
        );
        if (false === $this->getIncludeGlobalQParameterInCacheHash()) {
            $pluginNamespaces[] = 'q';
        }
        return array_combine($pluginNamespaces, $pluginNamespaces);
    }

    /**
     * Returns the "includeGlobalQParameterInCacheHash" extension configuration setting.
     *
     * @return bool
     */
    public function getIncludeGlobalQParameterInCacheHash(): bool
    {
        return (bool)$this->getConfigurationOrDefaultValue('includeGlobalQParameterInCacheHash', false);
    }

    /**
     * Returns the desired monitoring type
     * 0 - immediate
     * 1 - delayed
     * 2 - no monitoring
     *
     * @return int
     */
    public function getMonitoringType(): int
    {
        return (int)$this->getConfigurationOrDefaultValue('monitoringType', 0);
    }

    /**
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed|null
     */
    protected function getConfigurationOrDefaultValue(string $key, $defaultValue = null)
    {
        return $this->configuration[$key] ?? $defaultValue;
    }
}
