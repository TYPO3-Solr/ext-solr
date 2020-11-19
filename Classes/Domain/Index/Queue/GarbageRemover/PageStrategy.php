<?php

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\GarbageRemover;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 - Timo Hund <timo.hund@dkd.de>
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

use TYPO3\CMS\Backend\Utility\BackendUtility;


/**
 * Class PageStrategy
 */
class PageStrategy extends AbstractStrategy {

    /**
     * @param string $table
     * @param int $uid
     * @return mixed
     */
    protected function removeGarbageOfByStrategy($table, $uid)
    {
        if ($table === 'tt_content') {
            $this->collectPageGarbageByContentChange($uid);
            return;
        }

        if ($table === 'pages') {
            $this->collectPageGarbageByPageChange($uid);
            return;
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
