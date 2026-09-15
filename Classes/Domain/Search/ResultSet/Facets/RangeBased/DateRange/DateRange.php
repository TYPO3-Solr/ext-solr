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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetItem;
use DateTime;

/**
 * Value object that represent an option of options facet.
 *
 * @property DateRangeFacet $facet
 * @method DateRangeFacet getFacet()
 */
class DateRange extends AbstractRangeFacetItem
{
    public function __construct(
        DateRangeFacet $facet,
        protected ?DateTime $startRequested = null,
        protected ?DateTime $endRequested = null,
        protected ?DateTime $startInResponse = null,
        protected ?DateTime $endInResponse = null,
        string|int $gap = '',
        int $documentCount = 0,
        array $rangeCounts = [],
        bool $selected = false,
    ) {
        $label = '';
        if ($this->startRequested instanceof DateTime && $this->endRequested instanceof DateTime) {
            $label = $this->getRangeString();
        }

        parent::__construct(
            $facet,
            $label,
            $documentCount,
            $selected,
            [],
            $rangeCounts,
            $gap,
        );
    }

    protected function getRangeString(): string
    {
        $from = $this->startRequested === null ? '' : $this->startRequested->format('Ymd') . '0000';
        $till = $this->endRequested === null ? '' : $this->endRequested->format('Ymd') . '2359';
        return $from . '-' . $till;
    }

    /**
     * Retrieves the end date that was requested by the user for this facet.
     */
    public function getEndRequested(): ?DateTime
    {
        return $this->endRequested;
    }

    /**
     * Retrieves the start date that was requested by the used for the facet.
     */
    public function getStartRequested(): ?DateTime
    {
        return $this->startRequested;
    }

    /**
     * Retrieves the end date that was received from solr for this facet.
     */
    public function getEndInResponse(): ?DateTime
    {
        return $this->endInResponse;
    }

    /**
     * Retrieves the start date that was received from solr for this facet.
     */
    public function getStartInResponse(): ?DateTime
    {
        return $this->startInResponse;
    }
}
