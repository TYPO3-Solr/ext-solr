<?php

namespace ApacheSolrForTypo3\Solr\Migrations;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Removes the Site property from the SchedulerTask and set the rootPageId property.
 */
class RemoveSiteFromScheduler implements Migration {

    /**
     * Called by the extension manager to determine if the update menu entry
     * should by showed.
     *
     * @return bool
     */
    public function isNeeded()
    {
        $taskRows = $this->getTasksWithAssignedSite();
        $legacySchedulerTaskCount = $taskRows->rowCount();

        return $legacySchedulerTaskCount > 0;
    }

    /**
     * Main update function called by the extension manager.
     *
     * @return string
     */
    public function process()
    {
        $taskRows = $this->getTasksWithAssignedSite();
        $legacySchedulerTasks = $taskRows->fetchAll();
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable("tx_scheduler_task");

        $status = FlashMessage::OK;
        $title = 'Remove site from scheduler task';
        $failedTaskCount = 0;
        $migratedTaskCount = 0;
        foreach ($legacySchedulerTasks as $legacySchedulerTask) {
            try {
                $uid = $legacySchedulerTask['uid'];
                $task = unserialize($legacySchedulerTask['serialized_task_object']);
                $task->setRootPageId($task->getSite()->getRootPageId());
                $updatedTask = serialize($task);
                $updatedRows = $queryBuilder->update('tx_scheduler_task')
                    ->where($queryBuilder->expr()->eq('uid', $uid))
                    ->set('serialized_task_object', $updatedTask)
                    ->execute();

                $migratedTaskCount += $updatedRows;
            } catch (\Exception $e) {
                $failedTaskCount++;
                $status = FlashMessage::ERROR;
            }
        }

        $message = 'Migrated ' . (int)$migratedTaskCount . ' scheduler tasks! Update of ' . (int)$failedTaskCount . ' failed!';
        return [$status, $title, $message];
    }

    /**
     * @return \Doctrine\DBAL\Driver\Statement|int
     */
    protected function getTasksWithAssignedSite()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable("tx_scheduler_task");
        $taskRows = $queryBuilder
            ->select('uid', 'serialized_task_object')
            ->from("tx_scheduler_task")
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->like('serialized_task_object', "'%ApacheSolrForTypo3%'"),
                    $queryBuilder->expr()->like('serialized_task_object', "'%site\";O:28:\"%'")
                )
            )->execute();

        return $taskRows;
    }
}
