<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Marc Bastian Heinrichs <mbh@mbh-software.de>
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

namespace ApacheSolrForTypo3\Solr;

use InvalidArgumentException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Remote API related methods
 *
 * @package TYPO3
 * @subpackage solr
 */
class TypoScriptConfiguration implements SingletonInterface
{

    /**
     * Holds the solr configuration
     *
     * @var array
     */
    protected $configuration = array();

    /**
     * @param $configuration
     * @return array
     */
    public function initialize($configuration) {
        $this->reset();
        return $this->mergeConfigurationRecursiveWithOverrule($configuration);
    }

    /**
     * Resets the internal configuration to typoscript path plugin.tx_solr., if set.
     */
    public function reset() {
        if (!empty($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']) && is_array($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'])) {
            $this->configuration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];
        }
    }

    /**
     * Returns the solr configuration
     *
     * In the context of an frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * @return array
     */
    public function get() {
        return $this->configuration;
    }

    /**
     * Gets the value from a given TypoScript path.
     *
     * In the context of an frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * Example: plugin.tx_solr.search.targetPage
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage']
     *
     * @param string $path TypoScript path
     * @return array The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     */
    public function getValueByPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Parameter $path is not a string',
                1325623321);
        }

        $pathExploded = explode('.', trim($path));
        $pathBranch = $this->getPathBranch($pathExploded);

        $segmentCount = count($pathExploded);
        for ($i = 0; $i < $segmentCount; $i++) {
            $segment = $pathExploded[$i];

            if ($i == ($segmentCount - 1)) {
                $pathBranch = $pathBranch[$segment];
            } else {
                $pathBranch = $pathBranch[$segment . '.'];
            }
        }

        return $pathBranch;
    }

    /**
     * Gets the parent TypoScript Object from a given TypoScript path.
     *
     * In the context of an frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * Example: plugin.tx_solr.index.queue.tt_news.fields.content
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']
     * which is a SOLR_CONTENT cObj.
     *
     * @param string $path TypoScript path
     * @return array The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     */
    public function getObjectByPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('Parameter $path is not a string',
                1325627243);
        }

        $pathExploded = explode('.', trim($path));
        // remove last object
        $lastPathSegment = array_pop($pathExploded);
        $pathBranch = $this->getPathBranch($pathExploded);

        foreach ($pathExploded as $segment) {
            if (!array_key_exists($segment . '.', $pathBranch)) {
                throw new InvalidArgumentException(
                    'TypoScript object path "' . htmlspecialchars($path) . '" does not exist',
                    1325627264
                );
            }
            $pathBranch = $pathBranch[$segment . '.'];
        }

        return $pathBranch;
    }

    /**
     * Checks whether a given TypoScript path is valid.
     *
     * @param string $path TypoScript path
     * @return boolean TRUE if the path resolves, FALSE otherwise
     */
    public function isValidPath($path)
    {
        $isValidPath = false;

        $pathValue = $this->getValueByPath($path);
        if (!is_null($pathValue)) {
            $isValidPath = true;
        }

        return $isValidPath;
    }

    protected function getPathBranch($pathExploded) {
        if ($pathExploded[0] === 'plugin' && $pathExploded[1] === 'tx_solr') {
            $pathBranch = array('plugin.' => array('tx_solr.' => $this->configuration));
        } else {
            $pathBranch = $GLOBALS['TSFE']->tmpl->setup;
        }
        return $pathBranch;
    }

    /**
     * Returns the freshly merged solr configuration
     *
     * @param array $configuration
     * @return array
     */
    public function mergeConfigurationRecursiveWithOverrule(array $configuration) {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->configuration,
            $configuration
        );
        return $this->configuration;
    }
}