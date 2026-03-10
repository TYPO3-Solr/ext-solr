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

/**
 * Singleton that bridges the middleware (inside sub-request) with the IndexingService (outside).
 *
 * During a sub-request, the middleware and event listeners write results here.
 * After the sub-request completes, IndexingService reads results from here.
 */
class IndexingResultCollector implements SingletonInterface
{
    /** @var int[] User groups collected during findUserGroups action */
    private array $userGroups = [];

    /** @var string Page content captured during page rendering */
    private string $pageContent = '';

    /** @var array<string, mixed> Per-item indexing results (keyed by item uid) */
    private array $itemResults = [];

    /** @var bool Whether the last indexing operation succeeded overall */
    private bool $success = false;

    /** @var array Collected frontend groups from content elements */
    private array $frontendGroups = [];

    /** @var array Original TCA backup for UserGroupDetector */
    private ?array $originalTca = null;

    public function reset(): void
    {
        $this->userGroups = [];
        $this->pageContent = '';
        $this->itemResults = [];
        $this->success = false;
        $this->frontendGroups = [];
        $this->originalTca = null;
    }

    /**
     * @return int[]
     */
    public function getUserGroups(): array
    {
        return $this->userGroups;
    }

    /**
     * @param int[] $userGroups
     */
    public function setUserGroups(array $userGroups): void
    {
        $this->userGroups = $userGroups;
    }

    public function getPageContent(): string
    {
        return $this->pageContent;
    }

    public function setPageContent(string $pageContent): void
    {
        $this->pageContent = $pageContent;
    }

    public function getItemResults(): array
    {
        return $this->itemResults;
    }

    public function setItemResult(string $key, mixed $value): void
    {
        $this->itemResults[$key] = $value;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getFrontendGroups(): array
    {
        return $this->frontendGroups;
    }

    public function addFrontendGroup(int|string $group): void
    {
        $this->frontendGroups[] = $group;
    }

    public function setFrontendGroups(array $groups): void
    {
        $this->frontendGroups = $groups;
    }

    public function getOriginalTca(): ?array
    {
        return $this->originalTca;
    }

    public function setOriginalTca(?array $tca): void
    {
        $this->originalTca = $tca;
    }
}
