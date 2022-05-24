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
    /**
     * @var Query|null
     */
    protected ?Query $usedQuery = null;

    /**
     * @var SearchRequest|null
     */
    protected ?SearchRequest $usedSearchRequest = null;

    /**
     * @var Search|null
     */
    protected ?Search $usedSearch = null;

    /**
     * @var ResponseAdapter
     */
    protected ResponseAdapter $response;

    /**
     * @var int
     */
    protected int $usedPage = 0;

    /**
     * @var int
     */
    protected int $usedResultsPerPage = 0;

    /**
     * @var array
     */
    protected array $usedAdditionalFilters = [];

    /**
     * @var SearchResultCollection
     */
    protected SearchResultCollection $searchResults;

    /**
     * @var int
     */
    protected int $allResultCount = 0;

    /**
     * @var float
     */
    protected float $maximumScore = 0.0;

    /**
     * @var Suggestion[]
     */
    protected array $spellCheckingSuggestions = [];

    /**
     * @var FacetCollection
     */
    protected FacetCollection $facets;

    /**
     * @var SortingCollection
     */
    protected SortingCollection $sortings;

    /**
     * @var bool
     */
    protected bool $isAutoCorrected = false;

    /**
     * @var string
     */
    protected string $initialQueryString = '';

    /**
     * @var string
     */
    protected string $correctedQueryString = '';

    /**
     * @var bool
     */
    protected bool $hasSearched = false;

    /**
     * Constructor for SearchResultSet
     */
    public function __construct()
    {
        $this->facets = new FacetCollection();
        $this->sortings = new SortingCollection();
        $this->searchResults = new SearchResultCollection();
    }

    /**
     * @param int $allResultCount
     */
    public function setAllResultCount(int $allResultCount)
    {
        $this->allResultCount = $allResultCount;
    }

    /**
     * @return int
     */
    public function getAllResultCount(): int
    {
        return $this->allResultCount;
    }

    /**
     * @param Suggestion $suggestion
     */
    public function addSpellCheckingSuggestion(Suggestion $suggestion)
    {
        $this->spellCheckingSuggestions[$suggestion->getSuggestion()] = $suggestion;
    }

    /**
     * @return bool
     */
    public function getHasSpellCheckingSuggestions(): bool
    {
        return count($this->spellCheckingSuggestions) > 0;
    }

    /**
     * @param Suggestion[] $spellCheckingSuggestions
     */
    public function setSpellCheckingSuggestions(array $spellCheckingSuggestions)
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

    /**
     * @return FacetCollection
     */
    public function getFacets(): FacetCollection
    {
        return $this->facets;
    }

    /**
     * @param AbstractFacet $facet
     */
    public function addFacet(AbstractFacet $facet)
    {
        $this->facets->addFacet($facet);
    }

    /**
     * @return float
     */
    public function getMaximumScore(): float
    {
        return $this->maximumScore;
    }

    /**
     * @param float $maximumScore
     */
    public function setMaximumScore(float $maximumScore)
    {
        $this->maximumScore = $maximumScore;
    }

    /**
     * @param Sorting $sorting
     */
    public function addSorting(Sorting $sorting)
    {
        $this->sortings->addSorting($sorting);
    }

    /**
     * @return SortingCollection
     */
    public function getSortings(): SortingCollection
    {
        return $this->sortings;
    }

    /**
     * @param ResponseAdapter $response
     */
    public function setResponse(ResponseAdapter $response)
    {
        $this->response = $response;
    }

    /**
     * @return ResponseAdapter
     */
    public function getResponse(): ResponseAdapter
    {
        return $this->response;
    }

    /**
     * @param array $usedAdditionalFilters
     */
    public function setUsedAdditionalFilters(array $usedAdditionalFilters)
    {
        $this->usedAdditionalFilters = $usedAdditionalFilters;
    }

    /**
     * @return array
     */
    public function getUsedAdditionalFilters(): array
    {
        return $this->usedAdditionalFilters;
    }

    /**
     * @param Query $usedQuery
     */
    public function setUsedQuery(Query $usedQuery)
    {
        $this->usedQuery = $usedQuery;
    }

    /**
     * Retrieves the query object that has been used to build this result set.
     *
     * @return Query
     */
    public function getUsedQuery(): ?Query
    {
        return $this->usedQuery;
    }

    /**
     * @param int $page
     */
    public function setUsedPage(int $page)
    {
        $this->usedPage = $page;
    }

    /**
     * Retrieve the page argument that has been used to build this SearchResultSet.
     *
     * @return int
     */
    public function getUsedPage(): int
    {
        return $this->usedPage;
    }

    /**
     * @param SearchRequest $usedSearchRequest
     */
    public function setUsedSearchRequest(SearchRequest $usedSearchRequest)
    {
        $this->usedSearchRequest = $usedSearchRequest;
    }

    /**
     * Retrieves the SearchRequest that has been used to build this SearchResultSet.
     *
     * @return SearchRequest
     */
    public function getUsedSearchRequest(): ?SearchRequest
    {
        return $this->usedSearchRequest;
    }

    /**
     * @param Search $usedSearch
     */
    public function setUsedSearch(Search $usedSearch)
    {
        $this->usedSearch = $usedSearch;
    }

    /**
     * @return Search
     */
    public function getUsedSearch(): ?Search
    {
        return $this->usedSearch;
    }

    /**
     * @param int $usedResultsPerPage
     */
    public function setUsedResultsPerPage(int $usedResultsPerPage)
    {
        $this->usedResultsPerPage = $usedResultsPerPage;
    }

    /**
     * @return int
     */
    public function getUsedResultsPerPage(): int
    {
        return $this->usedResultsPerPage;
    }

    /**
     * @return SearchResultCollection
     */
    public function getSearchResults(): SearchResultCollection
    {
        return $this->searchResults;
    }

    /**
     * @param SearchResultCollection $searchResults
     */
    public function setSearchResults(SearchResultCollection $searchResults)
    {
        $this->searchResults = $searchResults;
    }

    /**
     * @param SearchResult $searchResult
     */
    public function addSearchResult(SearchResult $searchResult)
    {
        $this->searchResults[] = $searchResult;
    }

    /**
     * @return bool
     */
    public function getIsAutoCorrected(): bool
    {
        return $this->isAutoCorrected;
    }

    /**
     * @param bool $wasAutoCorrected
     */
    public function setIsAutoCorrected(bool $wasAutoCorrected)
    {
        $this->isAutoCorrected = $wasAutoCorrected;
    }

    /**
     * @return string
     */
    public function getInitialQueryString(): string
    {
        return $this->initialQueryString;
    }

    /**
     * @param string $initialQueryString
     */
    public function setInitialQueryString(string $initialQueryString)
    {
        $this->initialQueryString = $initialQueryString;
    }

    /**
     * @return string
     */
    public function getCorrectedQueryString(): string
    {
        return $this->correctedQueryString;
    }

    /**
     * @param string $correctedQueryString
     */
    public function setCorrectedQueryString(string $correctedQueryString)
    {
        $this->correctedQueryString = $correctedQueryString;
    }

    /**
     * @return bool
     */
    public function getHasSearched(): bool
    {
        return $this->hasSearched;
    }

    /**
     * @param bool $hasSearched
     */
    public function setHasSearched(bool $hasSearched)
    {
        $this->hasSearched = $hasSearched;
    }
}
