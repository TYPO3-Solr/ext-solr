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

namespace ApacheSolrForTypo3\Solr;

use Psr\EventDispatcher\EventDispatcherInterface;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\PageMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordGarbageCheckEvent;

/**
 * Garbage Collector, removes related documents from the index when a record is
 * set to hidden, is deleted or is otherwise made invisible to website visitors.
 *
 * Garbage collection will happen for online/LIVE workspaces only.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class GarbageCollector implements SingletonInterface
{
    /**
     * @var array
     */
    protected $trackedRecords = [];

    /**
     * @var TCAService
     */
    protected $tcaService;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * GarbageCollector constructor.
     *
     * @param TCAService|null $TCAService
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(TCAService $TCAService = null, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->tcaService = $TCAService ?? GeneralUtility::makeInstance(TCAService::class);
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Hooks into TCE main and tracks record deletion commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processCmdmap_preProcess($command, $table, $uid, $value, DataHandler $tceMain): void
    {
        // workspaces: collect garbage only for LIVE workspace
        if ($command === 'delete' && ($GLOBALS['BE_USER']->workspace ?? null) == 0) {
            $this->eventDispatcher->dispatch(
                new RecordDeletedEvent((int)$uid, (string)$table)
            );
        }
    }

    /**
     * Tracks down index documents belonging to a particular record or page and
     * removes them from the index and the Index Queue.
     *
     * @param string $table The record's table name.
     * @param int $uid The record's uid.
     * @throws \UnexpectedValueException if a hook object does not implement interface \ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor
     */
    public function collectGarbage($table, $uid): void
    {
        $this->getGarbageHandler()->collectGarbage((string)$table, (int)$uid);
    }

    /**
     * Hooks into TCE main and tracks page move commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value Not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processCmdmap_postProcess($command, $table, $uid, $value, DataHandler $tceMain) {
        // workspaces: collect garbage only for LIVE workspace
        if ($command === 'move' && $table === 'pages' && ($GLOBALS['BE_USER']->workspace ?? null) == 0) {
            $this->eventDispatcher->dispatch(
                new PageMovedEvent((int)$uid)
            );
        }
    }

    /**
     * Hooks into TCE main and tracks changed records. In this case the current
     * record's values are stored to do a change comparison later on for fields
     * like fe_group.
     *
     * @param array $incomingFields An array of incoming fields, new or changed, not used
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_preProcessFieldArray($incomingFields, $table, $uid, DataHandler $tceMain): void
    {
        if (!is_int($uid)) {
            // a newly created record, skip
            return;
        }

        $uid = (int)$uid;
        $table = (string)$table;
        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: collect garbage only for LIVE workspace
            return;
        }

        $hasConfiguredEnableColumnForFeGroup = $this->tcaService->isEnableColumn($table, 'fe_group');
        if (!$hasConfiguredEnableColumnForFeGroup) {
            return;
        }

        $record = $this->getGarbageHandler()->getRecordWithFieldRelevantForGarbageCollection($table, $uid);
        // If no record could be found skip further processing
        if (empty($record)) {
            return;
        }

        $record = $this->tcaService->normalizeFrontendGroupField($table, $record);

        // keep previous state of important fields for later comparison
        $this->trackedRecords[$table][$uid] = $record;
    }

    /**
     * Hooks into TCE Main and watches all record updates. If a change is
     * detected that would remove the record from the website, we try to find
     * related documents and remove them from the index.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data, not used
     * @param DataHandler $tceMain TYPO3 Core Engine parent object, not used
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, DataHandler $tceMain): void
    {
        if ($status === 'new') {
            // a newly created record, skip
            return;
        }

        $uid = (int)$uid;
        $table = (string)$table;
        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: collect garbage only for LIVE workspace
            return;
        }

        $updatedRecord = $this->getGarbageHandler()->getRecordWithFieldRelevantForGarbageCollection($table, $uid);
        if (empty($updatedRecord)) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new RecordGarbageCheckEvent(
                $uid,
                $table,
                $fields,
                $this->hasFrontendGroupsRemoved($table, $updatedRecord)
            )
        );
    }

    /**
     * Checks whether the a frontend group field exists for the record and if so
     * whether groups have been removed from accessing the record thus making
     * the record invisible to at least some people.
     *
     * @param string $table The table name.
     * @param array $updatedRecord An array with fields of the updated record that may affect visibility.
     * @return bool TRUE if frontend groups have been removed from access to the record, FALSE otherwise.
     */
    protected function hasFrontendGroupsRemoved(string $table, array $updatedRecord): bool
    {
        if (!isset($GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'])) {
            return false;
        }

        $frontendGroupsField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['fe_group'];

        $previousGroups = explode(',', (string)$this->trackedRecords[$table][$updatedRecord['uid']][$frontendGroupsField]);
        $currentGroups = explode(',', (string)$updatedRecord[$frontendGroupsField]);
        $removedGroups = array_diff($previousGroups, $currentGroups);

        return !empty($removedGroups);
    }

    /**
     * Returns the GarbageHandler
     *
     * @return GarbageHandler
     */
    protected function getGarbageHandler(): GarbageHandler
    {
        return GeneralUtility::makeInstance(GarbageHandler::class);
    }
}
