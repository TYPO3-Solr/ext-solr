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
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class TypoScript
 */
class TypoScript implements SingletonInterface
{
    /**
     * Holds the TypoScript values for given page-id language and TypoScript path.
     *
     * @var array
     */
    private array $configurationObjectCache = [];

    /**
     * Loads the TypoScript configuration for a given page-id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId The page id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     * @param int $language System language uid, optional, defaults to 0
     * @param int|null $rootPageId
     *
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     *
     * @throws DBALDriverException
     */
    public function getConfigurationFromPageId(
        int $pageId,
        string $path,
        int $language = 0,
        ?int $rootPageId = null
    ): TypoScriptConfiguration {
        $cacheId = md5($pageId . '|' . $path . '|' . $language);
        if (isset($this->configurationObjectCache[$cacheId])) {
            return $this->configurationObjectCache[$cacheId];
        }

        // If we're on UID 0, we cannot retrieve a configuration.
        // TSFE can not be initialized for UID = 0
        // getRootline() below throws an exception (since #typo3-60 )
        // as UID 0 cannot have any parent rootline by design.
        if ($pageId === 0 && $rootPageId === null) {
            return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray([], $pageId, $language, $path);
        }

        /* @var TwoLevelCache $cache */
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'tx_solr_configuration');
        $configurationArray = $cache->get($cacheId);

        if (!empty($configurationArray)) {
            // we have a cache hit and can return it.
            return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
        }

        // we have nothing in the cache. We need to build the configurationToUse
        $configurationArray = $this->buildConfigurationArray($pageId, $path, $language);

        $cache->set($cacheId, $configurationArray);

        return $this->configurationObjectCache[$cacheId] = $this->buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
    }

    /**
     * Builds a configuration array, containing the solr configuration.
     *
     * @param int $pageId
     * @param string $path
     * @param int $language
     *
     * @return array
     *
     * @throws DBALDriverException
     */
    protected function buildConfigurationArray(int $pageId, string $path, int $language): array
    {
        /* @var Tsfe $tsfeManager */
        $tsfeManager = GeneralUtility::makeInstance(Tsfe::class);
        try {
            $tsfe = $tsfeManager->getTsfeByPageIdAndLanguageId($pageId, $language);
        } catch (InternalServerErrorException | ServiceUnavailableException | SiteNotFoundException | Exception\Exception $e) {
            // @todo logging!
            return [];
        }
        $getConfigurationFromInitializedTSFEAndWriteToCache = $this->ext_getSetup($tsfe->tmpl->setup ?? [], $path);
        return $getConfigurationFromInitializedTSFEAndWriteToCache[0] ?? [];
    }

    /**
     * @param array $theSetup
     * @param string $theKey
     * @return array
     */
    public function ext_getSetup(array $theSetup, string $theKey): array
    {
        $parts = explode('.', $theKey, 2);
        if ((string)$parts[0] !== '' && is_array($theSetup[$parts[0] . '.'])) {
            if (trim($parts[1]) !== '') {
                return $this->ext_getSetup($theSetup[$parts[0] . '.'], trim($parts[1]));
            }
            return [$theSetup[$parts[0] . '.'], $theSetup[$parts[0]]];
        }
        if (trim($theKey) !== '') {
            return [[], $theSetup[$theKey]];
        }
        return [$theSetup, ''];
    }

    /**
     * Builds the configuration object from a config array and returns it.
     *
     * @param array $configurationToUse
     * @param int $pageId
     * @param int $languageId
     * @param string $typoScriptPath
     * @return TypoScriptConfiguration
     */
    protected function buildTypoScriptConfigurationFromArray(array $configurationToUse, int $pageId, int $languageId, string $typoScriptPath): TypoScriptConfiguration
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration($configurationToUse, $pageId, $languageId, $typoScriptPath);
    }
}
