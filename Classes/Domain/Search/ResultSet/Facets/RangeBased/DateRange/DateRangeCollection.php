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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItemCollection;

/**
 * Collection for facet options.
 */
class DateRangeCollection extends AbstractFacetItemCollection
{
    /**
     * Adds facet item to the collection
     */
    public function add(?AbstractFacetItem $item): AbstractFacetItemCollection
    {
        return parent::add($item);
    }

    /**
     * Returns the {@link DateRange} if resolved/available in position
     */
    public function getByPosition(int $position): ?object
    {
        return parent::getByPosition($position);
    }
}
