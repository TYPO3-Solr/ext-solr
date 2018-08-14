<?php

namespace ApacheSolrForTypo3\Solr\System\Language;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Driver\Statement;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class FrontendOverlayService
 * @package ApacheSolrForTypo3\Solr\System\Language
 */
class FrontendOverlayService {

    /**
     * @var TCAService
     */
    protected $tcaService = null;

    /**
     * @var TypoScriptFrontendController
     */
    protected $tsfe = null;

    /**
     * Relation constructor.
     * @param TCAService|null $tcaService
     * @param TypoScriptFrontendController|null $tsfe
     */
    public function __construct(TCAService $tcaService = null, TypoScriptFrontendController $tsfe = null)
    {
        $this->tcaService = $tcaService ?? GeneralUtility::makeInstance(TCAService::class);
        $this->tsfe = $tsfe ?? $GLOBALS['TSFE'];
    }

    /**
     * Return the translated record
     *
     * @param string $tableName
     * @param array $record
     * @return array
     */
    public function getOverlay($tableName, $record)
    {
        if ($tableName === 'pages') {
            return $this->tsfe->sys_page->getPageOverlay($record, $this->tsfe->sys_language_uid);
        }

        return $this->tsfe->sys_page->getRecordOverlay($tableName, $record, $this->tsfe->sys_language_uid);
    }

    /**
     * Returns the overlay table for a certain table.
     *
     * @param string $table
     * @param string $field
     * @return string
     */
    public function getOverlayTable(string $table, string $field) : string
    {
        // pages has a special overlay table constriction
        if ($this->tsfe->sys_language_uid > 0
            && $table === 'pages'
            && $this->tcaService->getHasConfigurationForField('pages_language_overlay', $field)
            && Util::getIsTYPO3VersionBelow9()) {
            return 'pages_language_overlay';
        }

        return $table;
    }

    /**
     * When the record has an overlay we retrieve the uid of the translated record,
     * to resolve the relations from the translation.
     *
     * @param string $table
     * @param string $field
     * @param int $uid
     * @return int
     */
    public function getUidOfOverlay($table, $field, $uid)
    {
        // when no language is set at all we do not need to overlay
        if (!isset($this->tsfe->sys_language_uid)) {
            return $uid;
        }
        // when no language is set we can return the passed recordUid
        if (!$this->tsfe->sys_language_uid > 0) {
            return $uid;
        }
        // when no TCA configured for pages_language_overlay's field, then use original record Uid
        // @todo this can be dropped when TYPO3 8 compatibility is dropped
        $translatedInPagesLanguageOverlayAndNoTCAPresent = Util::getIsTYPO3VersionBelow9() &&
            !$this->tcaService->getHasConfigurationForField('pages_language_overlay', $field);
        if ($table === 'pages' && $translatedInPagesLanguageOverlayAndNoTCAPresent) {
            return $uid;
        }

        $record = $this->getRecord($table, $uid);

        // when the overlay is not an array, we return the localRecordUid
        if (!is_array($record)) {
            return $uid;
        }

        $overlayUid = $this->getLocalRecordUidFromOverlay($table, $record);
        $uid = ($overlayUid !== 0) ? $overlayUid : $uid;
        return $uid;
    }

    /**
     * This method retrieves the _PAGES_OVERLAY_UID or _LOCALIZED_UID from the localized record.
     *
     * @param string $localTableName
     * @param array $originalRecord
     * @return int
     */
    protected function getLocalRecordUidFromOverlay($localTableName, $originalRecord)
    {
        $overlayRecord = $this->getOverlay($localTableName, $originalRecord);

        // when there is a _PAGES_OVERLAY_UID | _LOCALIZED_UID in the overlay, we return it
        if ($localTableName === 'pages' && isset($overlayRecord['_PAGES_OVERLAY_UID'])) {
            return (int)$overlayRecord['_PAGES_OVERLAY_UID'];
        } elseif (isset($overlayRecord['_LOCALIZED_UID'])) {
            return (int)$overlayRecord['_LOCALIZED_UID'];
        }

        return 0;
    }

    /**
     * @param $localTableName
     * @param $localRecordUid
     * @return mixed
     */
    protected function getRecord($localTableName, $localRecordUid)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($localTableName);

        $record = $queryBuilder->select('*')->from($localTableName)->where($queryBuilder->expr()->eq('uid', $localRecordUid))->execute()->fetch();
        return $record;
    }
}