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
use Doctrine\DBAL\ParameterType;

/**
 * Class IndexQueueIndexingPropertyRepository
 * Handles all CRUD operations to tx_solr_indexqueue_indexing_property table
 */
class IndexQueueIndexingPropertyRepository extends AbstractRepository
{
    protected string $table = 'tx_solr_indexqueue_indexing_property';

    /**
     * Removes existing indexing properties.
     */
    public function removeByRootPidAndIndexQueueUid(int $rootPid, int $indexQueueUid): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->delete($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'root',
                    $queryBuilder->createNamedParameter($rootPid, ParameterType::INTEGER),
                ),
                $queryBuilder->expr()->eq(
                    'item_id',
                    $queryBuilder->createNamedParameter($indexQueueUid, ParameterType::INTEGER),
                ),
            )->executeStatement();
    }

    /**
     * Inserts a list of given properties provided by $properties var as assoc array with column names as key
     */
    public function bulkInsert(array $properties): int
    {
        return $this->getQueryBuilder()->getConnection()->bulkInsert($this->table, $properties, ['root', 'item_id', 'property_key', 'property_value']);
    }

    /**
     * Fetches a list of properties related to index queue item uid
     *
     * @throws DBALException
     */
    public function findAllByIndexQueueUid(int $indexQueueUid): array
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
            ->select('property_key', 'property_value')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq(
                    'item_id',
                    $queryBuilder->createNamedParameter($indexQueueUid, ParameterType::INTEGER),
                ),
            )->executeQuery()
            ->fetchAllAssociative();
    }
}
