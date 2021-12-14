<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\ContentElementDeletedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\VersionSwappedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordMovedEvent;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent;

/**
 * A class that monitors changes to records so that the changed record gets
 * passed to the index queue to update the according index document.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class RecordMonitor
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * RecordMonitor constructor.
     *
     * @param EventDispatcherInterface|null $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Hooks into TCE main and tracks record deletion commands.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     */
    public function processCmdmap_preProcess(
        $command,
        $table,
        $uid
    ): void {
        if ($command === 'delete' && $table === 'tt_content' && $GLOBALS['BE_USER']->workspace == 0) {
            $this->eventDispatcher->dispatch(
                new ContentElementDeletedEvent((int)$uid)
            );
        }
    }

    /**
     * Hooks into TCE main and tracks workspace publish/swap events and
     * page move commands in LIVE workspace.
     *
     * @param string $command The command.
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @param string $value
     */
    public function processCmdmap_postProcess($command, $table, $uid, $value): void
    {
        $uid = (int)$uid;
        $table = (string)$table;

        if (Util::isDraftRecord($table, $uid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        // track publish / swap events for records (workspace support)
        // command "version"
        if ($command === 'version' && $value['action'] === 'swap') {
            $this->eventDispatcher->dispatch(
                new VersionSwappedEvent($uid, $table)
            );
        }

        // moving pages/records in LIVE workspace
        if ($command === 'move' && $GLOBALS['BE_USER']->workspace == 0) {
            $this->eventDispatcher->dispatch(
                new RecordMovedEvent($uid, $table)
            );
        }
    }

    /**
     * Hooks into TCE Main and watches all record creations and updates. If it
     * detects that the new/updated record belongs to a table configured for
     * indexing through Solr, we add the record to the index queue.
     *
     * @param string $status Status of the current operation, 'new' or 'update'
     * @param string $table The table the record belongs to
     * @param mixed $uid The record's uid, [integer] or [string] (like 'NEW...')
     * @param array $fields The record's data
     * @param DataHandler $tceMain TYPO3 Core Engine parent object
     * @return void
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $uid, array $fields, DataHandler $tceMain): void
    {
        $recordUid = $uid;
        $table = (string)$table;
        if ($this->skipMonitoringOfTable($table)) {
            return;
        }

        if ($status === 'new' && !MathUtility::canBeInterpretedAsInteger($recordUid)) {
            $recordUid = $tceMain->substNEWwithIDs[$recordUid];
        }
        if (Util::isDraftRecord($table, (int)$recordUid)) {
            // skip workspaces: index only LIVE workspace
            return;
        }

        $this->eventDispatcher->dispatch(
            new RecordUpdatedEvent((int)$recordUid, $table, $fields)
        );
    }

    /**
     * Check if the provided table is explicitly configured for monitoring
     *
     * @param string $table
     * @return bool
     */
    protected function skipMonitoringOfTable(string $table): bool
    {
        static $configurationMonitorTables;

        if (empty($configurationMonitorTables)) {
            $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $configurationMonitorTables = $configuration->getIsUseConfigurationMonitorTables();
        }

        // No explicit configuration => all tables should be monitored
        if (empty($configurationMonitorTables)) {
            return false;
        }

        return !in_array($table, $configurationMonitorTables);
    }
}
