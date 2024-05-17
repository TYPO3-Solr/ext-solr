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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
        protected readonly Context $context
    ) {}

    /**
     * Return the translated record
     */
    public function getOverlay(string $tableName, array $record): ?array
    {
        return GeneralUtility::makeInstance(PageRepository::class, $this->context)->getLanguageOverlay($tableName, $record);
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
        int $uid
    ): int {
        $contextsLanguageId = $this->context->getPropertyFromAspect('language', 'id');
        // when no language is set at all we do not need to overlay
        if ($contextsLanguageId === null) {
            return $uid;
        }
        // when no language is set we can return the passed recordUid
        if ($contextsLanguageId <= 0) {
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
     * This method retrieves the _LOCALIZED_UID from the localized record.
     */
    protected function getLocalRecordUidFromOverlay(string $localTableName, array $originalRecord): int
    {
        $overlayRecord = $this->getOverlay($localTableName, $originalRecord);
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
