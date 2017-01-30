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

use TYPO3\CMS\Backend\Utility\BackendUtility;
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
     * @var TypoScriptConfiguration
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
     * @param null $contextPageId
     * @return object
     */
    protected function getTypoScriptConfigurationInstance(array $configurationArray = null, $contextPageId = null)
    {
        return GeneralUtility::makeInstance(TypoScriptConfiguration::class, $configurationArray, $contextPageId);
    }

    /**
     * Retrieves the name of the Index Queue Configuration for a record.
     *
     * @param string $recordTable Table to read from
     * @param int $recordUid Id of the record
     * @param TypoScriptConfiguration $solrConfiguration
     * @return string Name of indexing configuration
     */
    public function getIndexingConfigurationName(
        $recordTable,
        $recordUid,
        TypoScriptConfiguration $solrConfiguration
    ) {
        $name = $recordTable;
        $indexingConfigurations = $solrConfiguration->getEnabledIndexQueueConfigurationNames();
        foreach ($indexingConfigurations as $indexingConfigurationName) {
            if (!$solrConfiguration->getIndexQueueConfigurationIsEnabled($indexingConfigurationName)) {
                // ignore disabled indexing configurations
                continue;
            }

            $record = $this->getRecordIfIndexConfigurationIsValid($recordTable, $recordUid,
                $indexingConfigurationName, $solrConfiguration);
            if (!empty($record)) {
                $name = $indexingConfigurationName;
                // FIXME currently returns after the first configuration match
                break;
            }
        }

        return $name;
    }

    /**
     * Retrieves a record, taking into account the additionalWhereClauses of the
     * Indexing Queue configurations.
     *
     * @param string $recordTable Table to read from
     * @param int $recordUid Id of the record
     * @param TypoScriptConfiguration $solrConfiguration
     * @return array Record if found, otherwise empty array
     */
    public function getRecord($recordTable, $recordUid, TypoScriptConfiguration $solrConfiguration)
    {
        $record = [];
        $indexingConfigurations = $solrConfiguration->getEnabledIndexQueueConfigurationNames();

        foreach ($indexingConfigurations as $indexingConfigurationName) {
            $record = $this->getRecordIfIndexConfigurationIsValid($recordTable, $recordUid,
                $indexingConfigurationName, $solrConfiguration);
            if (!empty($record)) {
                // if we found a record which matches the conditions, we can continue
                break;
            }
        }

        return $record;
    }

    /**
     * This method return the record array if the table is valid for this indexingConfiguration.
     * Otherwise an empty array will be returned.
     *
     * @param string $recordTable
     * @param integer $recordUid
     * @param string $indexingConfigurationName
     * @param TypoScriptConfiguration $solrConfiguration
     * @return array
     */
    protected function getRecordIfIndexConfigurationIsValid(
        $recordTable,
        $recordUid,
        $indexingConfigurationName,
        TypoScriptConfiguration $solrConfiguration
    ) {
        if (!$this->isValidTableForIndexConfigurationName($recordTable, $indexingConfigurationName,
            $solrConfiguration)
        ) {
            return [];
        }

        $recordWhereClause = $solrConfiguration->getIndexQueueAdditionalWhereClauseByConfigurationName($indexingConfigurationName);

        if ($recordTable === 'pages_language_overlay') {
            return $this->getPageOverlayRecordIfParentIsAccessible($recordUid, $recordWhereClause);
        }

        return (array)BackendUtility::getRecord($recordTable, $recordUid, '*', $recordWhereClause);
    }

    /**
     * This method is used to check if a table is an allowed table for an index configuration.
     *
     * @param string $recordTable
     * @param string $indexingConfigurationName
     * @param TypoScriptConfiguration $solrConfiguration
     * @return boolean
     */
    protected function isValidTableForIndexConfigurationName(
        $recordTable,
        $indexingConfigurationName,
        TypoScriptConfiguration $solrConfiguration
    ) {
        $tableToIndex = $solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);

        $isMatchingTable = ($tableToIndex === $recordTable);
        $isPagesPassedAndOverlayRequested = $tableToIndex === 'pages' && $recordTable === 'pages_language_overlay';

        if ($isMatchingTable || $isPagesPassedAndOverlayRequested) {
            return true;
        }

        return false;
    }

    /**
     * This method retrieves the pages_language_overlay record when the parent record is accessible
     * through the recordWhereClause
     *
     * @param int $recordUid
     * @param string $parentWhereClause
     * @return array
     */
    protected function getPageOverlayRecordIfParentIsAccessible($recordUid, $parentWhereClause)
    {
        $overlayRecord = (array)BackendUtility::getRecord('pages_language_overlay', $recordUid, '*');
        $pageRecord = (array)BackendUtility::getRecord('pages', $overlayRecord['pid'], '*', $parentWhereClause);

        if (empty($pageRecord)) {
            return [];
        }

        return $overlayRecord;
    }
}
