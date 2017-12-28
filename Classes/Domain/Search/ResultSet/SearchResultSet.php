<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\Sorting;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\SortingCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking\Suggestion;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;

//@deprecated
//@todo this alias can be removed when the old class was dropped
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult as NewSearchResult;


/**
 * The SearchResultSet is used to provided access to the \Apache_Solr_Response and
 * other relevant information, like the used Query and Request objects.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResultSet
{

    /**
     * @var Query
     */
    protected $usedQuery = null;

    /**
     * @var SearchRequest
     */
    protected $usedSearchRequest = null;

    /**
     * @var Search
     */
    protected $usedSearch;

    /**
     * @var \Apache_Solr_Response
     */
    protected $response = null;

    /**
     * @var int
     */
    protected $usedPage = 0;

    /**
     * @var int
     */
    protected $usedResultsPerPage = 0;

    /**
     * @var array
     */
    protected $usedAdditionalFilters = [];

    /**
     * @var SearchResultCollection
     */
    protected $searchResults = null;

    /**
     * @var int
     */
    protected $allResultCount = 0;

    /**
     * @var Suggestion[]
     */
    protected $spellCheckingSuggestions = [];

    /**
     * @var FacetCollection
     */
    protected $facets = null;

    /**
     * @var SortingCollection
     */
    protected $sortings = null;

    /**
     * @var bool
     */
    protected $isAutoCorrected = false;

    /**
     * @var string
     */
    protected $initialQueryString = '';

    /**
     * @var string
     */
    protected $correctedQueryString = '';

    /**
     * @return \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet
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
    public function setAllResultCount($allResultCount)
    {
        $this->allResultCount = $allResultCount;
    }

    /**
     * @return int
     */
    public function getAllResultCount()
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
    public function getHasSpellCheckingSuggestions()
    {
        return count($this->spellCheckingSuggestions) > 0;
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking\Suggestion[] $spellCheckingSuggestions
     */
    public function setSpellCheckingSuggestions($spellCheckingSuggestions)
    {
        $this->spellCheckingSuggestions = $spellCheckingSuggestions;
    }

    /**
     * @return \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Spellchecking\Suggestion[]
     */
    public function getSpellCheckingSuggestions()
    {
        return $this->spellCheckingSuggestions;
    }

    /**
     * @return FacetCollection
     */
    public function getFacets()
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
     * @param Sorting $sorting
     */
    public function addSorting(Sorting $sorting)
    {
        $this->sortings->addSorting($sorting);
    }

    /**
     * @return SortingCollection
     */
    public function getSortings()
    {
        return $this->sortings;
    }

    /**
     * @param \Apache_Solr_Response $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    /**
     * @return \Apache_Solr_Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param array $usedAdditionalFilters
     */
    public function setUsedAdditionalFilters($usedAdditionalFilters)
    {
        $this->usedAdditionalFilters = $usedAdditionalFilters;
    }

    /**
     * @return array
     */
    public function getUsedAdditionalFilters()
    {
        return $this->usedAdditionalFilters;
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Query $usedQuery
     */
    public function setUsedQuery($usedQuery)
    {
        $this->usedQuery = $usedQuery;
    }

    /**
     * Retrieves the query object that has been used to build this result set.
     *
     * @return \ApacheSolrForTypo3\Solr\Query
     */
    public function getUsedQuery()
    {
        return $this->usedQuery;
    }

    /**
     * @param int $page
     */
    public function setUsedPage($page)
    {
        $this->usedPage = $page;
    }

    /**
     * Retrieve the page argument that has been used to build this SearchResultSet.
     *
     * @return int
     */
    public function getUsedPage()
    {
        return $this->usedPage;
    }

    /**
     * @return int
     */
    public function getResultsPerPage()
    {
        return $this->usedQuery->getResultsPerPage();
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest $usedSearchRequest
     */
    public function setUsedSearchRequest($usedSearchRequest)
    {
        $this->usedSearchRequest = $usedSearchRequest;
    }

    /**
     * Retrieves the SearchRequest that has been used to build this SearchResultSet.
     *
     * @return \ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest
     */
    public function getUsedSearchRequest()
    {
        return $this->usedSearchRequest;
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Search $usedSearch
     */
    public function setUsedSearch($usedSearch)
    {
        $this->usedSearch = $usedSearch;
    }

    /**
     * @return \ApacheSolrForTypo3\Solr\Search
     */
    public function getUsedSearch()
    {
        return $this->usedSearch;
    }

    /**
     * @param int $usedResultsPerPage
     */
    public function setUsedResultsPerPage($usedResultsPerPage)
    {
        $this->usedResultsPerPage = $usedResultsPerPage;
    }

    /**
     * @return int
     */
    public function getUsedResultsPerPage()
    {
        return $this->usedResultsPerPage;
    }

    /**
     * @return SearchResultCollection
     */
    public function getSearchResults()
    {
        return $this->searchResults;
    }

    /**
     * @param SearchResultCollection $searchResults
     */
    public function setSearchResults($searchResults)
    {
        $this->searchResults = $searchResults;
    }

    /**
     * @param SearchResult $searchResult
     */
    public function addSearchResult(NewSearchResult $searchResult)
    {
        $this->searchResults[] = $searchResult;
    }

    /**
     * @return boolean
     */
    public function getIsAutoCorrected()
    {
        return $this->isAutoCorrected;
    }

    /**
     * @param boolean $wasAutoCorrected
     */
    public function setIsAutoCorrected($wasAutoCorrected)
    {
        $this->isAutoCorrected = $wasAutoCorrected;
    }

    /**
     * @return string
     */
    public function getInitialQueryString()
    {
        return $this->initialQueryString;
    }

    /**
     * @param string $initialQueryString
     */
    public function setInitialQueryString($initialQueryString)
    {
        $this->initialQueryString = $initialQueryString;
    }

    /**
     * @return string
     */
    public function getCorrectedQueryString()
    {
        return $this->correctedQueryString;
    }

    /**
     * @param string $correctedQueryString
     */
    public function setCorrectedQueryString($correctedQueryString)
    {
        $this->correctedQueryString = $correctedQueryString;
    }


}
