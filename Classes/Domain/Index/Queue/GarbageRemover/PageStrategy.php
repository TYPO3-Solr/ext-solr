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

use TYPO3\CMS\Backend\Utility\BackendUtility;


/**
 * Class PageStrategy
 */
class PageStrategy extends AbstractStrategy {

    /**
     * @param string $table
     * @param int $uid
     */
    protected function removeGarbageOfByStrategy($table, $uid)
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
     * Determines the relevant page id for an content element update. Deletes the page from solr and requeues the
     * page for a reindex.
     *
     * @param int $ttContentUid
     */
    protected function collectPageGarbageByContentChange($ttContentUid)
    {
        $contentElement = BackendUtility::getRecord('tt_content', $ttContentUid, 'uid, pid', '', false);
        $this->deleteInSolrAndUpdateIndexQueue('pages', $contentElement['pid']);
    }

    /**
     * When a page was changed it is removed from the index and index queue.
     *
     * @param int $uid
     */
    protected function collectPageGarbageByPageChange($uid)
    {
        $pageOverlay = BackendUtility::getRecord('pages', $uid, 'l10n_parent, sys_language_uid', '', false);
        if (!empty($pageOverlay['l10n_parent']) && intval($pageOverlay['l10n_parent']) !== 0) {
            $this->deleteIndexDocuments('pages', (int)$pageOverlay['l10n_parent'], (int)$pageOverlay['sys_language_uid']);
        } else {
            $this->deleteInSolrAndRemoveFromIndexQueue('pages', $uid);
        }
    }
}
