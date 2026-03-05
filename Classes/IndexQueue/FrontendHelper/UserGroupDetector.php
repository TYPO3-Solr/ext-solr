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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingInstructions;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingResultCollector;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\Access\RecordAccessGrantedEvent;
use TYPO3\CMS\Core\Domain\Event\BeforePageIsRetrievedEvent;
use TYPO3\CMS\Core\Domain\Event\BeforeRecordLanguageOverlayEvent;
use TYPO3\CMS\Core\Domain\Event\ModifyDefaultConstraintsForDatabaseQueryEvent;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\Event\AfterContentObjectRendererInitializedEvent;
use TYPO3\CMS\Frontend\Event\ModifyTypoScriptConfigEvent;

/**
 * The UserGroupDetector is responsible to identify the fe_group references on records
 * that are visible on the page (not the page itself).
 *
 * Supports both the legacy PageIndexerRequest system and the new IndexingInstructions system.
 * Activation is determined by checking for either:
 * - Legacy: $activated flag set via activate() method
 * - New: solr.indexingInstructions request attribute with findUserGroups action
 *
 * TYPO3 14 compatibility: The fe_group access check uses TcaSchemaFactory which caches
 * schemas at boot time. We handle this by listening to ModifyDefaultConstraintsForDatabaseQueryEvent
 * to remove the fe_group constraint from content queries during page indexing.
 */
class UserGroupDetector implements FrontendHelper, SingletonInterface
{
    public const ACTION_NAME = 'findUserGroups';

    protected const PARAM_ORIGINAL_TCA = '_solr_userGroupDetector_originalTca';
    protected const PARAM_FRONTEND_GROUPS = '_solr_userGroupDetector_frontendGroups';

    protected string $action = self::ACTION_NAME;

    /** @var bool Legacy activation flag */
    protected bool $activated = false;

    /** @var bool New activation flag (from IndexingInstructions) */
    protected bool $activatedViaInstructions = false;

    protected ?PageIndexerRequest $request = null;

    protected ?SolrLogManager $logger = null;

    protected ?IndexingResultCollector $resultCollector = null;

    /** @var ?array Original TCA backup (for new system) */
    protected ?array $originalTca = null;

    /** @var array Collected frontend groups (for new system) */
    protected array $collectedGroups = [];

    /**
     * Legacy activation via PageIndexerRequest.
     */
    public function activate(PageIndexerRequest $request): void
    {
        $this->request = $request;
        $this->activated = true;
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    /**
     * New activation via IndexingInstructions (called by event listeners auto-detecting the attribute).
     */
    protected function activateFromInstructions(IndexingInstructions $instructions): void
    {
        if ($this->activatedViaInstructions) {
            return;
        }
        $this->activatedViaInstructions = true;
        $this->resultCollector = GeneralUtility::makeInstance(IndexingResultCollector::class);
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
        $this->collectedGroups = [];
        $this->originalTca = null;
    }

    /**
     * Check if the detector is active (either legacy or new system).
     */
    protected function isActive(): bool
    {
        return $this->activated || $this->activatedViaInstructions;
    }

    /**
     * Check if there's a findUserGroups instruction on the TYPO3 request.
     */
    protected function checkAndActivateFromRequest(): void
    {
        if ($this->activatedViaInstructions || $this->activated) {
            return;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return;
        }

        $instructions = $request->getAttribute('solr.indexingInstructions');
        if ($instructions instanceof IndexingInstructions && $instructions->isFindUserGroups()) {
            $this->activateFromInstructions($instructions);
        }
    }

    /**
     * Disables the group access check by resetting the fe_group field in the given page table row.
     */
    #[AsEventListener]
    public function checkEnableFields(RecordAccessGrantedEvent $event): void
    {
        $this->checkAndActivateFromRequest();
        if ($this->isActive()) {
            $record = $event->getRecord();
            $record['fe_group'] = '';
            $event->updateRecord($record);
        }
    }

    /**
     * Deactivates the frontend user group fields in TCA so that no access
     * restrictions apply during page rendering.
     */
    #[AsEventListener]
    public function deactivateTcaFrontendGroupEnableFields(ModifyTypoScriptConfigEvent $event): void
    {
        $this->checkAndActivateFromRequest();
        if (!$this->isActive()) {
            return;
        }

        // Store original TCA
        if ($this->activated && $this->request !== null) {
            // Legacy path
            if ($this->request->getParameter(self::PARAM_ORIGINAL_TCA) === null) {
                $this->request->setParameter(self::PARAM_ORIGINAL_TCA, $GLOBALS['TCA']);
            }
        } elseif ($this->activatedViaInstructions) {
            // New path
            if ($this->originalTca === null) {
                $this->originalTca = $GLOBALS['TCA'];
                $this->resultCollector?->setOriginalTca($GLOBALS['TCA']);
            }
        }

        foreach ($GLOBALS['TCA'] as $tableName => $tableConfiguration) {
            if (isset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group'])) {
                unset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group']);
            }
        }
    }

    /**
     * Removes the fe_group constraint from database queries during page indexing.
     */
    #[AsEventListener]
    public function removeFeGroupConstraintFromDatabaseQuery(ModifyDefaultConstraintsForDatabaseQueryEvent $event): void
    {
        $constraints = $event->getConstraints();

        if (!isset($constraints['fe_group'])) {
            return;
        }

        $this->checkAndActivateFromRequest();
        if (!$this->isActive()) {
            return;
        }

        unset($constraints['fe_group']);
        $event->setConstraints($constraints);
    }

    /**
     * Modifies the database query parameters so that access checks for pages
     * are not performed any longer.
     */
    #[AsEventListener]
    public function getPage_preProcess(BeforePageIsRetrievedEvent $event): void
    {
        $this->checkAndActivateFromRequest();
        if ($this->isActive()) {
            $event->skipGroupAccessCheck();
        }
    }

    /**
     * Modifies page records so that when checking for access through fe groups
     * no groups or extendToSubpages flag is found and thus access is granted.
     */
    #[AsEventListener]
    public function getPageOverlay_preProcess(BeforeRecordLanguageOverlayEvent $event): void
    {
        $this->checkAndActivateFromRequest();
        if (!$this->isActive()) {
            return;
        }
        if ($event->getTable() !== 'pages') {
            return;
        }
        $pageInput = $event->getRecord();
        $pageInput['fe_group'] = '';
        $pageInput['extendToSubpages'] = '0';
        $event->setRecord($pageInput);
    }

    /**
     * Hook for post-processing the initialization of ContentObjectRenderer.
     * Tracks fe_groups from content elements during rendering.
     */
    #[AsEventListener]
    public function postProcessContentObjectInitialization(AfterContentObjectRendererInitializedEvent $event): void
    {
        $this->checkAndActivateFromRequest();
        if (!$this->isActive()) {
            return;
        }
        $cObject = $event->getContentObjectRenderer();
        if (!empty($cObject->currentRecord)) {
            [$table] = explode(':', $cObject->currentRecord);

            if (!empty($table) && $table != 'pages') {
                $this->findFrontendGroups($cObject->data, $table);
            }
        }
    }

    /**
     * Tracks user groups access restriction applied to the records.
     */
    protected function findFrontendGroups(array $record, string $table): void
    {
        $originalTca = $this->getOriginalTca();

        if (isset($originalTca[$table]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroups = $record[$originalTca[$table]['ctrl']['enablecolumns']['fe_group']] ?? null;

            if (empty($frontendGroups) || $frontendGroups === '-1') {
                $frontendGroups = 0;
            }

            if ($this->activated && $this->request !== null) {
                // Legacy path
                $collectedGroups = $this->request->getParameter(self::PARAM_FRONTEND_GROUPS) ?? [];
                $collectedGroups[] = $frontendGroups;
                $this->request->setParameter(self::PARAM_FRONTEND_GROUPS, $collectedGroups);
            } elseif ($this->activatedViaInstructions) {
                // New path
                $this->collectedGroups[] = $frontendGroups;
                $this->resultCollector?->addFrontendGroup($frontendGroups);
            }
        }
    }

    /**
     * Get original TCA from whichever system is active.
     */
    protected function getOriginalTca(): array
    {
        if ($this->activated && $this->request !== null) {
            return $this->request->getParameter(self::PARAM_ORIGINAL_TCA) ?? [];
        }
        return $this->originalTca ?? [];
    }

    /**
     * Returns an array of user groups that have been tracked during page rendering.
     */
    protected function getFrontendGroups(): array
    {
        if ($this->activated && $this->request !== null) {
            $collectedGroups = $this->request->getParameter(self::PARAM_FRONTEND_GROUPS) ?? [];
        } else {
            $collectedGroups = $this->collectedGroups;
        }

        $frontendGroupsList = implode(',', $collectedGroups);
        $frontendGroups = GeneralUtility::intExplode(',', $frontendGroupsList, true);

        $frontendGroups = array_unique($frontendGroups);
        $frontendGroups = array_filter(
            array_values($frontendGroups),
            static fn(int $val): bool => ($val !== -1),
        );

        if (empty($frontendGroups)) {
            $frontendGroups[] = 0;
        }

        sort($frontendGroups, SORT_NUMERIC);
        return array_reverse($frontendGroups);
    }

    /**
     * Legacy deactivation: Adds user groups to the PageIndexerResponse.
     */
    public function deactivate(PageIndexerResponse $response): void
    {
        if ($this->request === null && !$this->activatedViaInstructions) {
            $response->addActionResult($this->action, [0]);
            $this->activated = false;
            return;
        }

        // Restore original TCA
        $originalTca = $this->getOriginalTca();
        if (!empty($originalTca)) {
            $GLOBALS['TCA'] = $originalTca;
        }

        $groups = $this->getFrontendGroups();

        // Write to legacy response
        $response->addActionResult($this->action, $groups);

        // Also write to result collector for new system
        if ($this->activatedViaInstructions && $this->resultCollector !== null) {
            $this->resultCollector->setUserGroups($groups);
        }

        $this->activated = false;
        $this->activatedViaInstructions = false;
        $this->request = null;
        $this->originalTca = null;
        $this->collectedGroups = [];
    }

    /**
     * Called after the sub-request completes to finalize results for the new system.
     * This should be called by the SolrIndexingMiddleware after page rendering.
     */
    public function finalizeForNewSystem(): array
    {
        // Restore TCA
        if ($this->originalTca !== null) {
            $GLOBALS['TCA'] = $this->originalTca;
        }

        $groups = $this->getFrontendGroups();

        if ($this->resultCollector !== null) {
            $this->resultCollector->setUserGroups($groups);
        }

        // Reset state
        $this->activatedViaInstructions = false;
        $this->originalTca = null;
        $this->collectedGroups = [];

        return $groups;
    }
}
