<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;

/**
 * Class GroupItem
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping
 */
class GroupItem
{
    /**
     * @var string
     */
    protected $groupValue = '';

    /**
     * @var int
     */
    protected $numFound = 0;

    /**
     * @var int
     */
    protected $start = 0;

    /**
     * @var float
     */
    protected $maxScore = 0;

    /**
     * @var SearchResultCollection
     */
    protected $searchResults;

    /**
     * @param Group $group
     * @param string $groupValue
     * @param int $numFound
     * @param int $start
     * @param float $maxScore
     */
    public function __construct(Group $group, $groupValue, $numFound, $start, $maxScore)
    {
        $this->group = $group;
        $this->groupValue = $groupValue;
        $this->numFound = $numFound;
        $this->start = $start;
        $this->maxScore = $maxScore;
        $this->searchResults = new SearchResultCollection();
    }

    /**
     * Get groupValue
     *
     * @return string
     */
    public function getGroupValue()
    {
        return $this->groupValue;
    }

    /**
     * Get numFound
     *
     * @return int
     */
    public function getNumFound()
    {
        return $this->numFound;
    }

    /**
     * Get start
     *
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Get maxScore
     *
     * @return float
     */
    public function getMaxScore()
    {
        return $this->maxScore;
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
     * @return Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }
}
