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

namespace ApacheSolrForTypo3\Solr\Configuration;

use InvalidArgumentException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TypoScript configuration object, used to read all TypoScript configuration.
 *
 * The TypoScriptConfiguration was introduced in order to be able to replace the old,
 * array based configuration with one configuration object.
 *
 * To read the configuration, you should use
 *
 * $configuration->getValueByPath
 *
 * or
 *
 * $configuration->isValidPath
 *
 * to check if an configuration path exists.
 *
 * To ensure Backwards compatibility the TypoScriptConfiguration object implements the
 * ArrayAccess interface (offsetGet,offsetExists,offsetUnset and offsetSet)
 *
 * This was only introduced to be backwards compatible in logTerm only "getValueByPath", "isValidPath" or
 * speaking methods for configuration settings should be used!
 *
 * @author Marc Bastian Heinrichs <mbh@mbh-software.de>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class TypoScriptConfiguration implements \ArrayAccess
{

    /**
     * Holds the solr configuration
     *
     * @var array
     */
    protected $configuration = array();

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
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

    /**
     * @param array $pathExploded
     * @return array
     */
    protected function getPathBranch(array $pathExploded)
    {
        if ($pathExploded[0] === 'plugin' && $pathExploded[1] === 'tx_solr') {
            $pathBranch = array('plugin.' => array('tx_solr.' => $this->configuration));
        } else {
            $pathBranch = $GLOBALS['TSFE']->tmpl->setup;
        }
        return $pathBranch;
    }

    /**
     * Merges a configuration with another configuration a
     *
     * @param array $configuration
     * @return array
     */
    public function merge(array $configuration)
    {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->configuration,
            $configuration
        );
    }

    /**
     * This method is used to allow the usage of the new configuration object with the array_key,
     * same to the previous configuration.
     *
     * isset($config['tx_solr']['configPath']);
     *
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::isValidPath() instead, will be removed in version 5.0
     * introduced to track the old array style usage
     * @param  string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        GeneralUtility::logDeprecatedFunction();
        return array_key_exists($offset, $this->configuration);
    }

    /**
     * This method is used to allow the usage of the new configuration object with the array_key,
     * same to the previous configuration.
     *
     * $config['tx_solr']['configPath'];
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::getValueByPath() instead, will be removed in version 5.0
     * introduced to track the old array style usage
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        GeneralUtility::logDeprecatedFunction();

        if (!$this->offsetExists($offset)) {
            return null;
        }

        return $this->configuration[$offset];
    }

    /**
     * Throws an exception because the configuration should not be changed from outside.

     * @deprecated since 4.0 will be removed in version 5.0 introduced to track the old array style usage
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        GeneralUtility::logDeprecatedFunction();

        throw new \Exception('The configuration is readonly');
    }

    /**
     * Throws an exception because the configuration options should not be unsetted from outside.
     *
     * @deprecated since 4.0 will be removed in version 5.0 introduced to track the old array style usage
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        GeneralUtility::logDeprecatedFunction();

        throw new \Exception('The configuration is readonly');
    }
}
