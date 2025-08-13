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

namespace ApacheSolrForTypo3\Solr\Event\Indexing;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;

final class AfterContentAccessGroupsResolvedEvent
{
    public function __construct(
        protected Item $item,
        protected array $contentAccessGroups,
        protected int $systemLanguageUid,
    ) {}

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getContentAccessGroups(): array
    {
        return $this->contentAccessGroups;
    }

    public function setContentAccessGroups(array $contentAccessGroups): void
    {
        $this->contentAccessGroups = $contentAccessGroups;
    }

    public function getSystemLanguageUid(): int
    {
        return $this->systemLanguageUid;
    }
}
