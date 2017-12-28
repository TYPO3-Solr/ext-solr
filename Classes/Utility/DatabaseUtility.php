<?php
namespace ApacheSolrForTypo3\Solr\Utility;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014-2015 Ingo Renner <ingo@typo3.org>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Database utility class to do things the core currently does not support
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @deprecated Since 8.0.0, will be removed in 9.0.0. Use doctrines DBAL Connection::beginTransaction()|commit()|rollBack()
 *             See ApacheSolrForTypo3\Solr\System\Records\AbstractRepository::getConnectionForAllInTransactionInvolvedTables()
 */
class DatabaseUtility
{

    /**
     * @return Connection
     */
    protected static function getFirstAvailableConnection()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
    }

    /**
     * Start a transaction
     *
     * @return void
     */
    public static function transactionStart()
    {
        self::getFirstAvailableConnection()->beginTransaction();
    }

    /**
     * Commit a transaction
     *
     * @return void
     */
    public static function transactionCommit()
    {
        self::getFirstAvailableConnection()->commit();
    }

    /**
     * Roll back a transaction
     *
     * @return void
     */
    public static function transactionRollback()
    {
        self::getFirstAvailableConnection()->rollBack();
    }
}
