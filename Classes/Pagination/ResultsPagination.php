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

namespace ApacheSolrForTypo3\Solr\Pagination;

use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * Class ResultsPagination
 */
class ResultsPagination implements PaginationInterface
{
    protected PaginatorInterface $paginator;

    protected int $maxPageNumbers = 0;

    protected bool $hasMorePages = false;

    protected bool $hasLessPages = false;

    protected int $pageRangeFirst = 1;

    protected int $pageRangeLast = 1;

    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
        $this->calculatePageRange();
    }

    /**
     * Get maximum page numbers
     */
    public function getMaxPageNumbers(): int
    {
        return $this->maxPageNumbers;
    }

    /**
     * Set maximum page numbers
     */
    public function setMaxPageNumbers(int $maxPageNumbers): void
    {
        $this->maxPageNumbers = $maxPageNumbers;
        $this->calculatePageRange();
    }

    /**
     * Get has more pages
     */
    public function getHasMorePages(): bool
    {
        return $this->hasMorePages;
    }

    /**
     * Checks if pagination has fewer pages
     */
    public function getHasLessPages(): bool
    {
        return $this->hasLessPages;
    }

    /**
     * Get page range first
     */
    public function getPageRangeFirst(): int
    {
        return $this->pageRangeFirst;
    }

    /**
     * Get page range last
     */
    public function getPageRangeLast(): int
    {
        return $this->pageRangeLast;
    }

    /**
     * Get previous page number
     */
    public function getPreviousPageNumber(): ?int
    {
        $previousPage = $this->paginator->getCurrentPageNumber() - 1;

        if ($previousPage > $this->paginator->getNumberOfPages()) {
            return null;
        }

        return $previousPage >= $this->getFirstPageNumber() ? $previousPage : null;
    }

    /**
     * Get next page number
     */
    public function getNextPageNumber(): ?int
    {
        $nextPage = $this->paginator->getCurrentPageNumber() + 1;

        return $nextPage <= $this->paginator->getNumberOfPages()
            ? $nextPage
            : null;
    }

    /**
     * Get first page number
     */
    public function getFirstPageNumber(): int
    {
        return 1;
    }

    /**
     * Get last page number
     */
    public function getLastPageNumber(): int
    {
        return $this->paginator->getNumberOfPages();
    }

    /**
     * Get start record number
     */
    public function getStartRecordNumber(): int
    {
        if ($this->paginator->getCurrentPageNumber() > $this->paginator->getNumberOfPages()) {
            return 0;
        }

        return $this->paginator->getKeyOfFirstPaginatedItem() + 1;
    }

    /**
     * Get end record number
     */
    public function getEndRecordNumber(): int
    {
        if ($this->paginator->getCurrentPageNumber() > $this->paginator->getNumberOfPages()) {
            return 0;
        }

        return $this->paginator->getKeyOfLastPaginatedItem() + 1;
    }

    /**
     * Get all page numbers
     *
     * @return int[]
     */
    public function getAllPageNumbers(): array
    {
        return range($this->pageRangeFirst, $this->pageRangeLast);
    }

    /**
     * Calculate page range
     */
    protected function calculatePageRange(): void
    {
        $this->pageRangeFirst = 1;
        $this->pageRangeLast = $this->getLastPageNumber();
        $this->hasLessPages = false;
        $this->hasMorePages = false;

        $maxNumberOfLinks = $this->getMaxPageNumbers();
        if ($maxNumberOfLinks > 0) {
            $numberOfPages = $this->paginator->getNumberOfPages();

            if ($numberOfPages > $maxNumberOfLinks) {
                $currentPage = $this->paginator->getCurrentPageNumber();
                $pagesBeforeAndAfter = ($maxNumberOfLinks - 1) / 2;
                $this->pageRangeFirst = (int)($currentPage - floor($pagesBeforeAndAfter));
                $this->pageRangeLast = (int)($currentPage + ceil($pagesBeforeAndAfter));

                if ($this->pageRangeFirst < 1) {
                    $this->pageRangeLast -= $this->pageRangeFirst - 1;
                }
                if ($this->pageRangeLast > $numberOfPages) {
                    $this->pageRangeFirst -= $this->pageRangeLast - $numberOfPages;
                }
                $this->pageRangeFirst = max($this->pageRangeFirst, 1);
                $this->pageRangeLast = min($this->pageRangeLast, $numberOfPages);
                $this->hasLessPages = $this->pageRangeFirst > 1;
                $this->hasMorePages = $this->pageRangeLast < $numberOfPages;
            }
        }
    }
}
