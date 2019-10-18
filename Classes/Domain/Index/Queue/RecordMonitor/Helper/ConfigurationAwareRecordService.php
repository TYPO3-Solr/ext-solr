<?php

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 - Thomas Hohn <tho@systime.dk>
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

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extracted logic from the AbstractDataHandlerListener in order to
 * handle ConfigurationAwareRecords
 *
 * @author Thomas Hohn Hund <tho@systime.dk>
 */
class ConfigurationAwareRecordService
{
    /**
     * Retrieves the name of the Index Queue Configuration for a record.
     *
     * @param string $recordTable Table to read from
     * @param int $recordUid Id of the record
     * @param TypoScriptConfiguration $solrConfiguration
     * @return string Name of indexing configuration
     */
    public function getIndexingConfigurationName($recordTable, $recordUid, TypoScriptConfiguration $solrConfiguration)
    {
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
    protected function getRecordIfIndexConfigurationIsValid($recordTable, $recordUid, $indexingConfigurationName, TypoScriptConfiguration $solrConfiguration)
    {
        if (!$this->isValidTableForIndexConfigurationName($recordTable, $indexingConfigurationName, $solrConfiguration)) {
            return [];
        }

        $recordWhereClause = $solrConfiguration->getIndexQueueAdditionalWhereClauseByConfigurationName($indexingConfigurationName);

        $row = $this->getRecordForIndexConfigurationIsValid($recordTable, $recordUid, $recordWhereClause);

        return $row;
    }

    /**
     * Returns the row need by getRecordIfIndexConfigurationIsValid either directly from database
     * or from cache
     *
     * @param string $recordTable
     * @param integer $recordUid
     * @param string $recordWhereClause
     *
     * @return array
     */
    protected function getRecordForIndexConfigurationIsValid($recordTable, $recordUid, $recordWhereClause)
    {
        $cache = GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'cache_runtime');
        $cacheId = md5('ConfigurationAwareRecordService' . ':' . 'getRecordIfIndexConfigurationIsValid' . ':' . $recordTable . ':' . $recordUid . ':' . $recordWhereClause);

        $row = $cache->get($cacheId);
        if (!empty($row)) {
            return $row;
        }

        $row = (array)BackendUtility::getRecord($recordTable, $recordUid, '*', $recordWhereClause);
        $cache->set($cacheId, $row);

        return $row;
    }

    /**
     * This method is used to check if a table is an allowed table for an index configuration.
     *
     * @param string $recordTable
     * @param string $indexingConfigurationName
     * @param TypoScriptConfiguration $solrConfiguration
     * @return boolean
     */
    protected function isValidTableForIndexConfigurationName($recordTable, $indexingConfigurationName, TypoScriptConfiguration $solrConfiguration)
    {
        $tableToIndex = $solrConfiguration->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);

        $isMatchingTable = ($tableToIndex === $recordTable);

        if ($isMatchingTable) {
            return true;
        }

        return false;
    }

    /**
     * This method retrieves the parent pages record when the parent record is accessible
     * through the recordWhereClause
     *
     * @param int $recordUid
     * @param string $parentWhereClause
     * @return array
     */
    protected function getPageOverlayRecordIfParentIsAccessible($recordUid, $parentWhereClause)
    {
        $overlayRecord = (array)BackendUtility::getRecord('pages', $recordUid, '*');
        $overlayParentId = $overlayRecord['l10n_parent'];

        $pageRecord = (array)BackendUtility::getRecord('pages', $overlayParentId, '*', $parentWhereClause);

        if (empty($pageRecord)) {
            return [];
        }

        return $overlayRecord;
    }
}
