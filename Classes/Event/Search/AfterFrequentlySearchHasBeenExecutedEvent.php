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

namespace ApacheSolrForTypo3\Solr\Event\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * This event is dispatched after the frequently searched was executed.
 * It contains the result of that search request.
 */
final class AfterFrequentlySearchHasBeenExecutedEvent
{
    private SearchResultSet $resultSet;
    private array $additionalFilters;

    public function __construct(SearchResultSet $resultSet, array $additionalFilters)
    {
        $this->resultSet = $resultSet;
        $this->additionalFilters = $additionalFilters;
    }

    public function getResultSet(): SearchResultSet
    {
        return $this->resultSet;
    }

    public function getAdditionalFilters(): array
    {
        return $this->additionalFilters;
    }
}
