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

namespace ApacheSolrForTypo3\Solr\System\Data;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Class AbstractCollection
 */
abstract class AbstractCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @var AbstractFacetItem[]|Group[]
     */
    protected array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Clears the collection data.
     *
     * @noinspection PhpUnused
     */
    public function clean(): void
    {
        $this->data = [];
    }

    /**
     * This method can be used to pass a closure to create a filtered copy.
     * The closure get a collection item passed and needs to return true when the item should
     * be kept or false when it can be skipped.
     */
    public function getFilteredCopy(Closure $filter): AbstractCollection
    {
        $copy = clone $this;
        $filteredData = [];
        foreach ($this->data as $key => $item) {
            if ($filter($item)) {
                $filteredData[$key] = $item;
            }
        }

        $copy->data = $filteredData;
        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function getArrayCopy(): array
    {
        return $this->data;
    }

    /**
     * Returns the collections item by position if available
     */
    public function getByPosition(int $position): ?object
    {
        $keys = array_keys($this->data);
        return $this->data[$keys[$position] ?? ''] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * See {@link self::count()} but for Fluid accessor.
     */
    public function getCount(): int
    {
        return $this->count();
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->offsetExists($offset)) {
            return $this->data[$offset];
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
            return;
        }
        $this->data[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->data[$offset]);
        }
    }
}
