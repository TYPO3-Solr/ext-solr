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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItem;

/**
 * Abstract class that is used as base class for range facet items
 */
abstract class AbstractRangeFacetItem extends AbstractFacetItem
{
    public function __construct(
        protected AbstractFacet $facet,
        protected string $label = '',
        protected int $documentCount = 0,
        protected bool $selected = false,
        protected array $metrics = [],
        protected array $rangeCounts = [],
        protected string|int $gap = '',
    ) {
        parent::__construct(
            $this->facet,
            $this->label,
            $this->documentCount,
            $this->selected,
            $this->metrics,
        );
    }

    public function getUriValue(): string
    {
        return $this->getRangeString();
    }

    public function getCollectionKey(): string
    {
        return $this->getRangeString();
    }

    public function getRangeCounts(): array
    {
        return $this->rangeCounts;
    }

    public function getGap(): string
    {
        return (string)$this->gap;
    }

    abstract protected function getRangeString(): string;
}
