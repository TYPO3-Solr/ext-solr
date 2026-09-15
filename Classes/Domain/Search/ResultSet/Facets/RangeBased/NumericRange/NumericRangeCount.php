<?php

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

/**
 * Value object that represent a date range count. The count has a date and the count of documents
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

class NumericRangeCount
{
    protected float $rangeItem;

    protected int $documentCount = 0;

    public function __construct(float $rangeItem, int $documentCount)
    {
        $this->rangeItem = $rangeItem;
        $this->documentCount = $documentCount;
    }

    public function getRangeItem(): float
    {
        return $this->rangeItem;
    }

    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }
}
