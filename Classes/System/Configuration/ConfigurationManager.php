<?php
namespace ApacheSolrForTypo3\Solr\System\Configuration;

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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @copyright (c) 2010-2016 Timo Schmidt <timo.schmidt@dkd.de
 */
class ConfigurationManager implements SingletonInterface
{
    /**
     * TypoScript Configurations
     *
     * @var TypoScriptConfiguration|UnifiedConfiguration[]
     */
    protected $typoScriptConfigurations = [];

    /**
     * Resets the state of the configuration manager.
     *
     * @return void
     */
    public function reset()
    {
        $this->typoScriptConfigurations = [];
    }

    /**
     * Build a unified configuration in order to have all configuration settings in one place
     *
     * @param int $pageUid
     * @param int $languageUid
     * @return UnifiedConfiguration
     */
    public function getUnifiedConfiguration(int $pageUid = 0, int $languageUid = 0): UnifiedConfiguration
    {
        $hash = md5(UnifiedConfiguration::class . '-' . $pageUid . '-' . $languageUid);
        if (isset($this->typoScriptConfigurations[$hash])) {
            return $this->typoScriptConfigurations[$hash];
        }

        if ($pageUid === 0 && !empty($GLOBALS['TSFE']->id)) {
            $pageUid = (int)$GLOBALS['TSFE']->id;
        }

        $unifiedConfiguration = new UnifiedConfiguration($pageUid, $languageUid);
        // TODO: Move this into a middle ware
        $unifiedConfiguration->mergeConfigurationByObject($this->getGlobalConfiguration());
        $unifiedConfiguration->mergeConfigurationByObject($this->getSiteConfiguration($pageUid, $languageUid));
        $unifiedConfiguration->mergeConfigurationByObject($this->getExtensionConfiguration());
        $unifiedConfiguration->mergeConfigurationByObject(
            $this->getTypoScriptConfigurationByPageAndLanguage($pageUid, $languageUid)
        );

        $this->typoScriptConfigurations[$hash] = $unifiedConfiguration;

        return $unifiedConfiguration;
    }

    /**
     * Returns instance of the global configuration
     *
     * @return GlobalConfiguration
     */
    public function getGlobalConfiguration(): GlobalConfiguration
    {
        return new GlobalConfiguration();
    }

    /**
     * Returns instance of the global configuration
     *
     * @return ExtensionConfiguration
     */
    public function getExtensionConfiguration(): ExtensionConfiguration
    {
        return new ExtensionConfiguration();
    }

    /**
     * Returns the site configuration by given page uid and language
     *
     * @param int $pageUid
     * @param int $languageUid
     * @return SiteConfiguration
     */
    public function getSiteConfiguration(int $pageUid = 0, int $languageUid = 0): SiteConfiguration
    {
        return new SiteConfiguration($pageUid, $languageUid);
    }

    /**
     * Returns the TypoScript configuration by given page and language uid.
     *
     * @see ConfigurationManager:getTypoScriptConfiguration
     *
     * @param int $pageUid
     * @param int $languageUid
     * @return TypoScriptConfiguration
     */
    public function getTypoScriptConfigurationByPageAndLanguage(
        int $pageUid,
        int $languageUid = 0
    ): TypoScriptConfiguration {
        return $this->getTypoScriptConfiguration(
            null,
            $pageUid,
            $languageUid
        );
    }

    /**
     * Retrieves the TypoScriptConfiguration object from an configuration array, pageId, languageId and TypoScript
     * path that is used in in the current context.
     *
     * @param array $configurationArray
     * @param int $contextPageId
     * @param int $contextLanguageId
     * @param string $contextTypoScriptPath
     * @return TypoScriptConfiguration
     */
    public function getTypoScriptConfiguration(array $configurationArray = null, $contextPageId = null, $contextLanguageId = 0, $contextTypoScriptPath = '')
    {
        if ($configurationArray == null) {
            if (isset($this->typoScriptConfigurations['default'])) {
                $configurationArray = $this->typoScriptConfigurations['default'];
            } else {
                if (!empty($GLOBALS['TSFE']->tmpl->setup) && is_array($GLOBALS['TSFE']->tmpl->setup)) {
                    $configurationArray = $GLOBALS['TSFE']->tmpl->setup;
                    $this->typoScriptConfigurations['default'] = $configurationArray;
                }
            }
        }

        if (!is_array($configurationArray)) {
            $configurationArray = [];
        }

        if (!isset($configurationArray['plugin.']['tx_solr.'])) {
            $configurationArray['plugin.']['tx_solr.'] = [];
        }

        if ($contextPageId === null && !empty($GLOBALS['TSFE']->id)) {
            $contextPageId = $GLOBALS['TSFE']->id;
        }

        $hash = md5(serialize($configurationArray)) . '-' . $contextPageId . '-' . $contextLanguageId . '-' . $contextTypoScriptPath;
        if (isset($this->typoScriptConfigurations[$hash])) {
            return $this->typoScriptConfigurations[$hash];
        }

        $this->typoScriptConfigurations[$hash] = $this->getTypoScriptConfigurationInstance(
            $configurationArray,
            $contextPageId
        );
        return $this->typoScriptConfigurations[$hash];
    }

    /**
     * This method is used to build the TypoScriptConfiguration.
     *
     * @param ?array $configurationArray
     * @param int|null $contextPageId
     * @return TypoScriptConfiguration
     */
    protected function getTypoScriptConfigurationInstance(array $configurationArray = null, $contextPageId = 0)
    {
        return GeneralUtility::makeInstance(
            TypoScriptConfiguration::class,
            /** @scrutinizer ignore-type */ $configurationArray,
            /** @scrutinizer ignore-type */ (int)$contextPageId
        );
    }
}
