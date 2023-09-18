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
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * Event which is used when the searchResultSet has been created and by the SearchQuery.
 *
 * Previously used via
 * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['beforeSearch']
 */
final class AfterInitialSearchResultSetHasBeenCreatedEvent
{
    public function __construct(
        private SearchResultSet $searchResultSet,
        private readonly SearchRequest $searchRequest,
        private readonly Search $search,
        private readonly TypoScriptConfiguration $typoScriptConfiguration
    ) {}

    public function getSearchResultSet(): SearchResultSet
    {
        return $this->searchResultSet;
    }

    public function setSearchResultSet(SearchResultSet $searchResultSet): void
    {
        $this->searchResultSet = $searchResultSet;
    }

    public function getSearchRequest(): SearchRequest
    {
        return $this->searchRequest;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function getTypoScriptConfiguration(): TypoScriptConfiguration
    {
        return $this->typoScriptConfiguration;
    }
}
