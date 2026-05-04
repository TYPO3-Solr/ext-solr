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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Singleton that bridges the middleware (inside sub-request) with the IndexingService (outside).
 *
 * During a sub-request, the middleware and event listeners write results here.
 * After the sub-request completes, IndexingService reads results from here.
 */
class IndexingResultCollector implements SingletonInterface
{
    /**
     * @var int[] Finalized user groups from findUserGroups action
     */
    private array $userGroups = [];

    /**
     * @var array Raw frontend groups collected from content elements during rendering
     */
    private array $frontendGroups = [];

    /**
     * @var bool Whether the findUserGroups detection phase is active
     */
    private bool $userGroupDetectionActive = false;

    public function reset(): void
    {
        $this->userGroups = [];
        $this->frontendGroups = [];
        $this->userGroupDetectionActive = false;
    }

    /**
     * @return int[]
     */
    public function getUserGroups(): array
    {
        return $this->userGroups;
    }

    public function addFrontendGroup(int|string $group): void
    {
        $this->frontendGroups[] = $group;
    }

    public function isUserGroupDetectionActive(): bool
    {
        return $this->userGroupDetectionActive;
    }

    public function setUserGroupDetectionActive(bool $active): void
    {
        $this->userGroupDetectionActive = $active;
    }

    /**
     * Finalizes collected frontend groups: deduplicates, sorts, and stores
     * as the definitive userGroups result. Called by SolrIndexingMiddleware
     * after page rendering in the findUserGroups flow.
     */
    public function finalizeUserGroups(): void
    {
        $groupsList = implode(',', $this->frontendGroups);
        $groups = GeneralUtility::intExplode(',', $groupsList, true);
        $groups = array_unique($groups);
        $groups = array_filter(
            array_values($groups),
            static fn(int $val): bool => ($val !== -1),
        );

        if (empty($groups)) {
            $groups = [0];
        }

        sort($groups, SORT_NUMERIC);
        $this->userGroups = array_reverse($groups);
    }
}
