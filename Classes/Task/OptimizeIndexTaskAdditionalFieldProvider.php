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

use ApacheSolrForTypo3\Solr\Backend\CoreSelectorField;
use ApacheSolrForTypo3\Solr\Backend\SiteSelectorField;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use Doctrine\DBAL\Exception as DBALException;
use LogicException;
use Throwable;
use TYPO3\CMS\Backend\Form\Exception as BackendFormException;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/**
 * Adds additional field to specify the Solr server to initialize the index queue for
 */
class OptimizeIndexTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * Default language file of the extension link validator
     */
    protected string $languageFile = 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf';

    /**
     * Task information
     */
    protected array $taskInformation = [];

    /**
     * Scheduler task
     */
    protected ?OptimizeIndexTask $task = null;

    /**
     * Scheduler Module
     */
    protected ?SchedulerModuleController $schedulerModule = null;

    /**
     * Selected site
     */
    protected ?Site $site = null;

    /**
     * SiteRepository
     */
    protected ?SiteRepository $siteRepository = null;

    /**
     * PageRenderer
     */
    protected ?PageRenderer $pageRenderer = null;

    public function __construct()
    {
        $this->siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
    }

    /**
     * Initializes this instance and the necessary objects.
     *
     * @throws DBALException
     */
    protected function initialize(
        SchedulerModuleController $schedulerModule,
        OptimizeIndexTask $task = null,
        array $taskInfo = []
    ): void {
        $this->task = $task;
        $this->schedulerModule = $schedulerModule;
        $this->taskInformation = $taskInfo;

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
     * @param OptimizeIndexTask $task when editing, reference to the current task object. Null when adding.
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     * @return array Array containing all the information pertaining to the additional fields
     *                        The array is multidimensional, keyed to the task class name and each field's id
     *                        For each field it provides an associative sub-array with the following:
     *
     * @throws BackendFormException
     * @throws Throwable
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

        if (!$this->isTaskInstanceofOptimizeIndexTask($task)) {
            return $additionalFields;
        }

        $this->initialize($schedulerModule, $task, $taskInfo);
        $siteSelectorField = GeneralUtility::makeInstance(SiteSelectorField::class);

        $additionalFields['site'] = [
            'code' => $siteSelectorField->getAvailableSitesSelector('tx_scheduler[site]', $this->site),
            'label' => $this->languageFile . ':field_site',
        ];

        $additionalFields['cores'] = [
            'code' => $this->getCoreSelectorMarkup(),
            'label' => $this->languageFile . ':field_cores',
        ];

        return $additionalFields;
    }

    /**
     * Returns the selector HTML element with available cores.
     *
     * @throws BackendFormException
     */
    protected function getCoreSelectorMarkup(): string
    {
        $selectorMarkup = $this->getLanguageService()->sL($this->languageFile . ':tasks.validate.selectSiteFirst');

        if (is_null($this->site)) {
            return $selectorMarkup;
        }

        /** @var CoreSelectorField $selectorField */
        $selectorField = GeneralUtility::makeInstance(CoreSelectorField::class, $this->site);
        $selectorField->setFormElementName('tx_scheduler[cores]');
        $selectorField->setSelectedValues($this->task->getCoresToOptimizeIndex());
        return $selectorField->render();
    }

    /**
     * Checks any additional data that is relevant to this task. If the task
     * class is not relevant, the method is expected to return TRUE
     *
     * @param array $submittedData reference to the array containing the data submitted by the user
     * @param SchedulerModuleController $schedulerModule reference to the calling object (Scheduler's BE module)
     * @return bool True if validation was ok (or selected class is not relevant), FALSE otherwise
     * @throws Throwable
     * @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function validateAdditionalFields(
        array &$submittedData,
        SchedulerModuleController $schedulerModule
    ) {
        $result = true;
        // validate site
        $sites = $this->siteRepository->getAvailableSites();
        if (!array_key_exists($submittedData['site'], $sites)) {
            $result = false;
        }

        // validate core selection
        if (array_key_exists('cores', $submittedData)
            && !is_array($submittedData['cores'])
        ) {
            $this->addMessage(
                $this->getLanguageService()->sL($this->languageFile . ':tasks.validate.invalidCores'),
                ContextualFeedbackSeverity::ERROR
            );
            $result = false;
        }
        return $result;
    }

    /**
     * Saves any additional input into the current task object if the task
     * class matches.
     *
     * @param array $submittedData array containing the data submitted by the user
     * @param OptimizeIndexTask $task reference to the current task object
     */
    public function saveAdditionalFields(
        array $submittedData,
        OptimizeIndexTask|AbstractTask $task
    ): void {
        if (!$this->isTaskInstanceofOptimizeIndexTask($task)) {
            return;
        }

        $task->setRootPageId((int)$submittedData['site']);

        $cores = [];
        if (!empty($submittedData['cores'])) {
            $cores = $submittedData['cores'];
        }
        $task->setCoresToOptimizeIndex($cores);
    }

    protected function getPageRenderer(): ?PageRenderer
    {
        if (!isset($this->pageRenderer)) {
            $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        }
        return $this->pageRenderer;
    }

    /**
     * Check that a task is an instance of ReIndexTask
     */
    protected function isTaskInstanceofOptimizeIndexTask(?AbstractTask $task): bool
    {
        if ((!is_null($task)) && (!($task instanceof OptimizeIndexTask))) {
            throw new LogicException(
                '$task must be an instance of OptimizeIndexTask, '
                . 'other instances are not supported.',
                1624620844
            );
        }
        return true;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
