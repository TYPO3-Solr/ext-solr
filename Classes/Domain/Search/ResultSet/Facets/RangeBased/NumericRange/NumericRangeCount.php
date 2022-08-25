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
 * Value object that represent an date range count. The count has a date and the count of documents
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\NumericRange;

class NumericRangeCount
{
    /**
     * @var float
     */
    protected $rangeItem;

    /**
     * @var int
     */
    protected $documentCount = 0;

    /**
     * @param float $rangeItem
     * @param int $documentCount
     */
    public function __construct($rangeItem, $documentCount)
    {
        $this->rangeItem = $rangeItem;
        $this->documentCount = $documentCount;
    }

    /**
     * @return float
     */
    public function getRangeItem()
    {
        return $this->rangeItem;
    }

    /**
     * @return int
     */
    public function getDocumentCount()
    {
        return $this->documentCount;
    }
}
