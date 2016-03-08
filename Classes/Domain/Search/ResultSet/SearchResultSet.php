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
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;

/**
 * The SearchResultSet is used to provided access to the \Apache_Solr_Response and
 * other relevant information, like the used Query and Request objects.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
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
     * @var integer
     */
    protected $usedPage = 0;

    /**
     * @var array
     */
    protected $usedAdditionalFilters = array();

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
     * Retrieve the page argument that has beed used to build this SearchResultSet.
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
}
