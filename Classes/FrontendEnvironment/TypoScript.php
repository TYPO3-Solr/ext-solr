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

namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Class TypoScript
 */
class TypoScript implements SingletonInterface
{
    /**
     * Holds the TypoScript values for given page-id language and TypoScript path.
     */
    private array $configurationObjectCache = [];

    /**
     * Loads the TypoScript configuration for a given page-id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId The page id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     *
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public function getConfigurationFromPageId(
        int $pageId,
        string $path,
        ?int $rootPageId = null
    ): TypoScriptConfiguration {
        $cacheId = md5($pageId . '|' . $path);
        if (isset($this->configurationObjectCache[$cacheId])) {
            return $this->configurationObjectCache[$cacheId];
        }

        // If we're on UID 0, we cannot retrieve a configuration.
        // TSFE can not be initialized for UID = 0
        // getRootline() below throws an exception (since #typo3-60 )
        // as UID 0 cannot have any parent rootline by design.
        if ($pageId === 0 && $rootPageId === null) {
            return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray([], $pageId, $path);
        }

        /** @var TwoLevelCache $cache */
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, 'tx_solr_configuration');
        $configurationArray = $cache->get($cacheId);

        if (!empty($configurationArray)) {
            // we have a cache hit and can return it.
            return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $path);
        }

        // we have nothing in the cache. We need to build the configurationToUse
        $configurationArray = $this->buildConfigurationArray($path);

        $cache->set($cacheId, $configurationArray);

        return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $path);
    }

    /**
     * Builds a configuration array, containing the solr configuration.
     */
    protected function buildConfigurationArray(string $path): array
    {
        $typoscriptSetup =  GeneralUtility::makeInstance(ConfigurationManagerInterface::class)
            ->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $getConfigurationFromInitializedTSFEAndWriteToCache = $this->ext_getSetup($typoscriptSetup ?? [], $path);
        return $getConfigurationFromInitializedTSFEAndWriteToCache[0] ?? [];
    }

    /**
     * Adapted from TYPO3 core
     * @see sysext:core/Classes/TypoScript/ExtendedTemplateService until TYPO3 v11
     */
    public function ext_getSetup(array $theSetup, string $theKey): array
    {
        // 'a.b.c' --> ['a', 'b.c']
        $parts = explode('.', $theKey, 2);
        if ($parts[0] !== '' && is_array($theSetup[$parts[0] . '.'])) {
            if (trim($parts[1] ?? '') !== '') {
                // Current path segment is a sub array, check it recursively by applying the rest of the key
                return $this->ext_getSetup($theSetup[$parts[0] . '.'], trim($parts[1] ?? ''));
            }
            // No further path to evaluate, return current setup and the value for the current path segment - if any
            return [$theSetup[$parts[0] . '.'], $theSetup[$parts[0]] ?? ''];
        }
        if (trim($theKey) !== '') {
            return [[], $theSetup[$theKey]];
        }
        return [$theSetup, ''];
    }

    /**
     * Builds the configuration object from a config array and returns it.
     */
    protected function buildTypoScriptConfigurationFromArray(array $configurationToUse, int $pageId, string $typoScriptPath): TypoScriptConfiguration
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration($configurationToUse, $pageId, $typoScriptPath);
    }
}
