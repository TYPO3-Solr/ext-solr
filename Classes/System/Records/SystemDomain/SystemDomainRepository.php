<?php
namespace ApacheSolrForTypo3\Solr\System\Records\SystemDomain;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-eb-support@dkd.de>
 *
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
use ApacheSolrForTypo3\Solr\Util;

class SystemDomainRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'sys_domain';

    /**
     * Retrieves sys_domain records for a set of root page ids.
     *
     * @param array $rootPageIds
     * @return mixed
     */
    public function findDomainRecordsByRootPagesIds(array $rootPageIds = [])
    {

        if (Util::getIsTYPO3VersionBelow9()) {
            //@todo this can be dropped when support of TYPO3 8 is dropped
            $resultTmp = $this->getDomainRecordsByRootPageIdsFor8($rootPageIds);
        } else {
            $resultTmp = $this->getDomainRecordsByRootPageIdsFor9($rootPageIds);
        }

        $result = [];
        foreach ($resultTmp as $key => $row) {
            $result[$row['pid']] = $row;
        }
        return $result;
    }

    /**
     * Fetches the domain records for TYPO3 8.
     *
     * @todo this can be dropped when support of TYPO3 8 is dropped
     * @param array $rootPageIds
     * @return array
     */
    protected function getDomainRecordsByRootPageIdsFor8(array $rootPageIds = [])
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder->select('uid', 'pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('pid', $rootPageIds),
                $queryBuilder->expr()->eq('redirectTo', '\'\'')
            )->groupBy('uid', 'pid', 'sorting')
            ->orderBy('pid')
            ->addOrderBy('sorting')
            ->execute()->fetchAll();
    }

    /**
     * Fetches the domain records for TYPO3 9.
     *
     * @param array $rootPageIds
     * @return array
     */
    protected function getDomainRecordsByRootPageIdsFor9(array $rootPageIds = [])
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder->select('uid', 'pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('pid', $rootPageIds)
            )->groupBy('uid', 'pid', 'sorting')
            ->orderBy('pid')
            ->addOrderBy('sorting')
            ->execute()->fetchAll();
    }
}
