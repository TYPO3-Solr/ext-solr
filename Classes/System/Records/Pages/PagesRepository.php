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

namespace ApacheSolrForTypo3\Solr\System\Records\Pages;

use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\ParameterType;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
    protected string $table = 'pages';

    protected TwoLevelCache $transientVariableCache;

    public function __construct(?TwoLevelCache $transientVariableCache = null)
    {
        $this->transientVariableCache = $transientVariableCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, 'runtime');
    }

    /**
     * Gets the site's root pages. The "Is root of website" flag must be set,
     * which usually is the case for pages with pid = 0.
     *
     * @return array{array{
     *    'uid': int,
     *    'title': string
     * }} An array of (partial) root page records, containing the uid and title fields
     *
     * @throws DBALException
     */
    public function findAllRootPages(): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $queryBuilder
            ->select('uid', 'title')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->neq('pid', -1),
                $queryBuilder->expr()->eq('is_siteroot', 1),
            );

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Finds the MountPointProperties array for mount points(destinations) by mounted page UID(source) or by the rootline array of mounted page.
     *
     * @param int $mountedPageUid
     * @param int[] $rootLineParentPageIds
     * @return array{array{
     *    'uid': int,
     *    'mountPageDestination': int,
     *    'mountPageSource': int,
     *    'mountPageOverlayed': int
     * }}
     * @throws DBALException
     * @throws InvalidArgumentException
     */
    public function findMountPointPropertiesByPageIdOrByRootLineParentPageIds(
        int $mountedPageUid,
        array $rootLineParentPageIds = [],
    ): array {
        if (array_filter($rootLineParentPageIds, 'is_int') !== $rootLineParentPageIds) {
            throw new InvalidArgumentException('Given $rootLineParentPageIds array is not valid. Allowed only the arrays with the root line page UIDs as integers.', 1502459711);
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->select('uid', 'uid AS mountPageDestination', 'mount_pid AS mountPageSource', 'mount_pid_ol AS mountPageOverlayed')->from($this->table);
        $queryBuilder = $this->addWhereClauseForMountpointDestinationProperties($queryBuilder, $mountedPageUid, $rootLineParentPageIds);
        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * This method builds the where clause for the mountpoint destinations. It retrieves all records where the mount_pid = $mountedPageUid or the mount_pid is
     * in the rootLineParentPageIds.
     *
     * @param int[] $rootLineParentPageIds
     */
    protected function addWhereClauseForMountpointDestinationProperties(
        QueryBuilder $queryBuilder,
        int $mountedPageUid,
        array $rootLineParentPageIds,
    ): QueryBuilder {
        if (empty($rootLineParentPageIds)) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', 7),
                $queryBuilder->expr()->eq('no_search', 0),
                $queryBuilder->expr()->eq('mount_pid', $mountedPageUid),
                $queryBuilder->expr()->eq('mount_pid_ol', 1),
            );
        } else {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('doktype', 7),
                $queryBuilder->expr()->eq('no_search', 0),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq('mount_pid', $mountedPageUid),
                        $queryBuilder->expr()->eq('mount_pid_ol', 1),
                    ),
                    $queryBuilder->expr()->in('mount_pid', $rootLineParentPageIds),
                ),
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
     * @param int $rootPageId Page ID from where to start collection sub-pages
     * @return int[] Array of pages (IDs) in this site
     *
     * @throws DBALException
     */
    public function findAllSubPageIdsByRootPage(
        int $rootPageId,
        string $initialPagesAdditionalWhereClause = '',
    ): array {
        $cacheIdentifier = hash('sha1', 'getPages' . $rootPageId . $initialPagesAdditionalWhereClause);
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
     * This method retrieves the pages ids from the current tree level a calls getPages recursive,
     * when the maxDepth has not been reached.
     *
     * @param int[] $pageIds
     * @param string $initialPagesAdditionalWhereClause
     * @return int[]
     * @throws DBALException
     */
    protected function filterPageIdsByInitialPagesAdditionalWhereClause(
        array $pageIds,
        string $initialPagesAdditionalWhereClause,
    ): array {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter($pageIds, ArrayParameterType::INTEGER),
                ),
            );

        $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($initialPagesAdditionalWhereClause));

        return $queryBuilder->executeQuery()->fetchFirstColumn();
    }

    /**
     * Finds all PIDs within no_search_sub_entries=1 marked pages in all sites.
     *
     * @return int[]
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
                    $queryBuilder->expr()->eq('no_search_sub_entries', $queryBuilder->createNamedParameter(1, ParameterType::INTEGER)),
                )->executeQuery();
            while (($pageRow = $noSearchSubEntriesEnabledPagesStatement->fetchAssociative()) !== false) {
                $pageIds = array_merge($pageIds, $this->findAllSubPageIdsByRootPage((int)$pageRow['uid']));
            }
        } catch (Throwable) {
            return [];
        }
        return $pageIds;
    }

    /**
     * Finds translation overlays by given page Id.
     *
     * @return array<int, array{
     *    'pid': int,
     *    'l10n_parent': int,
     *    'sys_language_uid': int,
     * }>
     *
     * @throws DBALException
     */
    public function findTranslationOverlaysByPageId(int $pageId): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('pid', 'l10n_parent', 'sys_language_uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER))
                . BackendUtility::BEenableFields('pages'),
            )->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Finds Pages, which are showing content from the page currently being updated.
     *
     * @param int $pageId UID of the page currently being updated
     * @return array{array{
     *    'uid': int
     * }} with page Uids from pages, which are showing contents from given Page Id
     *
     * @throws DBALException
     */
    public function findPageUidsWithContentsFromPid(int $pageId): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $queryBuilder
            ->select('uid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->eq('content_from_pid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)),
            );

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Finds all pages by given where clause
     *
     * @return array{array{
     *    'uid': int,
     *    'mountPageSource': int,
     *    'mountPageDestination': int,
     *    'mountPageOverlayed': int
     * }}
     *
     * @throws DBALException
     */
    public function findAllMountPagesByWhereClause(string $whereClause): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select(
                'uid',
                'mount_pid AS mountPageSource',
                'uid AS mountPageDestination',
                'mount_pid_ol AS mountPageOverlayed',
            )
            ->from($this->table)
            ->where(
                QueryHelper::stripLogicalOperatorPrefix($whereClause),
            );

        $this->addDefaultLanguageUidConstraint($queryBuilder);

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns a specific page
     *
     * @return array{
     *    'uid': int,
     *    'pid': int
     * }|null
     */
    public function getPage(
        int $uid,
        string $fields = '*',
        string $additionalWhereClause = '',
        bool $useDeleteClause = true,
    ): ?array {
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
     */
    public function getBackendEnableFields(): string
    {
        return BackendUtility::BEenableFields($this->table);
    }

    /**
     * Limits the pages to the sys_language_uid = 0 (default language)
     */
    protected function addDefaultLanguageUidConstraint(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->andWhere($queryBuilder->expr()->eq('sys_language_uid', 0));
    }

    /**
     * Recursively fetch all descendants of a given page and return them as comma separated list
     *
     * Copied from {@link \TYPO3\CMS\Core\Database\QueryGenerator::getTreeList}, since it is deprecated and was removed in TYPO3 12.
     * See: https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.0/Deprecation-92080-DeprecatedQueryGeneratorAndQueryView.html
     *
     * @throws DBALException
     */
    public function getTreeList(int $id, int $depth = 999, int $begin = 0, string $permClause = ''): string
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
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($id, ParameterType::INTEGER)),
                    $queryBuilder->expr()->eq('sys_language_uid', 0),
                )
                ->orderBy('uid');
            if ($permClause !== '') {
                $queryBuilder->andWhere(QueryHelper::stripLogicalOperatorPrefix($permClause));
            }
            $statement = $queryBuilder->executeQuery();
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
        return (string)$theList;
    }
}
