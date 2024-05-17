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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetItem;

/**
 * Value object that represent an option of a numeric range facet.
 *
 * @property NumericRangeFacet $facet
 * @method NumericRangeFacet getFacet()
 */
class NumericRange extends AbstractRangeFacetItem
{
    public function __construct(
        NumericRangeFacet $facet,
        protected ?float $startRequested = null,
        protected ?float $endRequested = null,
        protected ?float $startInResponse = null,
        protected ?float $endInResponse = null,
        string|int $gap = '',
        int $documentCount = 0,
        ?array $rangeCounts = [],
        bool $selected = false,
    ) {
        $label = '';
        if ($this->startRequested !== null && $this->endRequested !== null) {
            $label = $this->getRangeString();
        }
        parent::__construct(
            $facet,
            $label,
            $documentCount,
            $selected,
            [],
            $rangeCounts,
            $gap
        );
    }

    protected function getRangeString(): string
    {
        return $this->startRequested . '-' . $this->endRequested;
    }

    /**
     * Retrieves the end date that was requested by the user for this facet.
     */
    public function getEndRequested(): ?float
    {
        return $this->endRequested;
    }

    /**
     * Retrieves the start date that was requested by the used for the facet.
     */
    public function getStartRequested(): ?float
    {
        return $this->startRequested;
    }

    /**
     * Retrieves the end date that was received from solr for this facet.
     */
    public function getEndInResponse(): ?float
    {
        return $this->endInResponse;
    }

    /**
     * Retrieves the start date that was received from solr for this facet.
     */
    public function getStartInResponse(): ?float
    {
        return $this->startInResponse;
    }
}
