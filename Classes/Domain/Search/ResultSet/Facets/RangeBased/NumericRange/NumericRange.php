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
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class NumericRange extends AbstractRangeFacetItem
{
    /**
     * @var float|null
     */
    protected ?float $startRequested = null;

    /**
     * @var float|null
     */
    protected ?float $endRequested = null;

    /**
     * @var float|null
     */
    protected ?float $startInResponse = null;

    /**
     * @var float|null
     */
    protected ?float $endInResponse = null;

    /**
     * @param NumericRangeFacet $facet
     * @param float|null $startRequested
     * @param float|null $endRequested
     * @param float|null $startInResponse
     * @param float|null $endInResponse
     * @param string $gap
     * @param int $documentCount
     * @param array|null $rangeCounts
     * @param bool $selected
     */
    public function __construct(
        NumericRangeFacet $facet,
        ?float $startRequested = null,
        ?float $endRequested = null,
        ?float $startInResponse = null,
        ?float $endInResponse = null,
        string $gap = '',
        int $documentCount = 0,
        ?array $rangeCounts = [],
        bool $selected = false
    ) {
        $this->startInResponse = $startInResponse;
        $this->endInResponse = $endInResponse;
        $this->startRequested = $startRequested;
        $this->endRequested = $endRequested;
        $this->rangeCounts = $rangeCounts;
        $this->gap = $gap;

        $label = '';
        if ($startRequested !== null && $endRequested !== null) {
            $label = $this->getRangeString();
        }

        parent::__construct($facet, $label, $documentCount, $selected);
    }

    /**
     * @return string
     */
    protected function getRangeString(): string
    {
        return $this->startRequested . '-' . $this->endRequested;
    }

    /**
     * Retrieves the end date that was requested by the user for this facet.
     *
     * @return float|null
     */
    public function getEndRequested(): ?float
    {
        return $this->endRequested;
    }

    /**
     * Retrieves the start date that was requested by the used for the facet.
     *
     * @return float|null
     */
    public function getStartRequested(): ?float
    {
        return $this->startRequested;
    }

    /**
     * Retrieves the end date that was received from solr for this facet.
     *
     * @return float|null
     */
    public function getEndInResponse(): ?float
    {
        return $this->endInResponse;
    }

    /**
     * Retrieves the start date that was received from solr for this facet.
     *
     * @return float|null
     */
    public function getStartInResponse(): ?float
    {
        return $this->startInResponse;
    }
}
