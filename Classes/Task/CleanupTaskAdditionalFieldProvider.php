<?php

declare(strict_types=1);

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

use ApacheSolrForTypo3\Solr\Backend\SiteSelectorField;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use Doctrine\DBAL\Exception as DBALException;
use LogicException;
use TYPO3\CMS\Backend\Form\Exception as BackendFormException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/**
 * Adds additional field to specify the Solr server to initialize the index queue for
 *
 * @author Christoph Moeller <support@network-publishing.de>
 */
class CleanupTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    protected array $taskInformation = [];

    /**
     * Scheduler task
     */
    protected ?CleanupIndexTask $task;

    protected ?SchedulerModuleController $schedulerModule = null;

    /**
     * Selected site
     */
    protected ?Site $site = null;

    protected SiteRepository $siteRepository;

    protected ?PageRenderer $pageRenderer = null;

    public function __construct()
    {
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * Initialize object
     *
     * @throws DBALException
     */
    protected function initialize(
        array $taskInfo,
        ?CleanupIndexTask $task,
        SchedulerModuleController $schedulerModule
    ): void {
        /* ReIndexTask @var $task  */
        $this->taskInformation = $taskInfo;
        $this->task = $task;
        $this->schedulerModule = $schedulerModule;

        $currentAction = $schedulerModule->getCurrentAction();

        if ($currentAction->equals(Action::EDIT)) {
            $this->site = $this->siteRepository->getSiteByRootPageId((int)$task->getRootPageId());
        }
    }

    /**
     * Used to define fields to provide the Solr server address when adding
     * or editing a task.
     *
     * @param array $taskInfo reference to the array containing the info used in the add/edit form
     * @param CleanupIndexTask $task when editing, reference to the current task object. Null when adding.
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     *
     * @return array Array containing all the information pertaining to the additional fields
     *               The array is multidimensional, keyed to the task class name and each field's id
     *               For each field it provides an associative sub-array with the following:
     *
     * @throws BackendFormException
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws DBALException
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getAdditionalFields(
        array &$taskInfo,
        $task,
        SchedulerModuleController $schedulerModule
    ) {
        $additionalFields = [];

        if (!$this->isTaskInstanceofCleanupIndexTask($task)) {
            return $additionalFields;
        }

        $this->initialize($taskInfo, $task, $schedulerModule);
        $siteSelectorField = GeneralUtility::makeInstance(SiteSelectorField::class);

        $additionalFields['site'] = [
            'code' => $siteSelectorField->getAvailableSitesSelector(
                'tx_scheduler[site]',
                $this->site
            ),
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:field_site',
        ];

        $additionalFields['deleteOlderThanDays'] = [
            'code' => '<input class="form-control" type="number" name="tx_scheduler[deleteOlderThanDays]" value="' . ($schedulerModule->getCurrentAction() == Action::EDIT ? $task->getDeleteOlderThanDays() ?? 1 : 1) . '" />',
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:task.cleanupIndex.deleteOlderThanDays',
        ];

        return $additionalFields;
    }

    /**
     * Checks any additional data that is relevant to this task. If the task
     * class is not relevant, the method is expected to return TRUE
     *
     * @param array $submittedData reference to the array containing the data submitted by the user
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     *
     * @return bool True if validation was ok (or selected class is not relevant), FALSE otherwise
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     *
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ): bool {
        $result = false;

        // validate site
        $sites = $this->siteRepository->getAvailableSites();
        if (array_key_exists($submittedData['site'], $sites)) {
            $result = true;
        }

        return $result;
    }

    /**
     * Saves any additional input into the current task object if the task
     * class matches.
     *
     * @param array $submittedData array containing the data submitted by the user
     * @param ReIndexTask $task reference to the current task object
     */
    public function saveAdditionalFields(
        array $submittedData,
        CleanupIndexTask|AbstractTask $task
    ): void {
        if (!$this->isTaskInstanceofCleanupIndexTask($task)) {
            return;
        }

        $task->setRootPageId((int)$submittedData['site']);
        $task->setDeleteOlderThanDays($submittedData['deleteOlderThanDays'] ? (int)$submittedData['deleteOlderThanDays'] : null);
    }

    /**
     * Check that a task is an instance of ReIndexTask
     */
    protected function isTaskInstanceofCleanupIndexTask(?AbstractTask $task): bool
    {
        if ((!is_null($task)) && (!($task instanceof CleanupIndexTask))) {
            throw new LogicException(
                '$task must be an instance of ReIndexTask, '
                . 'other instances are not supported.',
                1487500366
            );
        }
        return true;
    }
}
