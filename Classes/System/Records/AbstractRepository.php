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
 *  the Free Software Foundation; either version 2 of the License, or
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
}
