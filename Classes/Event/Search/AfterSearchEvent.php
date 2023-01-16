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
use ApacheSolrForTypo3\Solr\Pagination\ResultsPagination;

/**
 * This event is triggered before adding the search result to the fluid template
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
final class AfterSearchEvent
{
    private SearchResultSet $resultSet;
    private array $additionalFilters;
    private string $pluginNamespace;
    private array $arguments;
    private ResultsPagination $pagination;
    private int $currentPage;

    /**
     * @param SearchResultSet $resultSet
     * @param array $additionalFilters
     * @param string $pluginNamespace
     * @param array $arguments
     * @param ResultsPagination $pagination
     * @param int $currentPage
     */
    public function __construct(
        SearchResultSet $resultSet,
        array $additionalFilters,
        string $pluginNamespace,
        array $arguments,
        ResultsPagination $pagination,
        int $currentPage
    ) {
        $this->resultSet = $resultSet;
        $this->additionalFilters = $additionalFilters;
        $this->pluginNamespace = $pluginNamespace;
        $this->arguments = $arguments;
        $this->pagination = $pagination;
        $this->currentPage = $currentPage;
    }

    /**
     * @return SearchResultSet
     */
    public function getResultSet(): SearchResultSet
    {
        return $this->resultSet;
    }

    /**
     * @return array
     */
    public function getAdditionalFilters(): array
    {
        return $this->additionalFilters;
    }

    /**
     * @return string
     */
    public function getPluginNamespace(): string
    {
        return $this->pluginNamespace;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return ResultsPagination
     */
    public function getPagination(): ResultsPagination
    {
        return $this->pagination;
    }

    /**
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
}
