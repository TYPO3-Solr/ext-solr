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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Debug search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class DebugComponent extends AbstractComponent implements QueryAware, SearchRequestAware
{
    /**
     * Solr query
     */
    protected ?Query $query = null;

    protected ?SearchRequest $searchRequest;

    protected QueryBuilder $queryBuilder;

    /**
     * AccessComponent constructor.
     */
    public function __construct(QueryBuilder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class);
    }

    /**
     * Provides a component that is aware of the current SearchRequest
     */
    public function setSearchRequest(SearchRequest $searchRequest): void
    {
        $this->searchRequest = $searchRequest;
    }

    /**
     * Initializes the search component.
     *
     * Sets the debug query parameter
     */
    public function initializeSearchComponent(): void
    {
        if ($this->searchRequest->getContextTypoScriptConfiguration()->getEnabledDebugMode()) {
            $this->queryBuilder->startFrom($this->query)->useDebug(true);
        }
    }

    /**
     * Provides the extension component with an instance of the current query.
     *
     * @param Query $query Current query
     */
    public function setQuery(Query $query): void
    {
        $this->query = $query;
    }
}
