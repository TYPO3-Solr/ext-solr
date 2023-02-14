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

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use ReturnTypeWillChange;
use Traversable;

/**
 * Class AbstractCollection
 */
abstract class AbstractCollection implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * @var array
     */
    protected array $data = [];

    /**
     * @param array $data
     */
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
     *
     * @param Closure $filter
     * @return AbstractCollection
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
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * @return array
     */
    public function getArrayCopy(): array
    {
        return $this->data;
    }

    /**
     * @param int $position
     * @return ?object
     */
    public function getByPosition(int $position): ?object
    {
        $keys = array_keys($this->data);
        return $this->data[$keys[$position] ?? ''] ?? null;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return int
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
     * Offset to retrieve
     *
     * @param mixed $offset
     * @return mixed
     * @noinspection PhpLanguageLevelInspection
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->data[$offset];
        }
        return null;
    }

    /**
     * Offset to set
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
            return;
        }
        $this->data[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->data[$offset]);
        }
    }
}
