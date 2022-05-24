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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * Collection for facet options.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractFacetItemCollection extends AbstractCollection
{
    /**
     * @param AbstractFacetItem|null $item
     * @return AbstractFacetItemCollection
     */
    public function add(?AbstractFacetItem $item): AbstractFacetItemCollection
    {
        if ($item === null) {
            return $this;
        }

        $this->data[$item->getCollectionKey()] = $item;
        return $this;
    }

    /**
     * @param string $value
     * @return ?AbstractFacetItem
     */
    public function getByValue(string $value): ?AbstractFacetItem
    {
        return $this->data[$value] ?? null;
    }

    /**
     * Retrieves the count (with get prefixed to be usable in fluid).
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count();
    }

    /**
     * @return AbstractCollection
     */
    public function getSelected(): AbstractCollection
    {
        return $this->getFilteredCopy(function (AbstractFacetItem $item) {
            return $item->getSelected();
        });
    }

    /**
     * @param array $manualSorting
     * @return AbstractFacetItemCollection
     */
    public function getManualSortedCopy(array $manualSorting): AbstractFacetItemCollection
    {
        $result = clone $this;
        $copiedItems = $result->data;
        $sortedOptions = [];
        foreach ($manualSorting as $item) {
            if (isset($copiedItems[$item])) {
                $sortedOptions[$item] = $copiedItems[$item];
                unset($copiedItems[$item]);
            }
        }
        // in the end all items get appended that are not configured in the manual sort order
        $sortedOptions = $sortedOptions + $copiedItems;
        $result->data = $sortedOptions;

        return $result;
    }

    /**
     * @return AbstractFacetItemCollection
     */
    public function getReversedOrderCopy(): AbstractFacetItemCollection
    {
        $result = clone $this;
        $result->data = array_reverse($result->data, true);

        return $result;
    }
}
