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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Sorting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the sorting.
 */
class Sortings extends AbstractDeactivatable
{
    protected array $sortings = [];

    /**
     * Sortings constructor.
     */
    public function __construct(
        bool $isEnabled = false,
        array $sortings = []
    ) {
        $this->isEnabled = $isEnabled;
        $this->setSortings($sortings);
    }

    public static function getEmpty(): Sortings
    {
        return new Sortings(false);
    }

    /**
     * @return Sorting[]
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    public function setSortings(array $sortings): void
    {
        $this->sortings = $sortings;
    }

    public function addSorting(Sorting $sorting): void
    {
        $this->sortings[] = $sorting;
    }

    /**
     * Parses the sortings representation "<fieldName> <direction>,<fieldName> <direction>"
     */
    public static function fromString(string $sortingsString): Sortings
    {
        $sortFields = GeneralUtility::trimExplode(',', $sortingsString);
        $sortings = [];
        foreach ($sortFields as $sortField) {
            $sorting = Sorting::fromString($sortField);
            $sortings[] = $sorting;
        }

        return new Sortings(true, $sortings);
    }
}
