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

namespace ApacheSolrForTypo3\Solr\System\Language;

use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class FrontendOverlayService
 */
class FrontendOverlayService
{
    /**
     * FrontendOverlayService constructor
     */
    public function __construct(
        protected readonly TCAService $tcaService,
        protected readonly TypoScriptFrontendController $tsfe,
    ) {}

    /**
     * Return the translated record
     *
     * @throws AspectNotFoundException
     */
    public function getOverlay(string $tableName, array $record): ?array
    {
        /** @var LanguageAspect $currentLanguageAspect */
        $currentLanguageAspect = $this->tsfe->getContext()->getAspect('language');
        //        if ($tableName === 'pages') {
        //            return $this->tsfe->sys_page->getPageOverlay($record, $currentLanguageAspect);
        //        }

        return $this->tsfe->sys_page->getLanguageOverlay($tableName, $record, $currentLanguageAspect);
    }

    /**
     * When the record has an overlay we retrieve the uid of the translated record,
     * to resolve the relations from the translation.
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     */
    public function getUidOfOverlay(
        string $table,
        string $field,
        int $uid,
    ): int {
        $contextsLanguageId = $this->tsfe->getContext()->getPropertyFromAspect('language', 'id');
        // when no language is set at all we do not need to overlay
        if ($contextsLanguageId === null) {
            return $uid;
        }
        // when no language is set we can return the passed recordUid
        if (!($contextsLanguageId > 0)) {
            return $uid;
        }

        $record = $this->getRecord($table, $uid);

        // when the overlay is not an array, we return the localRecordUid
        if (!is_array($record)) {
            return $uid;
        }

        $overlayUid = $this->getLocalRecordUidFromOverlay($table, $record);
        return ($overlayUid !== 0) ? $overlayUid : $uid;
    }

    /**
     * This method retrieves the _PAGES_OVERLAY_UID or _LOCALIZED_UID from the localized record.
     *
     * @throws AspectNotFoundException
     */
    protected function getLocalRecordUidFromOverlay(string $localTableName, array $originalRecord): int
    {
        $overlayRecord = $this->getOverlay($localTableName, $originalRecord);

        // when there is a _PAGES_OVERLAY_UID | _LOCALIZED_UID in the overlay, we return it
        if ($localTableName === 'pages' && isset($overlayRecord['_PAGES_OVERLAY_UID'])) {
            return (int)$overlayRecord['_PAGES_OVERLAY_UID'];
        }
        if (isset($overlayRecord['_LOCALIZED_UID'])) {
            return (int)$overlayRecord['_LOCALIZED_UID'];
        }

        return 0;
    }

    /**
     * Returns  the record from table by record uid.
     *
     * @return array<string,mixed>|false
     *
     * @throws DBALException
     */
    protected function getRecord(string $localTableName, int $localRecordUid): array|false
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($localTableName);

        return $queryBuilder
            ->select('*')
            ->from($localTableName)
            ->where($queryBuilder->expr()->eq('uid', $localRecordUid))
            ->executeQuery()
            ->fetchAssociative();
    }
}
