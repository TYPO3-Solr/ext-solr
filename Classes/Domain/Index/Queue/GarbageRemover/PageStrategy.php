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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class PageStrategy
 */
class PageStrategy extends AbstractStrategy
{
    /**
     * Removes the garbage of a page record.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function removeGarbageOfByStrategy(string $table, int $uid): void
    {
        if ($table === 'tt_content') {
            $this->collectPageGarbageByContentChange($uid);
            return;
        }

        if ($table === 'pages') {
            $this->collectPageGarbageByPageChange($uid);
        }
    }

    /**
     * Deletes a page from Solr and updates the item in the index queue
     * The MountPagesUpdater takes care of mounted pages
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function deleteInSolrAndUpdateIndexQueue(string $table, int $uid): void
    {
        parent::deleteInSolrAndUpdateIndexQueue($table, $uid);

        if ($table === 'pages') {
            $mountPagesUpdater = GeneralUtility::makeInstance(MountPagesUpdater::class);
            $mountPagesUpdater->update($uid);
        }
    }

    /**
     * Determines the relevant page id for a content element update. Deletes the page from solr and requeues the
     * page for a reindex.
     *
     * @throws DBALException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function collectPageGarbageByContentChange(int $ttContentUid): void
    {
        $contentElement = BackendUtility::getRecord('tt_content', $ttContentUid, 'uid, pid', '', false);
        $this->deleteInSolrAndUpdateIndexQueue('pages', $contentElement['pid']);
    }

    /**
     * When a page was changed it is removed from the index and index queue.
     *
     * @throws DBALException
     */
    protected function collectPageGarbageByPageChange(int $uid): void
    {
        $page = BackendUtility::getRecord('pages', $uid, '*', '', false);
        if (!empty($page['l10n_parent']) && (int)($page['l10n_parent']) !== 0) {
            $this->deleteIndexDocuments('pages', (int)$page['l10n_parent'], (int)$page['sys_language_uid']);
        } else {
            $this->deleteInSolrAndRemoveFromIndexQueue('pages', $uid);
        }

        $this->collectMountPointGarbage($page);
    }

    protected function collectMountPointGarbage(?array $page): void
    {
        if ($page === null || (int)$page['doktype'] !== PageRepository::DOKTYPE_MOUNTPOINT) {
            return;
        }

        $mountPointUid = (GeneralUtility::makeInstance(TCAService::class))
            ->getTranslationOriginalUidIfTranslated('pages', $page, $page['uid']);

        $site = $this->siteRepository->getSiteByPageId($page['pid']);
        $itemUids = array_map(
            static function (array $item): int {
                return (int)$item['uid'];
            },
            $this->queueItemRepository->findAllIndexQueueItemsByRootPidAndMountIdentifier(
                $site->getRootPageId(),
                $page['mount_pid'] . '-' . $mountPointUid . '-' . $site->getRootPageId()
            )
        );

        if ($itemUids !== []) {
            if ($page['uid'] !== $mountPointUid) {
                $this->queueItemRepository->updateItemsChangedTime(time(), uids: $itemUids);
            } else {
                $this->queueItemRepository->deleteItems(uids: $itemUids);
            }
        }

        $this->deleteMountedPagesInAllSolrConnections($site, $page['mount_pid'] . '-' . $mountPointUid);
    }
}
