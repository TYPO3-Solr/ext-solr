<?php
namespace ApacheSolrForTypo3\Solr\System\Records\Pages;

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

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * PagesRepository to encapsulate the database access.
 */
class PagesRepository extends AbstractRepository
{

    /**
     * @var string
     */
    protected $table = 'pages';

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array An array of (partial) root page records, containing the uid and title fields
     */
    public function findAllRootPages()
    {
        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('uid', 'title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->neq('pid', -1),
                $queryBuilder->expr()->eq('is_siteroot', 1)
            )->execute()->fetchAll();

        return $result;
    }

    /**
     * Finds the MountPointProperties array for mount points(destinations) by mounted page UID(source) or by the rootline array of mounted page.
     *
     * @param int $mountedPageUid
     * @param array $rootLineParentPageIds
     * @return array
     */
    public function findMountPointPropertiesByPageIdOrByRootLineParentPageIds(int $mountedPageUid, array $rootLineParentPageIds = []) : array
    {
        if (array_filter($rootLineParentPageIds, 'is_int') !== $rootLineParentPageIds) {
            throw new \InvalidArgumentException('Given $rootLineParentPageIds array is not valid. Allowed only the arrays with the root line page UIDs as integers.', 1502459711);
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->select('uid', 'uid AS mountPageDestination', 'mount_pid AS mountPageSource', 'mount_pid_ol AS mountPageOverlayed')->from($this->table);
        $queryBuilder = $this->addWhereClauseForMountpointDestinationProperties($queryBuilder, $mountedPageUid, $rootLineParentPageIds);
        $result = $queryBuilder->execute()->fetchAll();
        return $result;
    }

    /**
     * This methods builds the where clause for the mountpoint destinations. It retrieves all records where the mount_pid = $mountedPageUid or the mount_pid is
     * in the rootLineParentPageIds.
     *
     * @param QueryBuilder $queryBuilder
     * @param int $mountedPageUid
     * @param array $rootLineParentPageIds
     * @return QueryBuilder
     */
    protected function addWhereClauseForMountpointDestinationProperties(QueryBuilder $queryBuilder, $mountedPageUid, array $rootLineParentPageIds) : QueryBuilder
    {
        if (empty($rootLineParentPageIds)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', 7),
                $queryBuilder->expr()->eq('no_search', 0),
                $queryBuilder->expr()->eq('mount_pid', $mountedPageUid),
                $queryBuilder->expr()->eq('mount_pid_ol', 1)
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', 7),
                $queryBuilder->expr()->eq('no_search', 0),
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq('mount_pid', $mountedPageUid),
                        $queryBuilder->expr()->eq('mount_pid_ol', 1)
                    ),
                    $queryBuilder->expr()->in('mount_pid', $rootLineParentPageIds)
                )
            );
        }

        return $queryBuilder;
    }

}
