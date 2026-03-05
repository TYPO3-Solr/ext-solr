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

/**
 * Immutable value object carrying indexing instructions for a sub-request.
 * Replaces the old PageIndexerRequest as the data container passed via request attributes.
 */
final readonly class IndexingInstructions
{
    public const ACTION_INDEX_PAGE = 'indexPage';
    public const ACTION_INDEX_RECORDS = 'indexRecords';
    public const ACTION_FIND_USER_GROUPS = 'findUserGroups';

    /**
     * @param Item[] $items The queue items to index
     * @param string $action One of the ACTION_* constants
     * @param int $language The sys_language_uid
     * @param int $userGroup The frontend user group (for access-restricted page indexing)
     * @param string $accessRootline The access rootline string for pages
     * @param array $parameters Additional parameters (e.g., loggingEnabled, pageUserGroup, overridePageUrl)
     */
    public function __construct(
        private array $items,
        private string $action,
        private int $language = 0,
        private int $userGroup = 0,
        private string $accessRootline = '',
        private array $parameters = [],
    ) {}

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getFirstItem(): ?Item
    {
        return $this->items[0] ?? null;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getLanguage(): int
    {
        return $this->language;
    }

    public function getUserGroup(): int
    {
        return $this->userGroup;
    }

    public function getAccessRootline(): string
    {
        return $this->accessRootline;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    public function isPageIndexing(): bool
    {
        return $this->action === self::ACTION_INDEX_PAGE;
    }

    public function isRecordIndexing(): bool
    {
        return $this->action === self::ACTION_INDEX_RECORDS;
    }

    public function isFindUserGroups(): bool
    {
        return $this->action === self::ACTION_FIND_USER_GROUPS;
    }
}
