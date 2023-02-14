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

namespace ApacheSolrForTypo3\Solr\System\Records;

use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use RuntimeException;
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
    protected string $table = '';

    /**
     * Retrieves a single row from the database by a given uid
     *
     * @param string $fields
     * @param int $uid
     * @return array<string,mixed>|false
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    protected function getOneRowByUid(string $fields, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select($fields)
            ->from($this->table)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->execute()
            ->fetchAssociative();
    }

    /**
     * Returns QueryBuilder for Doctrine DBAL
     *
     * @return QueryBuilder
     */
    protected function getQueryBuilder(): QueryBuilder
    {
        /* @var QueryBuilder $queryBuilder */
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->table);
    }

    /**
     * Returns current count of last searches
     *
     * @return int
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function count(): int
    {
        return (int)$this->getQueryBuilder()
            ->count('*')
            ->from($this->table)
            ->execute()
            ->fetchOne();
    }

    /**
     * Returns connection for all in transaction involved tables.
     *
     * Note: Rollback will not work in case of different connections.
     *
     * @param string ...$tableNames
     * @return Connection
     */
    public function getConnectionForAllInTransactionInvolvedTables(string ...$tableNames): Connection
    {
        if (empty($tableNames) || count($tableNames) < 2) {
            throw new InvalidArgumentException(__METHOD__ . ' requires at least 2 table names.', 1504801512);
        }

        if (!$this->isConnectionForAllTablesTheSame(...$tableNames)) {
            throw new RuntimeException(
                vsprintf(
                    'The tables "%s" using different database connections. Transaction needs same database connection ' .
                    'for all tables, please reconfigure the database settings for involved tables properly.',
                    [implode('", "', $tableNames)]
                ),
                1504866142
            );
        }
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(array_shift($tableNames));
    }

    /**
     * Checks whether all table involved in transaction using same connection.
     *
     * @param string ...$tableNames
     * @return bool
     */
    protected function isConnectionForAllTablesTheSame(string ...$tableNames): bool
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
