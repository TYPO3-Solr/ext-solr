<?php
namespace ApacheSolrForTypo3\Solr\Task;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Additional field provider for the index queue worker task
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class IndexQueueWorkerTaskAdditionalFieldProvider implements AdditionalFieldProviderInterface
{

    /**
     * Used to define fields to provide the TYPO3 site to index and number of
     * items to index per run when adding or editing a task.
     *
     * @param array $taskInfo reference to the array containing the info used in the add/edit form
     * @param AbstractTask $task when editing, reference to the current task object. Null when adding.
     * @param SchedulerModuleController $schedulerModule : reference to the calling object (Scheduler's BE module)
     * @return array Array containing all the information pertaining to the additional fields
     *                    The array is multidimensional, keyed to the task class name and each field's id
     *                    For each field it provides an associative sub-array with the following:
     */
    public function getAdditionalFields(
        array &$taskInfo,
        $task,
        SchedulerModuleController $schedulerModule
    ) {
        $additionalFields = array();

        if ($schedulerModule->CMD == 'add') {
            $taskInfo['site'] = null;
            $taskInfo['documentsToIndexLimit'] = 50;
        }

        if ($schedulerModule->CMD == 'edit') {
            $taskInfo['site'] = $task->getSite();
            $taskInfo['documentsToIndexLimit'] = $task->getDocumentsToIndexLimit();
        }

        $additionalFields['site'] = array(
            'code' => Site::getAvailableSitesSelector('tx_scheduler[site]',
                $taskInfo['site']),
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:field_site',
            'cshKey' => '',
            'cshLabel' => ''
        );

        $additionalFields['documentsToIndexLimit'] = array(
            'code' => '<input type="text" name="tx_scheduler[documentsToIndexLimit]" value="' . htmlspecialchars($taskInfo['documentsToIndexLimit']) . '" />',
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:indexqueueworker_field_documentsToIndexLimit',
            'cshKey' => '',
            'cshLabel' => ''
        );

        return $additionalFields;
    }

    /**
     * Checks any additional data that is relevant to this task. If the task
     * class is not relevant, the method is expected to return TRUE
     *
     * @param array $submittedData reference to the array containing the data submitted by the user
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     * @return boolean True if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ) {
        $result = false;

        // validate site
        $sites = Site::getAvailableSites();
        if (array_key_exists($submittedData['site'], $sites)) {
            $result = true;
        }

        // escape limit
        $submittedData['documentsToIndexLimit'] = intval($submittedData['documentsToIndexLimit']);

        return $result;
    }

    /**
     * Saves any additional input into the current task object if the task
     * class matches.
     *
     * @param array $submittedData array containing the data submitted by the user
     * @param AbstractTask $task reference to the current task object
     */
    public function saveAdditionalFields(
        array $submittedData,
        AbstractTask $task
    ) {
        $task->setSite(GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Site',
            $submittedData['site']));
        $task->setDocumentsToIndexLimit($submittedData['documentsToIndexLimit']);
    }
}
