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
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRange extends AbstractRangeFacetItem
{
    /**
     * @var DateTime|null
     */
    protected ?DateTime $startRequested = null;

    /**
     * @var DateTime|null
     */
    protected ?DateTime $endRequested = null;

    /**
     * @var DateTime|null
     */
    protected ?DateTime $startInResponse = null;

    /**
     * @var DateTime|null
     */
    protected ?DateTime $endInResponse = null;

    /**
     * @param DateRangeFacet $facet
     * @param DateTime|null $startRequested
     * @param DateTime|null $endRequested
     * @param DateTime|null $startInResponse
     * @param DateTime|null $endInResponse
     * @param string|null $gap
     * @param int $documentCount
     * @param array $rangeCounts
     * @param bool $selected
     */
    public function __construct(
        DateRangeFacet $facet,
        DateTime $startRequested = null,
        DateTime $endRequested = null,
        DateTime $startInResponse = null,
        DateTime $endInResponse = null,
        string $gap = '',
        int $documentCount = 0,
        array $rangeCounts = [],
        bool $selected = false
    ) {
        $this->startInResponse = $startInResponse;
        $this->endInResponse = $endInResponse;
        $this->startRequested = $startRequested;
        $this->endRequested = $endRequested;
        $this->rangeCounts = $rangeCounts;
        $this->gap = $gap;

        $label = '';
        if ($startRequested instanceof DateTime && $endRequested instanceof DateTime) {
            $label = $this->getRangeString();
        }

        parent::__construct($facet, $label, $documentCount, $selected);
    }

    /**
     * @return string
     */
    protected function getRangeString(): string
    {
        $from = null === $this->startRequested ? '' : $this->startRequested->format('Ymd') . '0000';
        $till = null === $this->endRequested ? '' : $this->endRequested->format('Ymd') . '0000';
        return $from . '-' . $till;
    }

    /**
     * Retrieves the end date that was requested by the user for this facet.
     *
     * @return DateTime|null
     */
    public function getEndRequested(): ?DateTime
    {
        return $this->endRequested;
    }

    /**
     * Retrieves the start date that was requested by the used for the facet.
     *
     * @return DateTime|null
     */
    public function getStartRequested(): ?DateTime
    {
        return $this->startRequested;
    }

    /**
     * Retrieves the end date that was received from solr for this facet.
     *
     * @return DateTime|null
     */
    public function getEndInResponse(): ?DateTime
    {
        return $this->endInResponse;
    }

    /**
     * Retrieves the start date that was received from solr for this facet.
     *
     * @return DateTime|null
     */
    public function getStartInResponse(): ?DateTime
    {
        return $this->startInResponse;
    }
}
