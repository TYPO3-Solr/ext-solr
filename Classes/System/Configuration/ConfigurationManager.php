<?php
namespace ApacheSolrForTypo3\Solr\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Schmidt <timo.schmidt@dkd.de
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class ConfigurationManager implements SingletonInterface
{
    /**
     * TypoScript Configurations
     *
     * @var array
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

        $unifiedConfiguration = new UnifiedConfiguration($pageUid, $languageUid);
        $unifiedConfiguration->mergeConfigurationByObject($this->getGlobalConfiguration());
        // TODO: Site configuration
        $unifiedConfiguration->mergeConfigurationByObject($this->getExtensionConfiguration());
        // TODO TypoScript configuration

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

    public function getSiteConfiguration(): SiteConfiguration
    {

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

        $this->typoScriptConfigurations[$hash] = $this->getTypoScriptConfigurationInstance($configurationArray, $contextPageId);
        return $this->typoScriptConfigurations[$hash];
    }

    /**
     * This method is used to build the TypoScriptConfiguration.
     *
     * @param array $configurationArray
     * @param int|null $contextPageId
     * @return object
     */
    protected function getTypoScriptConfigurationInstance(array $configurationArray = null, $contextPageId = null)
    {
        return GeneralUtility::makeInstance(
            TypoScriptConfiguration::class,
            /** @scrutinizer ignore-type */ $configurationArray,
            /** @scrutinizer ignore-type */ $contextPageId
        );
    }
}
