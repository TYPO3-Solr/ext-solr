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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * Solr document class that should be used in the frontend in the search context.
 */
class SearchResult extends Document
{
    /**
     * The variant field value
     *
     * Value of Solr collapse field, which is defined via
     * TypoScript variable "variants.variantField"
     */
    protected string $variantFieldValue = '';

    /**
     * Number of variants found
     *
     * May differ from documents in variants as
     * returned variants are limited by `expand.rows`
     */
    protected int $variantsNumFound = 0;

    /**
     * @var SearchResult[]
     */
    protected array $variants = [];

    /**
     * Indicates if an instance of this document is a variant (a sub document of another).
     */
    protected bool $isVariant = false;

    /**
     * References the parent document of the document is a variant.
     */
    protected ?SearchResult $variantParent = null;

    /**
     * The group item if available
     */
    protected ?GroupItem $groupItem = null;

    /**
     * Returns the group item if available
     */
    public function getGroupItem(): ?GroupItem
    {
        return $this->groupItem;
    }

    public function getHasGroupItem(): bool
    {
        return $this->groupItem !== null;
    }

    public function setGroupItem(GroupItem $group): void
    {
        $this->groupItem = $group;
    }

    public function getVariantFieldValue(): string
    {
        return $this->variantFieldValue;
    }

    public function setVariantFieldValue(string $variantFieldValue): void
    {
        $this->variantFieldValue = $variantFieldValue;
    }

    public function getVariantsNumFound(): int
    {
        return $this->variantsNumFound;
    }

    public function setVariantsNumFound(int $numFound): void
    {
        $this->variantsNumFound = $numFound;
    }

    /**
     * @return SearchResult[]
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function addVariant(SearchResult $expandedResult): void
    {
        $this->variants[] = $expandedResult;
    }

    public function getIsVariant(): bool
    {
        return $this->isVariant;
    }

    public function setIsVariant(bool $isVariant = true): void
    {
        $this->isVariant = $isVariant;
    }

    public function getVariantParent(): ?SearchResult
    {
        return $this->variantParent;
    }

    public function setVariantParent(SearchResult $variantParent): void
    {
        $this->variantParent = $variantParent;
    }

    public function getContent(): string
    {
        return $this->fields['content'] ?? '';
    }

    public function getIsElevated(): bool
    {
        return $this->fields['isElevated'] ?? false;
    }

    public function getType(): string
    {
        return $this->fields['type'] ?? '';
    }

    /**
     * Note: The id field on Apache Solr document is a string.
     */
    public function getId(): string
    {
        return $this->fields['id'] ?? '';
    }

    public function getScore(): float
    {
        return (float)($this->fields['score'] ?? 0.0);
    }

    public function getVectorSimilarityScore(): float
    {
        return (float)($this->fields['$q_vector'] ?? 0.0);
    }

    public function getUrl(): string
    {
        return $this->fields['url'] ?? '';
    }

    public function getTitle(): string
    {
        return $this->fields['title'] ?? '';
    }
}
