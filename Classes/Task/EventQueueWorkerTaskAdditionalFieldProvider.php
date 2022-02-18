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

namespace ApacheSolrForTypo3\Solr\Task;

use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/**
 * Additional field provider for the index queue worker task
 *
 * @author Markus Friedrich <markus.friedrich@dkd.de>
 */
class EventQueueWorkerTaskAdditionalFieldProvider implements AdditionalFieldProviderInterface
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
    ): array {
        /** @var $task EventQueueWorkerTask */
        $additionalFields = [];

        if (!$task instanceof EventQueueWorkerTask) {
            return $additionalFields;
        }

        if ($schedulerModule->getCurrentAction()->equals(Action::EDIT)) {
            $taskInfo['solr_eventqueueworkertask_limit'] = $task->getLimit();
        }

        $additionalFields['limit'] = [
            'code' => '<input type="number" class="form-control" name="tx_scheduler[solr_eventqueueworkertask_limit]" value="' . (int)$taskInfo['solr_eventqueueworkertask_limit'] . '" />',
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang_be.xlf:task.eventQueueWorkerTask.limit',
            'cshKey' => '',
            'cshLabel' => ''
        ];

        return $additionalFields;
    }

    /**
     * Checks any additional data that is relevant to this task. If the task
     * class is not relevant, the method is expected to return TRUE
     *
     * @param array $submittedData reference to the array containing the data submitted by the user
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     * @return bool True if validation was ok (or selected class is not relevant), FALSE otherwise
     */
    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ): bool {
        $submittedData['solr_eventqueueworkertask_limit'] = max(
            (int)($submittedData['solr_eventqueueworkertask_limit'] ?? EventQueueWorkerTask::DEFAULT_PROCESSING_LIMIT),
            1
        );
        return true;
    }

    /**
     * Saves the custom limit
     *
     * @param array $submittedData array containing the data submitted by the user
     * @param AbstractTask $task reference to the current task object
     */
    public function saveAdditionalFields(
        array $submittedData,
        AbstractTask $task
    ): void {
        if (!$task instanceof EventQueueWorkerTask) {
            return;
        }

        $task->setLimit($submittedData['solr_eventqueueworkertask_limit']);
    }
}
