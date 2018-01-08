<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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


class Pagination {
    /**
     * @var int
     */
    protected $resultsPerPage;

    /**
     * @var int
     */
    protected $page;

    /**
     * Pagination constructor.
     *
     * @param int $page
     * @param int $resultsPerPage
     */
    public function __construct(int $page = 0, int $resultsPerPage = 01)
    {
        $this->page = $page;
        $this->resultsPerPage = $resultsPerPage;
    }

    /**
     * Gets the currently showing page's number
     *
     * @return int page number currently showing
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Sets the page that should be shown
     *
     * @param int $page page number to show
     * @return void
     */
    public function setPage($page)
    {
        $this->page = max(intval($page), 0);
    }

    /**
     * Gets the index of the first result document we're showing
     *
     * @return int index of the currently first document showing
     */
    public function getStartIndex()
    {
        return ($this->page - 1) * $this->resultsPerPage;
    }

    /**
     * Gets the index of the last result document we're showing
     *
     * @return int index of the currently last document showing
     */
    public function getEndIndex()
    {
        return $this->page * $this->resultsPerPage;
    }

    /**
     * Returns the number of results that should be shown per page
     *
     * @return int number of results to show per page
     */
    public function getResultsPerPage()
    {
        return $this->resultsPerPage;
    }

    /**
     * Sets the number of results that should be shown per page
     *
     * @param int $resultsPerPage Number of results to show per page
     * @return void
     */
    public function setResultsPerPage($resultsPerPage)
    {
        $this->resultsPerPage = max(intval($resultsPerPage), 0);
    }
}