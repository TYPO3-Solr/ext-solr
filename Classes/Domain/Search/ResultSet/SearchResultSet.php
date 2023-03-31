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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\SortingCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking\Suggestion;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;

/**
 * The SearchResultSet is used to provide access to the Apache Solr Response and
 * other relevant information, like the used Query and Request objects.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResultSet
{
    protected ?Query $usedQuery = null;

    protected ?SearchRequest $usedSearchRequest = null;

    protected ?Search $usedSearch = null;

    protected ?ResponseAdapter $response = null;

    protected int $usedPage = 0;

    protected int $usedResultsPerPage = 0;

    protected array $usedAdditionalFilters = [];

    protected SearchResultCollection $searchResults;

    protected int $allResultCount = 0;

    protected float $maximumScore = 0.0;

    /**
     * @var Suggestion[]
     */
    protected array $spellCheckingSuggestions = [];

    protected FacetCollection $facets;

    protected SortingCollection $sortings;

    protected bool $isAutoCorrected = false;

    protected string $initialQueryString = '';

    protected string $correctedQueryString = '';

    protected bool $hasSearched = false;

    public function __construct()
    {
        $this->facets = new FacetCollection();
        $this->sortings = new SortingCollection();
        $this->searchResults = new SearchResultCollection();
    }

    public function setAllResultCount(int $allResultCount): void
    {
        $this->allResultCount = $allResultCount;
    }

    public function getAllResultCount(): int
    {
        return $this->allResultCount;
    }

    public function addSpellCheckingSuggestion(Suggestion $suggestion): void
    {
        $this->spellCheckingSuggestions[$suggestion->getSuggestion()] = $suggestion;
    }

    public function getHasSpellCheckingSuggestions(): bool
    {
        return count($this->spellCheckingSuggestions) > 0;
    }

    /**
     * @param Suggestion[] $spellCheckingSuggestions
     */
    public function setSpellCheckingSuggestions(array $spellCheckingSuggestions): void
    {
        $this->spellCheckingSuggestions = $spellCheckingSuggestions;
    }

    /**
     * @return Suggestion[]
     */
    public function getSpellCheckingSuggestions(): array
    {
        return $this->spellCheckingSuggestions;
    }

    public function getFacets(): FacetCollection
    {
        return $this->facets;
    }

    public function addFacet(AbstractFacet $facet): void
    {
        $this->facets->addFacet($facet);
    }

    public function getMaximumScore(): float
    {
        return $this->maximumScore;
    }

    public function setMaximumScore(float $maximumScore): void
    {
        $this->maximumScore = $maximumScore;
    }

    public function addSorting(Sorting $sorting): void
    {
        $this->sortings->addSorting($sorting);
    }

    public function getSortings(): SortingCollection
    {
        return $this->sortings;
    }

    public function setResponse(ResponseAdapter $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?ResponseAdapter
    {
        return $this->response;
    }

    public function setUsedAdditionalFilters(array $usedAdditionalFilters): void
    {
        $this->usedAdditionalFilters = $usedAdditionalFilters;
    }

    public function getUsedAdditionalFilters(): array
    {
        return $this->usedAdditionalFilters;
    }

    public function setUsedQuery(Query $usedQuery): void
    {
        $this->usedQuery = $usedQuery;
    }

    /**
     * Retrieves the query object that has been used to build this result set.
     */
    public function getUsedQuery(): ?Query
    {
        return $this->usedQuery;
    }

    public function setUsedPage(int $page): void
    {
        $this->usedPage = $page;
    }

    /**
     * Retrieve the page argument that has been used to build this SearchResultSet.
     */
    public function getUsedPage(): int
    {
        return $this->usedPage;
    }

    public function setUsedSearchRequest(SearchRequest $usedSearchRequest): void
    {
        $this->usedSearchRequest = $usedSearchRequest;
    }

    /**
     * Retrieves the SearchRequest that has been used to build this SearchResultSet.
     */
    public function getUsedSearchRequest(): ?SearchRequest
    {
        return $this->usedSearchRequest;
    }

    public function setUsedSearch(Search $usedSearch): void
    {
        $this->usedSearch = $usedSearch;
    }

    public function getUsedSearch(): ?Search
    {
        return $this->usedSearch;
    }

    public function setUsedResultsPerPage(int $usedResultsPerPage): void
    {
        $this->usedResultsPerPage = $usedResultsPerPage;
    }

    public function getUsedResultsPerPage(): int
    {
        return $this->usedResultsPerPage;
    }

    public function getSearchResults(): SearchResultCollection
    {
        return $this->searchResults;
    }

    public function setSearchResults(SearchResultCollection $searchResults): void
    {
        $this->searchResults = $searchResults;
    }

    public function addSearchResult(SearchResult $searchResult): void
    {
        $this->searchResults[] = $searchResult;
    }

    public function getIsAutoCorrected(): bool
    {
        return $this->isAutoCorrected;
    }

    public function setIsAutoCorrected(bool $wasAutoCorrected): void
    {
        $this->isAutoCorrected = $wasAutoCorrected;
    }

    public function getInitialQueryString(): string
    {
        return $this->initialQueryString;
    }

    public function setInitialQueryString(string $initialQueryString): void
    {
        $this->initialQueryString = $initialQueryString;
    }

    public function getCorrectedQueryString(): string
    {
        return $this->correctedQueryString;
    }

    public function setCorrectedQueryString(string $correctedQueryString): void
    {
        $this->correctedQueryString = $correctedQueryString;
    }

    public function getHasSearched(): bool
    {
        return $this->hasSearched;
    }

    public function setHasSearched(bool $hasSearched): void
    {
        $this->hasSearched = $hasSearched;
    }
}
