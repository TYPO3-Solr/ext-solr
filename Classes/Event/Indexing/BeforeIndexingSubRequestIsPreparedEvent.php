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

use ApacheSolrForTypo3\Solr\IndexQueue\IndexingInstructions;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;

/**
 * Dispatched before an indexing sub-request is prepared.
 *
 * All sub-requests of an indexing run share one PHP process and one DI container, so shared
 * services must drop the state of the previous sub-request here to stay consistent.
 */
final class BeforeIndexingSubRequestIsPreparedEvent
{
    public function __construct(
        private readonly Item $item,
        private readonly int $language,
        private readonly IndexingInstructions $instructions,
    ) {}

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getLanguage(): int
    {
        return $this->language;
    }

    public function getInstructions(): IndexingInstructions
    {
        return $this->instructions;
    }
}
