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

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * @var TwoLevelCache
     */
    protected $transientVariableCache;

    /**
     * PagesRepository constructor.
     *
     * @param TwoLevelCache|null $transientVariableCache
     */
    public function __construct(TwoLevelCache $transientVariableCache = null)
    {
        $this->transientVariableCache = $transientVariableCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'cache_runtime');
    }

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array An array of (partial) root page records, containing the uid and title fields
     */
    public function findAllRootPages()
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->select('uid', 'title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->neq('pid', -1),
                $queryBuilder->expr()->eq('is_siteroot', 1)
            );


        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder->execute()->fetchAll();
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

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder;
    }

    /**
     * Generates a list of page IDs in this site.
     * Attention: Includes all page types except Deleted pages!
     *
     * @param int $rootPageId Page ID from where to start collection sub pages
     * @param int $maxDepth Maximum depth to descend into the site tree
     * @param string $initialPagesAdditionalWhereClause
     * @return array Array of pages (IDs) in this site
     */
    public function findAllSubPageIdsByRootPage(int $rootPageId, int $maxDepth = 999, string $initialPagesAdditionalWhereClause = '') : array
    {
        $pageIds = [];

        $recursionRootPageId = $rootPageId;

        // when we have a cached value, we can return it.
        $cacheIdentifier = sha1('getPages' . (string)$rootPageId);
        if ($this->transientVariableCache->get($cacheIdentifier) !== false) {
            return $this->transientVariableCache->get($cacheIdentifier);
        }

        if ($maxDepth <= 0) {
            // exiting the recursion loop, may write to cache now
            $this->transientVariableCache->set($cacheIdentifier, $pageIds);
            return $pageIds;
        }

        // get the page ids of the current level and if needed call getPages recursive
        $pageIds = $this->getPageIdsFromCurrentDepthAndCallRecursive($maxDepth, $recursionRootPageId, $pageIds, $initialPagesAdditionalWhereClause);

        // exiting the recursion loop, may write to cache now
        $this->transientVariableCache->set($cacheIdentifier, $pageIds);
        return $pageIds;
    }

    /**
     * This method retrieves the pages ids from the current tree level an calls getPages recursive,
     * when the maxDepth has not been reached.
     *
     * @param int $maxDepth
     * @param int $recursionRootPageId
     * @param array $pageIds
     * @param string $initialPagesAdditionalWhereClause
     * @return array
     */
    protected function getPageIdsFromCurrentDepthAndCallRecursive(int $maxDepth, int $recursionRootPageId, array $pageIds, string $initialPagesAdditionalWhereClause = '')
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($recursionRootPageId, \PDO::PARAM_INT))
            );

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        if (!empty($initialPagesAdditionalWhereClause)) {
            $queryBuilder->andWhere($initialPagesAdditionalWhereClause);
        }

        $resultSet = $queryBuilder->execute();
        while ($page = $resultSet->fetch()) {
            $pageIds[] = $page['uid'];

            if ($maxDepth > 1) {
                $pageIds = array_merge($pageIds, $this->findAllSubPageIdsByRootPage($page['uid'], $maxDepth - 1));
            }
        }

        return $pageIds;
    }
  
    /**
     * Finds translation overlays by given page Id.
     *
     * @param int $pageId
     * @return array
     */
    public function findTranslationOverlaysByPageId(int $pageId) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('pid', 'l10n_parent', 'sys_language_uid')
            ->from('pages')
            ->add('where',
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT))
                . BackendUtility::BEenableFields('pages')
            )->execute()->fetchAll();

    }

    /**
     * Finds Pages, which are showing content from the page currently being updated.
     *
     * @param int $pageId UID of the page currently being updated
     * @return array with page Uids from pages, which are showing contents from given Page Id
     */
    public function findPageUidsWithContentsFromPid(int $pageId) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->add('where',
                $queryBuilder->expr()->eq('content_from_pid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT))
            );

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder->execute()->fetchAll();
    }

    /**
     * Finds all pages by given where clause
     *
     * @param string $whereClause
     * @return array
     */
    public function findAllMountPagesByWhereClause(string $whereClause) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select(
                'uid',
                'mount_pid AS mountPageSource',
                'uid AS mountPageDestination',
                'mount_pid_ol AS mountPageOverlayed')
            ->from($this->table)
            ->add('where', $whereClause);

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder->execute()->fetchAll();
    }

    /**
     * Limits the pages to the sys_language_uid = 0 (default language)
     *
     * @param $queryBuilder
     */
    protected function addDefaultLanguageUidConstraint($queryBuilder)
    {
        $queryBuilder->andWhere($queryBuilder->expr()->eq('sys_language_uid', 0));
    }
}
