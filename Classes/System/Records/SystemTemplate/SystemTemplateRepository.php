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

namespace ApacheSolrForTypo3\Solr\System\Records\SystemTemplate;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DBALException;

/**
 * SystemTemplateRepository to encapsulate the database access for records used in solr.
 */
class SystemTemplateRepository extends AbstractRepository
{
    protected string $table = 'sys_template';

    /**
     * Finds a first closest page id with active template.
     * This method expects one startPageId, which must be inside the root line and does not check if it is one in the root line.
     *
     * @throws DBALException
     */
    public function findOneClosestPageIdWithActiveTemplateByRootLine(array $rootLine): ?int
    {
        if (empty($rootLine)) {
            return null;
        }

        [$rootLinePageIds, $rootLinePositions] = $this->extractPageIdsAndPositions($rootLine);
        $templateRecords = $this->getTemplateRecordsFromRootlinePages($rootLinePageIds);

        if (empty($templateRecords)) {
            return null;
        }

        $this->sortTemplateRecordsByPageRootlinePosition($templateRecords, $rootLinePositions);

        return isset($templateRecords[0]['pid']) ? (int)$templateRecords[0]['pid'] : null;
    }

    /**
     * @param array<int, array{uid: int}> $rootLine
     * @return array{0: list<int>, 1: array<int, int>}
     */
    protected function extractPageIdsAndPositions(array $rootLine): array
    {
        $rootLinePageIds = [0];
        $rootLinePositions = [];

        foreach ($rootLine as $position => $rootLineItem) {
            $pageId = (int)$rootLineItem['uid'];
            $rootLinePageIds[] = $pageId;
            $rootLinePositions[$pageId] = $position;
        }

        return [$rootLinePageIds, $rootLinePositions];
    }

    /**
     * Retrieves template records associated with the given page IDs from the rootline.
     *
     * @param list<int> $rootLinePageIds The page IDs from the rootline to search for templates on.
     * @return list<array{uid: int, pid: int}> A list of template records (uid, pid) found, or an empty list if none are found.
     * @throws DBALException
     */
    protected function getTemplateRecordsFromRootlinePages(array $rootLinePageIds): array
    {
        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder
            ->select('uid', 'pid')
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->in(
                    'pid',
                    $queryBuilder->createNamedParameter(
                        $rootLinePageIds,
                        ArrayParameterType::INTEGER
                    )
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();

        return $result ?: [];
    }

    /**
     * Sorts template records based on the rootline position of the page they belong to.
     *
     * @param list<array{uid: int, pid: int}> $templateRecords The template records to sort.
     * @param array<int, int> $pageRootlinePositions A map of page IDs to their rootline positions.
     */
    protected function sortTemplateRecordsByPageRootlinePosition(array &$templateRecords, array $pageRootlinePositions): void
    {
        usort($templateRecords, static fn(array $a, array $b): int => $pageRootlinePositions[$b['pid']] <=> $pageRootlinePositions[$a['pid']]);
    }
}
