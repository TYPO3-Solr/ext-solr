<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Changes in TYPO3 have an impact on the solr content and are caught
 * by the GarbageCollector and RecordMonitor. Both act as a TCE Main Hook.
 *
 * This base class is used to share functionality that are needed for both
 * to perform the changes in the data handler on the solr index.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
abstract class AbstractDataHandlerListener
{

    /**
     * @return array
     */
    protected function getAllRelevantFieldsForCurrentState()
    {
        $allCurrentStateFieldnames = [];

        foreach ($this->getUpdateSubPagesRecursiveTriggerConfiguration() as $triggerConfiguration) {
            if (!isset($triggerConfiguration['currentState']) || !is_array($triggerConfiguration['currentState'])) {
                // when no "currentState" configuration for the trigger exists we can skip it
                continue;
            }

            // we collect the currentState fields to return a unique list of all fields
            $allCurrentStateFieldnames = array_merge($allCurrentStateFieldnames, array_keys($triggerConfiguration['currentState']));
        }

        return array_unique($allCurrentStateFieldnames);
    }

    /**
     * When the extend to subpages flag was set, we determine the affected subpages and return them.
     *
     * @param int $pageId
     * @return array
     */
    protected function getSubPageIds($pageId)
    {
        /** @var $queryGenerator \TYPO3\CMS\Core\Database\QueryGenerator */
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);

        // here we retrieve only the subpages of this page because the permission clause is not evaluated
        // on the root node.
        $permissionClause = ' 1 ' . BackendUtility::BEenableFields('pages');
        $treePageIdList = $queryGenerator->getTreeList($pageId, 20, 0, $permissionClause);
        $treePageIds = array_map('intval', explode(',', $treePageIdList));

            // the first one can be ignored because this is the page itself
        array_shift($treePageIds);

        return $treePageIds;
    }

    /**
     * Check if an page update will trigger an recursive update of pages
     * This can be the case if $changedFields are part of the TriggerConfiguration or
     * columns have explicit been configured via plugin.tx_solr.index.queue.recursivePageUpdateFields
     *
     * @param int $pageId
     * @param array $changedFields
     * @return bool
     */
    protected function isRecursivePageUpdateRequired($pageId, $changedFields)
    {
        // First check TriggerConfiguration
        $isRecursiveUpdateRequiredTriggerConfiguration = $this->isRecursiveUpdateRequired($pageId, $changedFields);

        // If TriggerConfiguration is false => also check if changeFields are part of recursivePageUpdateFields
        if ($isRecursiveUpdateRequiredTriggerConfiguration === false) {
            $solrConfiguration = Util::getSolrConfigurationFromPageId($pageId);
            $recursiveUpdateFields = $solrConfiguration->getRecursivePageUpdateFields();

            // Check if no additional fields have been defined
            if (count($recursiveUpdateFields) === 0) {
                return false;
            }

            // The recursiveUpdateFields configuration is not part of the $changedFields
            if (!array_intersect_key($changedFields, $recursiveUpdateFields)) {
                return false;
            }
        }

        return true;
    }


    /**
     * @param int $pageId
     * @param array $changedFields
     * @return bool
     */
    protected function isRecursiveUpdateRequired($pageId, $changedFields)
    {
        $fieldsForCurrentState = $this->getAllRelevantFieldsForCurrentState();
        $fieldListToRetrieve = implode(',', $fieldsForCurrentState);
        $page = BackendUtility::getRecord('pages', $pageId, $fieldListToRetrieve, '', false);
        foreach ($this->getUpdateSubPagesRecursiveTriggerConfiguration() as $configurationName => $triggerConfiguration) {
            $allCurrentStateFieldsMatch = $this->getAllCurrentStateFieldsMatch($triggerConfiguration, $page);
            $allChangeSetValuesMatch = $this->getAllChangeSetValuesMatch($triggerConfiguration, $changedFields);

            $aMatchingTriggerHasBeenFound = $allCurrentStateFieldsMatch && $allChangeSetValuesMatch;
            if ($aMatchingTriggerHasBeenFound) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $triggerConfiguration
     * @param array $pageRecord
     * @return bool
     */
    protected function getAllCurrentStateFieldsMatch($triggerConfiguration, $pageRecord)
    {
        $triggerConfigurationHasNoCurrentStateConfiguration = !array_key_exists('currentState', $triggerConfiguration);
        if ($triggerConfigurationHasNoCurrentStateConfiguration) {
            return true;
        }
        $diff = array_diff_assoc($triggerConfiguration['currentState'], $pageRecord);
        return empty($diff);
    }

    /**
     * @param array $triggerConfiguration
     * @param array $changedFields
     * @return bool
     */
    protected function getAllChangeSetValuesMatch($triggerConfiguration, $changedFields)
    {
        $triggerConfigurationHasNoChangeSetStateConfiguration = !array_key_exists('changeSet', $triggerConfiguration);
        if ($triggerConfigurationHasNoChangeSetStateConfiguration) {
            return true;
        }

        $diff = array_diff_assoc($triggerConfiguration['changeSet'], $changedFields);
        return empty($diff);
    }

    /**
     * The implementation of this method need to retrieve a configuration to determine which record data
     * and change combination required a recursive change.
     *
     * The structure needs to be:
     *
     * [
     *      [
     *           'currentState' => ['fieldName1' => 'value1'],
     *           'changeSet' => ['fieldName1' => 'value1']
     *      ]
     * ]
     *
     * When the all values of the currentState AND all values of the changeSet match, a recursive update
     * will be triggered.
     *
     * @return array
     */
    abstract protected function getUpdateSubPagesRecursiveTriggerConfiguration();
}
