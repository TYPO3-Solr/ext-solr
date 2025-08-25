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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Abstract update handler
 *
 * Base class for Handling updates or deletions on potential
 * relevant records
 */
abstract class AbstractUpdateHandler
{
    /**
     * List of fields in the update field array that
     * are required for processing
     *
     * Note: For pages all fields except l10n_diffsource are
     *       kept, as additional fields can be configured in
     *       TypoScript, see AbstractDataUpdateEvent->__sleep.
     */
    protected static array $requiredUpdatedFields = [];

    /**
     * Configuration used to check if recursive updates are required
     *
     * Update handlers may need to determine which update combination
     * require a recursive change.
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
     */
    protected array $updateSubPagesRecursiveTriggerConfiguration = [];

    protected ConfigurationAwareRecordService $configurationAwareRecordService;

    protected FrontendEnvironment $frontendEnvironment;

    protected TCAService $tcaService;

    protected Queue $indexQueue;

    protected ?PagesRepository $pagesRepository;

    protected SolrLogManager $logger;

    /**
     * @var QueryBuilder[]
     */
    protected array $queryBuilders = [];

    public function __construct(
        ConfigurationAwareRecordService $recordService,
        FrontendEnvironment $frontendEnvironment,
        TCAService $tcaService,
        Queue $indexQueue,
        ?SolrLogManager $solrLogManager = null,
    ) {
        $this->configurationAwareRecordService = $recordService;
        $this->frontendEnvironment = $frontendEnvironment;
        $this->tcaService = $tcaService;
        $this->indexQueue = $indexQueue;

        $this->logger = $solrLogManager ?? GeneralUtility::makeInstance(
            SolrLogManager::class,
            __CLASS__,
        );
    }

    /**
     * Returns the required fields from the updated fields array
     */
    public static function getRequiredUpdatedFields(): array
    {
        return static::$requiredUpdatedFields;
    }

    /**
     * Add required update field
     */
    public static function addRequiredUpdatedField(string $field): void
    {
        static::$requiredUpdatedFields[] = $field;
    }

    /**
     * Returns all relevant fields for current state
     */
    protected function getAllRelevantFieldsForCurrentState(): array
    {
        $allCurrentStateFieldnames = [];

        foreach ($this->getUpdateSubPagesRecursiveTriggerConfiguration() as $triggerConfiguration) {
            if (!isset($triggerConfiguration['currentState']) || !is_array($triggerConfiguration['currentState'])) {
                // when no "currentState" configuration for the trigger exists we can skip it
                continue;
            }

            // we collect the currentState fields to return a unique list of all fields
            $allCurrentStateFieldnames = array_merge(
                $allCurrentStateFieldnames,
                array_keys($triggerConfiguration['currentState']),
            );
        }

        return array_unique($allCurrentStateFieldnames);
    }

    /**
     * When the extend-to-subpages flag was set, we determine the affected subpages and return them.
     *
     * @throws DBALException
     */
    protected function getSubPageIds(int $pageId): array
    {
        // here we retrieve only the subpages of this page because the permission clause is not evaluated
        // on the root node.
        $permissionClause = ' 1 ' . $this->getPagesRepository()->getBackendEnableFields();
        $treePageIdList = $this->getPagesRepository()->getTreeList($pageId, 20, 0, $permissionClause);
        $treePageIds = array_map('intval', explode(',', $treePageIdList));

        // the first one can be ignored because this is the page itself
        array_shift($treePageIds);

        return $treePageIds;
    }

    /**
     * Checks if a page update will trigger a recursive update of pages
     *
     * This can either be the case if some $changedFields are part of the RecursiveUpdateTriggerConfiguration or
     * columns have explicitly been configured via plugin.tx_solr.index.queue.recursiveUpdateFields
     *
     * @throws DBALException
     */
    protected function isRecursivePageUpdateRequired(int $pageId, array $updatedFields): bool
    {
        // First check RecursiveUpdateTriggerConfiguration
        $isRecursiveUpdateRequired = $this->isRecursiveUpdateRequired($pageId, $updatedFields);
        // If RecursiveUpdateTriggerConfiguration is false => check if changeFields are part of recursiveUpdateFields
        if ($isRecursiveUpdateRequired === false) {
            try {
                $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($pageId);
                $indexQueueConfigurationName = $this->configurationAwareRecordService->getIndexingConfigurationName(
                    'pages',
                    $pageId,
                    $solrConfiguration,
                );
            } catch (SiteNotFoundException $e) {
                $this->logger->log(
                    LogLevel::WARNING,
                    'Couldn\t determine site for page ' . $pageId,
                    [
                        'pageUid' => $pageId,
                        'error' => [
                            'code' => $e->getCode(),
                            'file' => $e->getFile() . ':' . $e->getLine(),
                            'message' => $e->getMessage(),
                        ],
                    ],
                );
                return false;
            }
            if ($indexQueueConfigurationName === null) {
                return false;
            }
            $updateFields = $solrConfiguration->getIndexQueueConfigurationRecursiveUpdateFields(
                $indexQueueConfigurationName,
            );

            // Check if no additional fields have been defined and then skip recursive update
            if (empty($updateFields)) {
                return false;
            }
            // If the recursiveUpdateFields configuration is not part of the $changedFields skip recursive update
            if (!array_intersect_key($updatedFields, $updateFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks whether recursive update is required
     */
    protected function isRecursiveUpdateRequired(int $pageId, array $updatedFields): bool
    {
        $fieldsForCurrentState = $this->getAllRelevantFieldsForCurrentState();
        $fieldListToRetrieve = implode(',', $fieldsForCurrentState);
        $page = $this->getPagesRepository()->getPage($pageId, $fieldListToRetrieve, '', false);
        foreach ($this->getUpdateSubPagesRecursiveTriggerConfiguration() as $triggerConfiguration) {
            $allCurrentStateFieldsMatch = $this->getAllCurrentStateFieldsMatch($triggerConfiguration, $page);
            $allChangeSetValuesMatch = $this->getAllChangeSetValuesMatch($triggerConfiguration, $updatedFields);

            $aMatchingTriggerHasBeenFound = $allCurrentStateFieldsMatch && $allChangeSetValuesMatch;
            if ($aMatchingTriggerHasBeenFound) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all current state fields match
     */
    protected function getAllCurrentStateFieldsMatch(array $triggerConfiguration, array $pageRecord): bool
    {
        $triggerConfigurationHasNoCurrentStateConfiguration = !array_key_exists('currentState', $triggerConfiguration);
        if ($triggerConfigurationHasNoCurrentStateConfiguration) {
            return true;
        }
        $diff = array_diff_assoc($triggerConfiguration['currentState'], $pageRecord);
        return empty($diff);
    }

    /**
     * Returns all change-set values match
     */
    protected function getAllChangeSetValuesMatch(array $triggerConfiguration, array $changedFields): bool
    {
        $triggerConfigurationHasNoChangeSetStateConfiguration = !array_key_exists('changeSet', $triggerConfiguration);
        if ($triggerConfigurationHasNoChangeSetStateConfiguration) {
            return true;
        }

        // Return true if triggerConfiguration field value is '*' and it's key is in changedFields array
        foreach ($triggerConfiguration['changeSet'] as $key => $value) {
            if ($value === '*' && array_key_exists($key, $changedFields)) {
                return true;
            }
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
     */
    protected function getUpdateSubPagesRecursiveTriggerConfiguration(): array
    {
        return $this->updateSubPagesRecursiveTriggerConfiguration;
    }

    protected function getPagesRepository(): PagesRepository
    {
        if (!isset($this->pagesRepository)) {
            $this->pagesRepository = GeneralUtility::makeInstance(PagesRepository::class);
        }

        return $this->pagesRepository;
    }

    /**
     * Returns the prepared QueryBuilder for given table
     */
    protected function getQueryBuilderForTable(string $table): QueryBuilder
    {
        if (!isset($this->queryBuilders[$table])) {
            $this->queryBuilders[$table] = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        }

        return $this->queryBuilders[$table];
    }

    /**
     * Wraps {@link BackendUtility::getRecord()}
     *
     * Purpose: Unit-Tests
     */
    protected function getRecord(
        string $table,
        int|string $uid,
        string $fields = '*',
        string $where = '',
        bool $useDeleteClause = true,
    ): ?array {
        return BackendUtility::getRecord(
            $table,
            $uid,
            $fields,
            $where,
            $useDeleteClause,
        );
    }
}
