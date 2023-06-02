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
use Psr\Http\Message\UriInterface;

/**
 * Event which is fired for the Page Indexer, to define what kind of URL should be submitted.
 *
 * Previously defined via $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier']
 * and the PageIndexerDataUrlModifier interface.
 */
class AfterFrontendPageUriForIndexingHasBeenGeneratedEvent
{
    public function __construct(
        protected readonly Item $item,
        protected UriInterface $pageIndexUri,
        protected readonly int $languageId,
        protected readonly string $mountPointParameter,
        protected readonly array $options
    ) {
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getPageIndexUri(): UriInterface
    {
        return $this->pageIndexUri;
    }

    public function setPageIndexUri(UriInterface $pageIndexUri): void
    {
        $this->pageIndexUri = $pageIndexUri;
    }

    public function getLanguageId(): int
    {
        return $this->languageId;
    }

    public function getMountPointParameter(): string
    {
        return $this->mountPointParameter;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
