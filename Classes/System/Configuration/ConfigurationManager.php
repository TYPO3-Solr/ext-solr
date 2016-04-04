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
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class ConfigurationManager implements SingletonInterface
{
    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfigurations = array();

    /**
     * Resets the state of the configuration manager.
     *
     * @return void
     */
    public function reset()
    {
        $this->typoScriptConfigurations = array();
    }

    /**
     * Retrieves the TypoScriptConfiguration object from an configuration array, pageId, languageId and TypoScript
     * path that is used in in the current context.
     *
     * @param array $configurationArray
     * @param null $contextPageId
     * @param int $contextLanguageId
     * @param string $contextTypoScriptPath
     * @return TypoScriptConfiguration
     */
    public function getTypoScriptConfiguration(array $configurationArray = null, $contextPageId = null, $contextLanguageId = 0, $contextTypoScriptPath = '')
    {
        if ($configurationArray == null) {
            if (!empty($GLOBALS['TSFE']->tmpl->setup) && is_array($GLOBALS['TSFE']->tmpl->setup)) {
                $configurationArray = $GLOBALS['TSFE']->tmpl->setup;
            }
        }

        if (! is_array($configurationArray)) {
            $configurationArray = array();
        }

        if (!isset($configurationArray['plugin.']['tx_solr.'])) {
            $configurationArray['plugin.']['tx_solr.'] = array();
        }

        if ($contextPageId == null && !empty($GLOBALS['TSFE']->id)) {
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
     * @param null $contextPageId
     * @return object
     */
    protected function getTypoScriptConfigurationInstance(array $configurationArray = null, $contextPageId = null)
    {
        return GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\TypoScriptConfiguration', $configurationArray, $contextPageId);
    }
}
