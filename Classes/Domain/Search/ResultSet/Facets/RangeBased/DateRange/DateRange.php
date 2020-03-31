<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\AbstractRangeFacetItem;
use DateTime;

/**
 * Value object that represent an option of a options facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRange extends AbstractRangeFacetItem
{
    /**
     * @var DateTime
     */
    protected $startRequested;

    /**
     * @var DateTime
     */
    protected $endRequested;

    /**
     * @var DateTime
     */
    protected $startInResponse;

    /**
     * @var DateTime
     */
    protected $endInResponse;

    /**
     * @param DateRangeFacet $facet
     * @param DateTime|null $startRequested
     * @param DateTime|null $endRequested
     * @param DateTime|null $startInResponse
     * @param DateTime|null $endInResponse
     * @param string $gap
     * @param int $documentCount
     * @param array $rangeCounts
     * @param bool $selected
     */
    public function __construct(DateRangeFacet $facet, DateTime $startRequested = null, DateTime $endRequested = null, DateTime $startInResponse = null, DateTime $endInResponse = null, $gap = '', $documentCount = 0, $rangeCounts, $selected = false)
    {
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
    protected function getRangeString()
    {
        return $this->startRequested->format('Ymd') . '0000-' . $this->endRequested->format('Ymd') . '0000';
    }

    /**
     * Retrieves the end date that was requested by the user for this facet.
     *
     * @return \DateTime
     */
    public function getEndRequested()
    {
        return $this->endRequested;
    }

    /**
     * Retrieves the start date that was requested by the used for the facet.
     *
     * @return \DateTime
     */
    public function getStartRequested()
    {
        return $this->startRequested;
    }

    /**
     * Retrieves the end date that was received from solr for this facet.
     *
     * @return \DateTime
     */
    public function getEndInResponse()
    {
        return $this->endInResponse;
    }

    /**
     * Retrieves the start date that was received from solr for this facet.
     *
     * @return \DateTime
     */
    public function getStartInResponse()
    {
        return $this->startInResponse;
    }
}
