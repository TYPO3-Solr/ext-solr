<?php

namespace ApacheSolrForTypo3\Solr\System\Records;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de
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

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository class to encapsulate the database access for records used in solr.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractRepository
{
    /**
     * @var string
     */
    protected $table = '';

    /**
     * Retrieves a single row from the database by a given uid
     *
     * @param string $fields
     * @param string $uid
     * @return mixed
     */
    protected function getOneRowByUid($fields, $uid)
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select($fields)
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', intval($uid)))
            ->execute()->fetch();
    }

    /**
     * Returns QueryBuilder for Doctrine DBAL
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilder()
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
        return $queryBuilder;
    }

    /**
     * Returns current count of last searches
     *
     * @return int
     */
    public function count() : int
    {
        return (int)$this->getQueryBuilder()
            ->count('*')
            ->from($this->table)
            ->execute()->fetchColumn(0);
    }

    /**
     * Returns connection for all in transaction involved tables.
     *
     * Note: Rollback will not work in case of different connections.
     *
     * @param string[] ...$tableNames
     * @return Connection
     */
    public function getConnectionForAllInTransactionInvolvedTables(string ...$tableNames) : Connection
    {
        if (empty($tableNames) || count($tableNames) < 2) {
            throw new \InvalidArgumentException(__METHOD__ . ' requires at least 2 table names.', 1504801512);
        }

        if (!$this->isConnectionForAllTablesTheSame(...$tableNames)) {
            throw new \RuntimeException(
                vsprintf('The tables "%s" using different database connections. Transaction needs same database connection ' .
                    'for all tables, please reconfigure the database settings for involved tables properly.', [implode('", "', $tableNames)]
                ), 1504866142
            );
        }
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(array_shift($tableNames));
    }

    /**
     * Checks whether all table involved in transaction using same connection.
     *
     * @param string[] ...$tableNames
     * @return bool
     */
    protected function isConnectionForAllTablesTheSame(string ...$tableNames) : bool
    {
        /** @var ConnectionPool $connectionPool */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable(array_shift($tableNames));
        foreach ($tableNames as $tableName) {
            $connectionForTable = $connectionPool->getConnectionForTable($tableName);
            if ($connection !== $connectionForTable) {
                return false;
            }
        }
        return true;
    }
}
