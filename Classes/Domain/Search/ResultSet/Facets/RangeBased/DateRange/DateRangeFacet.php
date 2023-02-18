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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;

/**
 * Value object that represent a date range facet.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRangeFacet extends AbstractFacet
{
    public const TYPE_DATE_RANGE = 'dateRange';

    /**
     * String
     * @var string
     */
    protected static string $type = self::TYPE_DATE_RANGE;

    /**
     * @var DateRange|null
     */
    protected ?DateRange $range = null;

    /**
     * @param DateRange $range
     */
    public function setRange(DateRange $range)
    {
        $this->range = $range;
    }

    /**
     * @return DateRange|null
     */
    public function getRange(): ?DateRange
    {
        return $this->range;
    }

    /**
     * Get facet partial name used for rendering the facet
     *
     * @return string
     */
    public function getPartialName(): string
    {
        return !empty($this->configuration['partialName']) ? $this->configuration['partialName'] : 'RangeDate.html';
    }

    /**
     * Since the DateRange contains only one or two items when return a collection with the range only to
     * allow to render the date range as other facet items.
     *
     * @return AbstractFacetItemCollection
     */
    public function getAllFacetItems(): AbstractFacetItemCollection
    {
        return new DateRangeCollection([$this->range]);
    }
}
