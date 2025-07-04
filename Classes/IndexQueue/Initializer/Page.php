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

namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception as DBALException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue initializer for pages which also covers resolution of mount
 * pages.
 */
class Page extends AbstractInitializer
{
    /**
     * The type of items this initializer is handling.
     */
    protected string $type = 'pages';

    /**
     * Overrides the general setType() implementation, forcing type to "pages".
     *
     * @param string $type Type to initialize (ignored).
     */
    public function setType(string $type): void {}

    /**
     * Initializes Index Queue page items for a site. Includes regular pages
     * and mounted pages - no nested mount page structures though.
     *
     * @throws ConnectionException
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function initialize(): bool
    {
        $pagesInitialized = parent::initialize();
        $mountPagesInitialized = $this->initializeMountPointPages();

        return $pagesInitialized && $mountPagesInitialized;
    }

    /**
     * Initialize a single page that is part of a mounted tree.
     *
     * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
     * @param int $mountedPageId The ID of the mounted page
     *
     * @throws DBALException
     */
    public function initializeMountedPage(array $mountProperties, int $mountedPageId): void
    {
        $mountedPages = [$mountedPageId];

        $this->addMountedPagesToIndexQueue($mountedPages, $mountProperties);
        $this->addIndexQueueItemIndexingProperties($mountProperties, $mountedPages);
    }

    /**
     * Initializes the pages of a single mount point
     *
     * @param int $mountPointId Uid of mount point (doktype = 7) to initialize
     */
    public function initializeMountPoint(int $mountPointId)
    {
        return $this->initializeMountPointPages($mountPointId);
    }

    /**
     * Initializes Mount Point(pages) to be indexed through the Index Queue. The Mount
     * Points are searched and their mounted virtual sub-trees are then resolved
     * and added to the Index Queue as if they were actually present below the
     * Mount Point.
     *
     * @return bool TRUE if initialization of the Mount Pages was successful, FALSE otherwise
     *
     * @throws ConnectionException
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function initializeMountPointPages(?int $restrictToMountPoint = null): bool
    {
        $mountPointsInitialized = false;
        $mountPoints = $this->pagesRepository->findAllMountPagesByWhereClause(
            $this->buildPagesClause()
            . $this->buildTcaWhereClause()
            . ' AND doktype = 7 AND no_search = 0'
            . ($restrictToMountPoint !== null ? ' AND uid=' . $restrictToMountPoint : ''),
        );

        if (empty($mountPoints)) {
            return true;
        }

        $databaseConnection = $this->queueItemRepository->getConnectionForAllInTransactionInvolvedTables(
            'tx_solr_indexqueue_item',
            'tx_solr_indexqueue_indexing_property',
        );

        foreach ($mountPoints as $mountPoint) {
            if (!$this->isMountPointValid($mountPoint)) {
                continue;
            }

            $mountedPages = $this->resolveMountPageTree($mountPoint);

            // handling mount_pid_ol behavior
            if (!$mountPoint['mountPageOverlayed']) {
                // Add page like a regular page, as only the sub-tree is mounted.
                // The page itself has its own content, which is handled like standard page.
                $indexQueue = GeneralUtility::makeInstance(Queue::class);
                $indexQueue->updateItem($this->type, $mountPoint['uid']);
                $mountPointsInitialized = true;
            }

            // This can happen when the mount point does not show the content of the
            // mounted page and the mounted page does not have any subpages.
            if (empty($mountedPages)) {
                continue;
            }

            $databaseConnection->beginTransaction();
            try {
                $this->addMountedPagesToIndexQueue($mountedPages, $mountPoint);
                $this->addIndexQueueItemIndexingProperties($mountPoint, $mountedPages);

                $databaseConnection->commit();
                $mountPointsInitialized = true;
            } catch (Throwable $e) {
                $databaseConnection->rollBack();

                $this->logger->error(
                    'Index Queue initialization failed for mount pages',
                    [
                        $e->__toString(),
                    ],
                );
                break;
            }
        }

        return $mountPointsInitialized;
    }

    /**
     * Checks whether a Mount Point page is properly configured.
     */
    protected function isMountPointValid(array $mountPoint): bool
    {
        $isValidMountPage = true;

        if (!empty($mountPoint['mountPageOverlayed']) && empty($mountPoint['mountPageSource'])) {
            $isValidMountPage = false;

            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                'Property "Mounted page" must not be empty. Invalid Mount Page configuration for page ID ' . $mountPoint['uid'] . '.',
                'Failed to initialize Mount Page tree. ',
                ContextualFeedbackSeverity::ERROR,
            );
            // @extensionScannerIgnoreLine
            $this->flashMessageQueue->addMessage($flashMessage);
        }

        if (!empty($mountPoint['mountPageOverlayed']) && !$this->mountedPageExists($mountPoint['mountPageSource'])) {
            $isValidMountPage = false;

            $flashMessage = GeneralUtility::makeInstance(
                FlashMessage::class,
                'The mounted page must be accessible in the frontend. '
                . 'Invalid Mount Page configuration for page ID '
                . $mountPoint['uid'] . ', the mounted page with ID '
                . $mountPoint['mountPageSource']
                . ' is not accessible in the frontend.',
                'Failed to initialize Mount Page tree. ',
                ContextualFeedbackSeverity::ERROR,
            );
            // @extensionScannerIgnoreLine
            $this->flashMessageQueue->addMessage($flashMessage);
        }

        return $isValidMountPage;
    }

    /**
     * Checks whether the mounted page (mount page source) exists. That is,
     * whether it is accessible in the frontend. So the record must exist
     * (deleted = 0) and must not be hidden (hidden = 0).
     */
    protected function mountedPageExists(int $mountedPageId): bool
    {
        $mountedPageExists = false;

        $mountedPage = BackendUtility::getRecord('pages', $mountedPageId, 'uid', ' AND hidden = 0');
        if (!empty($mountedPage)) {
            $mountedPageExists = true;
        }

        return $mountedPageExists;
    }

    /**
     * Adds the virtual / mounted pages to the Index Queue as if they would
     * belong to the same site where they are mounted.
     *
     * @param array $mountedPages An array of mounted page IDs
     * @param array $mountProperties Array with mount point properties (mountPageSource, mountPageDestination, mountPageOverlayed)
     *
     * @throws DBALException
     */
    protected function addMountedPagesToIndexQueue(array $mountedPages, array $mountProperties): void
    {
        $mountPointIdentifier = $this->getMountPointIdentifier($mountProperties);
        $mountPointPageIsWithExistingQueueEntry = $this->queueItemRepository->findPageIdsOfExistingMountPagesByMountIdentifier($mountPointIdentifier);

        // update existing queue entries to trigger reindexing
        array_walk(
            $mountPointPageIsWithExistingQueueEntry,
            function (int $pageUid): void {
                $tstamp = GeneralUtility::makeInstance(Context::class)
                    ->getPropertyFromAspect('date', 'timestamp') ?? time();
                $items = $this->queueItemRepository->findItemsByItemTypeAndItemUid('pages', $pageUid);
                foreach ($items as $item) {
                    $this->queueItemRepository->updateChangedTimeByItem($item, $tstamp);
                }
            },
        );

        // add new items if necessary
        $mountedPagesThatNeedToBeAdded = array_diff($mountedPages, $mountPointPageIsWithExistingQueueEntry);
        if (count($mountedPagesThatNeedToBeAdded) === 0) {
            //nothing to do
            return;
        }

        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');

        $mountIdentifier = $this->getMountPointIdentifier($mountProperties);
        $initializationQuery = 'INSERT INTO tx_solr_indexqueue_item (root, item_type, item_uid, indexing_configuration, indexing_priority, changed, has_indexing_properties, pages_mountidentifier, errors) '
            . $this->buildSelectStatement() . ', 1, ' . $connection->quote($mountIdentifier) . ',""'
            . 'FROM pages '
            . 'WHERE '
            . 'uid IN(' . implode(',', $mountedPagesThatNeedToBeAdded) . ') '
            . $this->buildTcaWhereClause()
            . $this->buildUserWhereClause();
        $logData = ['query' => $initializationQuery];

        try {
            $logData['rows'] = $this->queueItemRepository->initializeByNativeSQLStatement($initializationQuery);
        } catch (DBALException $DBALException) {
            $logData['error'] = $DBALException->getCode() . ': ' . $DBALException->getMessage();
        }

        $this->logInitialization($logData);
    }

    /**
     * Adds Index Queue item indexing properties for mounted pages. The page
     * indexer later needs to know that he's dealing with a mounted page, the
     * indexing properties will let make it possible for the indexer to
     * distinguish the mounted pages.
     *
     * @param array $mountPage An array with information about the root/destination Mount Page
     * @param array $mountedPages An array of mounted page IDs
     *
     * @throws DBALException
     */
    protected function addIndexQueueItemIndexingProperties(array $mountPage, array $mountedPages): void
    {
        $mountIdentifier = $this->getMountPointIdentifier($mountPage);
        $mountPageItems = $this->queueItemRepository->findAllIndexQueueItemsByRootPidAndMountIdentifierAndMountedPids($this->site->getRootPageId(), $mountIdentifier, $mountedPages);

        foreach ($mountPageItems as $mountPageItemRecord) {
            /** @var Item $mountPageItem */
            $mountPageItem = GeneralUtility::makeInstance(Item::class, $mountPageItemRecord);
            $mountPageItem->setIndexingProperty('mountPageSource', $mountPage['mountPageSource']);
            $mountPageItem->setIndexingProperty('mountPageDestination', $mountPage['mountPageDestination']);
            $mountPageItem->setIndexingProperty('isMountedPage', '1');

            $mountPageItem->storeIndexingProperties();
        }
    }

    /**
     * Builds an identifier of the given mount point properties.
     *
     * @param array $mountProperties Array with mount point properties (mountPageSource, mountPageDestination, mountPageOverlayed)
     * @return string String consisting of mountPageSource-mountPageDestination-mountPageOverlayed
     */
    protected function getMountPointIdentifier(array $mountProperties): string
    {
        return $mountProperties['mountPageSource']
        . '-' . $mountProperties['mountPageDestination']
        . '-' . $mountProperties['mountPageOverlayed'];
    }

    // Mount Page resolution

    /**
     * Gets all the pages from a mounted page tree.
     *
     * @throws DBALException
     */
    protected function resolveMountPageTree(array $mountPage): array
    {
        $mountPageSourceId = (int)$mountPage['mountPageSource'];

        $mountPageTree = $this->site->getPages(
            $mountPageSourceId,
            'pages',
            $this->site->getSolrConfiguration()->getValueByPathOrDefaultValue(
                'plugin.tx_solr.index.queue.pages.additionalWhereClause',
                '',
            ),
        );

        // Do not include $mountPageSourceId in tree, if the mount point is not set to overlay.
        if (!empty($mountPageTree) && !$mountPage['mountPageOverlayed']) {
            $mountPageTree = array_diff($mountPageTree, [$mountPageSourceId]);
        }

        return $mountPageTree;
    }
}
