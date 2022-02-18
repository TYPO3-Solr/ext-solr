<?php

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

namespace ApacheSolrForTypo3\Solr\Pagination;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Core\Pagination\AbstractPaginator;

/**
 * Class ResultsPaginator
 *
 * @author Rudy Gnodde <rudy.gnodde@beech.it>
 */
class ResultsPaginator extends AbstractPaginator
{
    /**
     * @var SearchResultSet
     */
    protected $resultSet;

    /**
     * @param SearchResultSet $resultSet
     * @param int $currentPageNumber
     * @param int $itemsPerPage
     */
    public function __construct(
        SearchResultSet $resultSet,
        int $currentPageNumber = 1,
        int $itemsPerPage = 10
    ) {
        $this->resultSet = $resultSet;
        $this->setCurrentPageNumber($currentPageNumber);
        $this->setItemsPerPage($itemsPerPage);

        $this->updateInternalState();
    }

    /**
     * Get paginated items
     *
     * @return iterable
     */
    public function getPaginatedItems(): iterable
    {
        return $this->resultSet->getSearchResults();
    }

    /**
     * Update paginated items
     *
     * @param int $itemsPerPage
     * @param int $offset
     */
    public function updatePaginatedItems(int $itemsPerPage, int $offset): void
    {
    }

    /**
     * Get amount of items on current page
     *
     * @return int
     */
    public function getAmountOfItemsOnCurrentPage(): int
    {
        return $this->resultSet->getSearchResults()->count();
    }

    /**
     * Get total amount of items
     *
     * @return int
     */
    public function getTotalAmountOfItems(): int
    {
        return $this->resultSet->getAllResultCount();
    }

}
