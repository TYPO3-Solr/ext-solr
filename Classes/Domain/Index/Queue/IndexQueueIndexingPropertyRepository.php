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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use PDO;

/**
 * Class IndexQueueIndexingPropertyRepository
 * Handles all CRUD operations to tx_solr_indexqueue_indexing_property table
 */
class IndexQueueIndexingPropertyRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'tx_solr_indexqueue_indexing_property';

    /**
     * Removes existing indexing properties.
     *
     * @param int $rootPid
     * @param int $indexQueueUid
     * @return int
     * @throws DBALException
     */
    public function removeByRootPidAndIndexQueueUid(int $rootPid, int $indexQueueUid): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return (int)$queryBuilder
            ->delete($this->table)
            ->where(
                /** @scrutinizer ignore-type */
                $queryBuilder->expr()->eq(
                    'root',
                    $queryBuilder->createNamedParameter($rootPid, PDO::PARAM_INT)
                ),
                /** @scrutinizer ignore-type */
                $queryBuilder->expr()->eq(
                    'item_id',
                    $queryBuilder->createNamedParameter($indexQueueUid, PDO::PARAM_INT)
                )
            )->execute();
    }

    /**
     * Inserts a list of given properties
     *
     * @param array $properties assoc array with column names as key
     * @return int
     */
    public function bulkInsert(array $properties): int
    {
        return $this->getQueryBuilder()->getConnection()->bulkInsert($this->table, $properties, ['root', 'item_id', 'property_key', 'property_value']);
    }

    /**
     * Fetches a list of properties related to index queue item
     *
     * @param int $indexQueueUid
     * @return array list of records for searched index queue item
     * @throws DBALDriverException
     * @throws DBALException
     */
    public function findAllByIndexQueueUid(int $indexQueueUid): array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('property_key', 'property_value')
            ->from($this->table)
            ->where(
                /** @scrutinizer ignore-type */
                $queryBuilder->expr()->eq(
                    'item_id',
                    $queryBuilder->createNamedParameter($indexQueueUid, PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAllAssociative();
    }
}
