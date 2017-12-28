<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;

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
     */
    public function removeByRootPidAndIndexQueueUid(int $rootPid, int $indexQueueUid) : int
    {
        $queryBuider = $this->getQueryBuilder();
        return $queryBuider
            ->delete($this->table)
            ->where(
                $queryBuider->expr()->eq('root', $queryBuider->createNamedParameter($rootPid, \PDO::PARAM_INT)),
                $queryBuider->expr()->eq('item_id', $queryBuider->createNamedParameter($indexQueueUid, \PDO::PARAM_INT))
            )->execute();
    }

    /**
     * Inserts a list of given properties
     *
     * @param array $properties assoc array with column names as key
     * @return int
     */
    public function bulkInsert(array $properties) : int
    {
        return $this->getQueryBuilder()->getConnection()->bulkInsert($this->table, $properties, ['root', 'item_id', 'property_key', 'property_value']);
    }

    /**
     * Fetches a list of properties related to index queue item
     *
     * @param int $indexQueueUid
     * @return array list of records for searched index queue item
     */
    public function findAllByIndexQueueUid(int $indexQueueUid) : array
    {
        $queryBuider = $this->getQueryBuilder();
        return $queryBuider
            ->select('property_key', 'property_value')
            ->from($this->table)
            ->where(
                $queryBuider->expr()->eq('item_id', $queryBuider->createNamedParameter($indexQueueUid, \PDO::PARAM_INT))
            )
            ->execute()->fetchAll();
    }
}
