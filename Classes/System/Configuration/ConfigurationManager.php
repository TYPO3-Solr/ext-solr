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
    protected array $typoScriptConfigurations = [];

    /**
     * Resets the state of the configuration manager.
     */
    public function reset()
    {
        $this->typoScriptConfigurations = [];
    }

    /**
     * Retrieves the TypoScriptConfiguration object from configuration array, pageId, languageId and TypoScript
     * path that is used in the current context.
     *
     * @param array|null $configurationArray
     * @param int|null $contextPageId
     * @param int $contextLanguageId
     * @param string $contextTypoScriptPath
     * @return TypoScriptConfiguration
     */
    public function getTypoScriptConfiguration(array $configurationArray = null, int $contextPageId = null, int $contextLanguageId = 0, string $contextTypoScriptPath = ''): TypoScriptConfiguration
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
     * @param array|null $configurationArray
     * @param int|null $contextPageId
     * @return TypoScriptConfiguration
     */
    protected function getTypoScriptConfigurationInstance(array $configurationArray = null, int $contextPageId = null): TypoScriptConfiguration
    {
        return GeneralUtility::makeInstance(
            TypoScriptConfiguration::class,
            /** @scrutinizer ignore-type */
            $configurationArray,
            /** @scrutinizer ignore-type */
            $contextPageId
        );
    }
}
