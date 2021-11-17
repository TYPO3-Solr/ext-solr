<?php
namespace ApacheSolrForTypo3\Solr\Pagination;

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

use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * Class ResultsPagination
 *
 * @author Rudy Gnodde <rudy.gnodde@beech.it>
 */
class ResultsPagination implements PaginationInterface
{
    /**
     * @var PaginatorInterface
     */
    protected $paginator;

    /**
     * @var int
     */
    protected $maxPageNumbers = 0;

    /**
     * @var bool
     */
    protected $hasMorePages = false;

    /**
     * @var bool
     */
    protected $hasLessPages = false;

    /**
     * @var int
     */
    protected $pageRangeFirst;

    /**
     * @var int
     */
    protected $pageRangeLast;

    /**
     * @param PaginatorInterface $paginator
     */
    public function __construct(PaginatorInterface $paginator)
    {
        $this->paginator = $paginator;
        $this->calculatePageRange();
    }

    /**
     * @return int
     */
    public function getMaxPageNumbers(): int
    {
        return $this->maxPageNumbers;
    }

    /**
     * @param int $maxPageNumbers
     */
    public function setMaxPageNumbers(int $maxPageNumbers): void
    {
        $this->maxPageNumbers = $maxPageNumbers;
        $this->calculatePageRange();
    }

    /**
     * @return bool
     */
    public function getHasMorePages(): bool
    {
        return $this->hasMorePages;
    }

    /**
     * @return bool
     */
    public function getHasLessPages(): bool
    {
        return $this->hasLessPages;
    }

    /**
     * @return int|null
     */
    public function getPreviousPageNumber(): ?int
    {
        $previousPage = $this->paginator->getCurrentPageNumber() - 1;

        if ($previousPage > $this->paginator->getNumberOfPages()) {
            return null;
        }

        return $previousPage >= $this->getFirstPageNumber()
            ? $previousPage
            : null
            ;
    }

    /**
     * @return int|null
     */
    public function getNextPageNumber(): ?int
    {
        $nextPage = $this->paginator->getCurrentPageNumber() + 1;

        return $nextPage <= $this->paginator->getNumberOfPages()
            ? $nextPage
            : null;
    }

    /**
     * @return int
     */
    public function getFirstPageNumber(): int
    {
        return 1;
    }

    /**
     * @return int
     */
    public function getLastPageNumber(): int
    {
        return $this->paginator->getNumberOfPages();
    }

    /**
     * @return int
     */
    public function getStartRecordNumber(): int
    {
        if ($this->paginator->getCurrentPageNumber() > $this->paginator->getNumberOfPages()) {
            return 0;
        }

        return $this->paginator->getKeyOfFirstPaginatedItem() + 1;
    }

    /**
     * @return int
     */
    public function getEndRecordNumber(): int
    {
        if ($this->paginator->getCurrentPageNumber() > $this->paginator->getNumberOfPages()) {
            return 0;
        }

        return $this->paginator->getKeyOfLastPaginatedItem() + 1;
    }

    /**
     * @return int[]
     */
    public function getAllPageNumbers(): array
    {
        return range($this->pageRangeFirst, $this->pageRangeLast);
    }

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
                $this->pageRangeFirst = $currentPage - floor($pagesBeforeAndAfter);
                $this->pageRangeLast = $currentPage + ceil($pagesBeforeAndAfter);

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
