<?php

namespace ApacheSolrForTypo3\Solr\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Sortings;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\SortingHelper;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sorting search component
 *
 * TODO maybe merge ApacheSolrForTypo3\Solr\Sorting into ApacheSolrForTypo3\Solr\Search\SortingComponent
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SortingComponent extends AbstractComponent implements QueryAware, SearchRequestAware
{

    /**
     * Solr query
     *
     * @var Query
     */
    protected $query;

    /**
     * @var SearchRequest
     */
    protected $searchRequest;

    /**
     * QueryBuilder
     *
     * @var QueryBuilder|object
     */
    protected $queryBuilder;

    /**
     * AccessComponent constructor.
     * @param QueryBuilder|null
     */
    public function __construct(QueryBuilder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class);
    }

    /**
     * Initializes the search component.
     *
     * Sets the sorting query parameters
     */
    public function initializeSearchComponent()
    {
        $this->queryBuilder->startFrom($this->query);

        if (!empty($this->searchConfiguration['query.']['sortBy'])) {
            $this->queryBuilder->useSortings(Sortings::fromString($this->searchConfiguration['query.']['sortBy']));
            $this->query = $this->queryBuilder->getQuery();
        }

        $isSortingEnabled = !empty($this->searchConfiguration['sorting']) && ((int)$this->searchConfiguration['sorting']) === 1;
        if (!$isSortingEnabled) {
            return;
        }

        $arguments = $this->searchRequest->getArguments();
        $isSortingPassedAsArgument = !empty($arguments['sort']) && preg_match('/^([a-z0-9_]+ (asc|desc)[, ]*)*([a-z0-9_]+ (asc|desc))+$/i', $arguments['sort']);
        if (!$isSortingPassedAsArgument) {
            return;
        }

        // a passed sorting has allways priority an overwrites the configured initial sorting
        $this->query->clearSorts();
        /** @var $sortHelper SortingHelper */
        $sortHelper = GeneralUtility::makeInstance(SortingHelper::class, $this->searchConfiguration['sorting.']['options.']);
        $sortFields = $sortHelper->getSortFieldFromUrlParameter($arguments['sort']);
        $this->queryBuilder->useSortings(Sortings::fromString($sortFields));
        $this->query = $this->queryBuilder->getQuery();
    }

    /**
     * Checks if the arguments array has a valid sorting.
     *
     * @param array $arguments
     * @return bool
     */
    protected function hasValidSorting(array $arguments)
    {
        return !empty($arguments['sort']) && preg_match('/^([a-z0-9_]+ (asc|desc)[, ]*)*([a-z0-9_]+ (asc|desc))+$/i', $arguments['sort']);
    }

    /**
     * Provides the extension component with an instance of the current query.
     *
     * @param Query $query Current query
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * @param SearchRequest $searchRequest
     */
    public function setSearchRequest(SearchRequest $searchRequest)
    {
        $this->searchRequest = $searchRequest;
    }
}
