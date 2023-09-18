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

namespace ApacheSolrForTypo3\Solr\Event\Variants;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * Event which is fired after the ID (string) for a variant was created.
 *
 * Previously used with $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId']
 * and the ApacheSolrForTypo3\Solr\Domain\Variants\IdModifier interface.
 */
final class AfterVariantIdWasBuiltEvent
{
    public function __construct(
        private string $variantId,
        private readonly string $systemHash,
        private readonly string $type,
        private readonly int $uid,
        private readonly array $itemRecord,
        private readonly Site $site,
        private readonly Document $document
    ) {}

    public function getVariantId(): string
    {
        return $this->variantId;
    }

    public function setVariantId(string $variantId): void
    {
        $this->variantId = $variantId;
    }

    public function getSystemHash(): string
    {
        return $this->systemHash;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getItemRecord(): array
    {
        return $this->itemRecord;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }
}
