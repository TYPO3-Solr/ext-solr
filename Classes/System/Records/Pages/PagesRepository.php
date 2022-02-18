<?php

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

namespace ApacheSolrForTypo3\Solr\System\Records\Pages;

use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use PDO;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
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
        $this->transientVariableCache = $transientVariableCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'runtime');
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
     * Attentions:
     * * Includes all page types except deleted pages!
     *
     * @param int $rootPageId Page ID from where to start collection sub pages
     * @param string $initialPagesAdditionalWhereClause
     * @return array Array of pages (IDs) in this site
     * @throws DBALDriverException
     */
    public function findAllSubPageIdsByRootPage(
        int $rootPageId,
        string $initialPagesAdditionalWhereClause = ''
    ) : array
    {

        $cacheIdentifier = sha1('getPages' . (string)$rootPageId . $initialPagesAdditionalWhereClause);
        if ($this->transientVariableCache->get($cacheIdentifier) !== false) {
            return $this->transientVariableCache->get($cacheIdentifier);
        }

        $pageIdsList = $this->getTreeList($rootPageId, 9999, 0, 'deleted = 0');
        $pageIds = GeneralUtility::intExplode(',', $pageIdsList);

        if (!empty($initialPagesAdditionalWhereClause)) {
            $pageIds = $this->filterPageIdsByInitialPagesAdditionalWhereClause($pageIds, $initialPagesAdditionalWhereClause);
        }

        $this->transientVariableCache->set($cacheIdentifier, $pageIds);
        return $pageIds;
    }

    /**
     * This method retrieves the pages ids from the current tree level an calls getPages recursive,
     * when the maxDepth has not been reached.
     *
     * @param array $pageIds
     * @param string $initialPagesAdditionalWhereClause
     * @return array
     * @throws DBALDriverException
     */
    protected function filterPageIdsByInitialPagesAdditionalWhereClause(
        array $pageIds,
        string $initialPagesAdditionalWhereClause
    ): array {

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($pageIds, Connection::PARAM_INT_ARRAY)
                )
            );

        $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($initialPagesAdditionalWhereClause));

        return $queryBuilder->execute()->fetchFirstColumn();
    }

    /**
     * Finds all pages records in a site or given branch with no_search_sub_entries=1
     *
     * @param int $rootPageId
     *
     * @return array
     * @throws DBALDriverException
     * @deprecated since v11 and will be removed in v12. Use {@link findAllPagesWithinNoSearchSubEntriesMarkedPages()} instead.
     */
    public function findAllPagesWithinNoSearchSubEntriesMarkedPagesByRootPage(int $rootPageId): array
    {
        trigger_error(
            'Method ' . __METHOD__ . ' of class ' . __CLASS__ . ' is deprecated since v11 and will be removed in v12. Use PagesRepository::findAllPagesWithinNoSearchSubEntriesMarkedPages() instead.',
            E_USER_DEPRECATED
        );

        $wholePageTree = $this->findAllSubPageIdsByRootPage($rootPageId);

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        try {
            $noSearchSubEntriesEnabledPages = $queryBuilder
                ->select('uid')
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($wholePageTree, Connection::PARAM_INT_ARRAY)),
                    $queryBuilder->expr()->eq('no_search_sub_entries', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT))
                )->execute()->fetchAllAssociative();
        } catch (Throwable $e) {
            return [];
        }

        if (empty($noSearchSubEntriesEnabledPages)) {
            return [];
        }

        $pageIds = [];
        foreach ($noSearchSubEntriesEnabledPages as $page) {
            $pageIds = array_merge($pageIds, $this->findAllSubPageIdsByRootPage((int)$page['uid']));
        }

        return $pageIds;
    }

    /**
     * Finds all PIDs within no_search_sub_entries=1 marked pages in all sites.
     *
     * @return array
     */
    public function findAllPagesWithinNoSearchSubEntriesMarkedPages(): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $pageIds = [];
        try {
            $noSearchSubEntriesEnabledPagesStatement = $queryBuilder
                ->select('uid')
                ->from($this->table)
                ->where(
                    $queryBuilder->expr()->eq('no_search_sub_entries', $queryBuilder->createNamedParameter(1, PDO::PARAM_INT))
                )->execute();
            while (($pageRow = $noSearchSubEntriesEnabledPagesStatement->fetchAssociative()) !== false) {
                $pageIds = array_merge($pageIds, $this->findAllSubPageIdsByRootPage((int)$pageRow['uid']));
            }
        } catch (Throwable $e) {
            return [];
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
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, PDO::PARAM_INT))
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
                $queryBuilder->expr()->eq('content_from_pid', $queryBuilder->createNamedParameter($pageId, PDO::PARAM_INT))
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
     * Returns a specific page
     *
     * @param int $uid
     * @param string $fields
     * @param string $additionalWhereClause
     * @param bool $useDeleteClause Use the deleteClause to check if a record is deleted (default TRUE)
     * @return array|null
     */
    public function getPage(int $uid, string $fields = '*', string $additionalWhereClause = '', bool $useDeleteClause = true): ?array
    {
        if (!$uid > 0) {
            return null;
        }

        return BackendUtility::getRecord($this->table, $uid, $fields, $additionalWhereClause, $useDeleteClause);
    }

    /**
     * Returns an additional where clause considering the backend relevant pages enable fields
     *
     * Note: Currently just a wrapper for BEenableFields, but as this should only be used internally
     * we should switch to the DefaultRestrictionHandler
     *
     * @param string $table The table from which to return enableFields WHERE clause. Table name must have a 'ctrl' section in $GLOBALS['TCA'].
     */
    public function getBackendEnableFields(): string
    {
        return BackendUtility::BEenableFields($this->table);
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

    /**
     * Recursively fetch all descendants of a given page
     *
     * Copied from {@link \TYPO3\CMS\Core\Database\QueryGenerator::getTreeList}, since it is deprecated and will be removed in TYPO3 12.
     * See: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.0/Deprecation-92080-DeprecatedQueryGeneratorAndQueryView.html
     *
     * @param int $id uid of the page
     * @param int $depth
     * @param int $begin
     * @param string $permClause
     * @return string comma separated list of descendant pages
     * @throws DBALDriverException
     * @noinspection Duplicates
     */
    protected function getTreeList(int $id, int $depth = 999, int $begin = 0, string $permClause = ''): string
    {
        if ($id < 0) {
            $id = abs($id);
        }
        if ($begin === 0) {
            $theList = $id;
        } else {
            $theList = '';
        }
        if ($id && $depth > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
            $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $queryBuilder->select('uid')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('sys_language_uid', 0)
                )
                ->orderBy('uid');
            if ($permClause !== '') {
                $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($permClause));
            }
            $statement = $queryBuilder->execute();
            while ($row = $statement->fetchAssociative()) {
                if ($begin <= 0) {
                    $theList .= ',' . $row['uid'];
                }
                if ($depth > 1) {
                    $theSubList = $this->getTreeList($row['uid'], $depth - 1, $begin - 1, $permClause);
                    if (!empty($theList) && !empty($theSubList) && ($theSubList[0] !== ',')) {
                        $theList .= ',';
                    }
                    $theList .= $theSubList;
                }
            }
        }
        return $theList;
    }
}
