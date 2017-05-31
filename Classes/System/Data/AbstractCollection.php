<?php

namespace ApacheSolrForTypo3\Solr\System\Data;

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

/**
 * Class AbstractCollection
 */
abstract class AbstractCollection implements \IteratorAggregate, \Countable, \ArrayAccess
{

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return void
     */
    public function clean()
    {
        $this->data = [];
    }

    /**
     * This method can be used to pass a closure to created a filtered copy.
     * The closure get an collection item passed and needs to return true when the item should
     * be kept or false when it can be skipped.
     *
     * @param callable $filter
     * @return AbstractCollection
     */
    public function getFilteredCopy(\Closure $filter)
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
     * @return \ArrayIterator|\Traversable
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @return array
     */
    public function getArrayCopy()
    {
        return $this->data;
    }

    /**
     * @param int $position
     * @return Object
     */
    public function getByPosition($position)
    {
        $keys = array_keys($this->data);
        return isset($this->data[$keys[$position]]) ? $this->data[$keys[$position]] : null;
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
    public function count()
    {
        return count($this->data);
    }

    /**
     * @return int
     */
    public function getCount()
    {
        return $this->count();
    }

    /**
     * Whether a offset exists
     *
     * @param mixed $offset
     * @return bool true on success or false on failure
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Offset to retrieve
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->data[$offset];
        } else {
            return null;
        }
    }

    /**
     * Offset to set
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * Offset to unset
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->data[$offset]);
        }
    }
}
