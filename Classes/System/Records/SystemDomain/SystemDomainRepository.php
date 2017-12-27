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
        $queryBuilder = $this->getQueryBuilder();
        $resultTmp = $queryBuilder->select('uid', 'pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in('pid', $rootPageIds),
                $queryBuilder->expr()->eq('redirectTo', '\'\'')
            )->groupBy('uid', 'pid', 'sorting')
            ->orderBy('pid')
            ->addOrderBy('sorting')
            ->execute()->fetchAll();

        $result = [];
        foreach ($resultTmp as $key => $row) {
            $result[$row['pid']] = $row;
        }
        return $result;
    }
}
