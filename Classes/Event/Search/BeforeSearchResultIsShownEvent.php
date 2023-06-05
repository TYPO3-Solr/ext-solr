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
final class BeforeSearchResultIsShownEvent
{
    private SearchResultSet $resultSet;
    private array $additionalFilters;
    private string $pluginNamespace;
    private array $arguments;
    private ResultsPagination $pagination;
    private int $currentPage;

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

    public function getResultSet(): SearchResultSet
    {
        return $this->resultSet;
    }

    public function getAdditionalFilters(): array
    {
        return $this->additionalFilters;
    }

    public function getPluginNamespace(): string
    {
        return $this->pluginNamespace;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getPagination(): ResultsPagination
    {
        return $this->pagination;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
}
