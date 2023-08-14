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

use DateTime;

/**
 * Value object that represent a date range count. The count has a date and the count of documents
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DateRangeCount
{
    protected DateTime $date;

    protected int $documentCount = 0;

    public function __construct(DateTime $date, int $documentCount = 0)
    {
        $this->date = $date;
        $this->documentCount = $documentCount;
    }

    public function getDate(): DateTime
    {
        return $this->date;
    }

    public function getDocumentCount(): int
    {
        return $this->documentCount;
    }
}
