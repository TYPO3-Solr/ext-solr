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
 * The UserGroupDetector is responsible to identify the fe_group references on records that are visible on the page (not the page itself).
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

    /**
     * This frontend helper's executed action.
     */
    protected string $action = self::ACTION_NAME;

    protected bool $activated = false;

    protected ?PageIndexerRequest $request = null;

    protected ?SolrLogManager $logger = null;

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     */
    public function activate(PageIndexerRequest $request): void
    {
        $this->request = $request;
        $this->activated = true;
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
    }

    /**
     * Disables the group access check by resetting the fe_group field in the given page table row.
     */
    #[AsEventListener]
    public function checkEnableFields(RecordAccessGrantedEvent $event): void
    {
        if ($this->activated) {
            $record = $event->getRecord();
            $record['fe_group'] = '';
            $event->updateRecord($record);
        }
    }

    /**
     * Deactivates the frontend user group fields in TCA so that no access
     * restrictions apply during page rendering.
     *
     * Note: In TYPO3 14, this TCA modification no longer affects content queries
     * because FrontendGroupRestriction uses cached TcaSchema instead of runtime TCA.
     * The removeFeGroupConstraintFromDatabaseQuery() listener handles that case.
     */
    #[AsEventListener]
    public function deactivateTcaFrontendGroupEnableFields(ModifyTypoScriptConfigEvent $event): void
    {
        if (!$this->activated) {
            return;
        }

        if ($this->request->getParameter(self::PARAM_ORIGINAL_TCA) === null) {
            $this->request->setParameter(self::PARAM_ORIGINAL_TCA, $GLOBALS['TCA']);
        }

        foreach ($GLOBALS['TCA'] as $tableName => $tableConfiguration) {
            if (isset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group'])) {
                unset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']['fe_group']);
            }
        }
    }

    /**
     * Removes the fe_group constraint from database queries during page indexing.
     *
     * In TYPO3 14, the FrontendGroupRestriction uses TcaSchemaFactory which caches
     * schemas at boot time, so runtime TCA modifications have no effect. This event
     * listener directly removes the fe_group constraint from the query constraints.
     */
    #[AsEventListener]
    public function removeFeGroupConstraintFromDatabaseQuery(ModifyDefaultConstraintsForDatabaseQueryEvent $event): void
    {
        $constraints = $event->getConstraints();

        if (!isset($constraints['fe_group'])) {
            return;
        }

        if (!$this->activated) {
            return;
        }

        unset($constraints['fe_group']);
        $event->setConstraints($constraints);
    }

    // manipulation

    /**
     * Modifies the database query parameters so that access checks for pages
     * are not performed any longer.
     */
    #[AsEventListener]
    public function getPage_preProcess(BeforePageIsRetrievedEvent $event): void
    {
        if ($this->activated) {
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
        if (!$this->activated) {
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

    // execution

    /**
     * Hook for post-processing the initialization of ContentObjectRenderer
     */
    #[AsEventListener]
    public function postProcessContentObjectInitialization(AfterContentObjectRendererInitializedEvent $event): void
    {
        if (!$this->activated) {
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
        $originalTca = $this->request->getParameter(self::PARAM_ORIGINAL_TCA) ?? [];

        if (isset($originalTca[$table]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroups = $record[$originalTca[$table]['ctrl']['enablecolumns']['fe_group']] ?? null;

            if (empty($frontendGroups) || $frontendGroups === '-1') {
                // default = public access
                $frontendGroups = 0;
            } elseif ($this->request->getParameter('loggingEnabled')) {
                $this->logger?->info(
                    'Access restriction found',
                    [
                        'groups' => $frontendGroups,
                        'record' => $record,
                        'record type' => $table,
                    ],
                );
            }

            $collectedGroups = $this->request->getParameter(self::PARAM_FRONTEND_GROUPS) ?? [];
            $collectedGroups[] = $frontendGroups;
            $this->request->setParameter(self::PARAM_FRONTEND_GROUPS, $collectedGroups);
        }
    }

    /**
     * Returns an array of user groups that have been tracked during page rendering.
     */
    protected function getFrontendGroups(): array
    {
        $collectedGroups = $this->request->getParameter(self::PARAM_FRONTEND_GROUPS) ?? [];
        $frontendGroupsList = implode(',', $collectedGroups);
        $frontendGroups = GeneralUtility::intExplode(
            ',',
            $frontendGroupsList,
            true,
        );

        // clean up: filter double groups
        $frontendGroups = array_unique($frontendGroups);
        $frontendGroups = array_filter(
            array_values($frontendGroups),
            static fn(int $val): bool => ($val !== -1),
        );

        if (empty($frontendGroups)) {
            // most likely an empty page with no content elements => public
            $frontendGroups[] = 0;
        }

        // Index user groups first
        sort($frontendGroups, SORT_NUMERIC);
        return array_reverse($frontendGroups);
    }

    /**
     * Adds the user groups found to the PageIndexerResponse
     */
    public function deactivate(PageIndexerResponse $response): void
    {
        if ($this->request === null) {
            $response->addActionResult($this->action, [0]);
            $this->activated = false;
            return;
        }

        // Restore original TCA
        $originalTca = $this->request->getParameter(self::PARAM_ORIGINAL_TCA);
        if ($originalTca !== null) {
            $GLOBALS['TCA'] = $originalTca;
        }

        $response->addActionResult($this->action, $this->getFrontendGroups());
        $this->activated = false;
        $this->request = null;
    }
}
