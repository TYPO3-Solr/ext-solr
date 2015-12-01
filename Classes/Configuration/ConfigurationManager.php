<?php

namespace ApacheSolrForTypo3\Solr\Configuration;

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

use ApacheSolrForTypo3\Solr\Configuration\TypoScriptConfiguration;
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
    protected $typoScriptConfiguration = null;

    /**
     * @param array $configurationArray
     */
    public function __construct(array $configurationArray = null)
    {
        $this->initialize($configurationArray);
    }

    /**
     * Resets the state of the configuration manager.
     *
     * @return void
     */
    public function reset()
    {
        $this->initialize();
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Configuration\TypoScriptConfiguration $typoScriptConfiguration
     */
    public function setTypoScriptConfiguration($typoScriptConfiguration)
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration;
    }

    /**
     * @return \ApacheSolrForTypo3\Solr\Configuration\TypoScriptConfiguration
     */
    public function getTypoScriptConfiguration()
    {
        return $this->typoScriptConfiguration;
    }

    /**
     * @param array $configurationArray
     */
    private function initialize(array $configurationArray = null)
    {
        if ($configurationArray == null) {
            if (!empty($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']) && is_array($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'])) {
                $configurationArray = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];
            } else {
                $configurationArray = array();
            }
        }

        $this->typoScriptConfiguration = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Configuration\\TypoScriptConfiguration', $configurationArray);
    }
}
