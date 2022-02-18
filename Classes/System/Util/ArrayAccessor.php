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

namespace ApacheSolrForTypo3\Solr\System\Util;

/**
 * Class ArrayAccessor
 *
 * LowLevel class to access nested associative arrays with
 * a path.
 *
 * Example:
 *
 * $data = [];
 * $data['foo']['bar'] = 'bla';
 *
 * $accessor = new ArrayAccesor($data);
 * $value = $accessor->get('foo.bar');
 *
 * echo $value;
 *
 * the example above will output "bla"
 *
 */
class ArrayAccessor
{

    /**
     * @var array
     */
    protected $data;

    /**
     * @var string
     */
    protected $pathSeparator = ':';

    /**
     * @var bool
     */
    protected $includePathSeparatorInKeys = false;

    /**
     * @param array $data
     * @param string $pathSeparator
     * @param bool $includePathSeparatorInKeys
     */
    public function __construct(array $data = [], $pathSeparator = ':', $includePathSeparatorInKeys = false)
    {
        $this->data = $data;
        $this->pathSeparator = $pathSeparator;
        $this->includePathSeparatorInKeys = $includePathSeparatorInKeys;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param $path
     * @param mixed $defaultIfEmpty
     * @return mixed
     */
    public function get($path, $defaultIfEmpty = null)
    {
        $pathArray = $this->getPathAsArray($path);
        $pathSegmentCount = count($pathArray);

        switch ($pathSegmentCount) {
                // direct access for small paths
            case 1:
                return $this->data[$pathArray[0]] ?? $defaultIfEmpty;
            case 2:
                return $this->data[$pathArray[0]][$pathArray[1]] ?? $defaultIfEmpty;
            case 3:
                return $this->data[$pathArray[0]][$pathArray[1]][$pathArray[2]] ?? $defaultIfEmpty;
            default:
                // when we have a longer path we use a loop to get the element
                $loopResult = $this->getDeepElementWithLoop($pathArray);
                return $loopResult ?? $defaultIfEmpty;
        }
    }

    /**
     * @param $pathArray
     * @return array|null
     */
    protected function getDeepElementWithLoop($pathArray)
    {
        $currentElement = $this->data;
        foreach ($pathArray as $key => $pathSegment) {
            // if the current path segment was not found we can stop searching
            if (!isset($currentElement[$pathSegment])) {
                break;
            }
            $currentElement = $currentElement[$pathSegment];
            unset($pathArray[$key]);
        }

        // if segments are left the item does not exist
        if (count($pathArray) > 0) {
            return null;
        }

        // if no items left, we've found the last element
        return $currentElement;
    }

    /**
     * @param $path
     * @return bool
     */
    public function has($path): bool
    {
        return $this->get($path) !== null;
    }

    /**
     * @param string $path
     * @param mixed $value
     */
    public function set(string $path, $value)
    {
        $pathArray = $this->getPathAsArray($path);
        $pathSegmentCount = count($pathArray);

        switch ($pathSegmentCount) {
            // direct access for small paths
            case 1:
               $this->data[$pathArray[0]] = $value;
               return;
            case 2:
               $this->data[$pathArray[0]][$pathArray[1]] = $value;
               return;
            default:
               $this->setDeepElementWithLoop($pathArray, $value);
        }
    }

    /**
     * @param array $pathArray
     * @param mixed $value
     */
    protected function setDeepElementWithLoop(array $pathArray, $value)
    {
        $currentElement = &$this->data;
        foreach ($pathArray as $key => $pathSegment) {
            if (!isset($currentElement[$pathSegment])) {
                $currentElement[$pathSegment] = [];
            }

            unset($pathArray[$key]);
            // if segments are left the item does not exist
            if (count($pathArray) === 0) {
                $currentElement[$pathSegment] = $value;
                return;
            }

            $currentElement = &$currentElement[$pathSegment];
        }
    }

    /**
     * @param string $path
     */
    public function reset(string $path)
    {
        $pathArray = $this->getPathAsArray($path);
        $pathSegmentCount = count($pathArray);

        switch ($pathSegmentCount) {
            // direct access for small paths
            case 1:
                unset($this->data[$pathArray[0]]);
                 return;
            case 2:
                unset($this->data[$pathArray[0]][$pathArray[1]]);
                 return;
            default:
                $this->resetDeepElementWithLoop($pathArray);
        }
    }

    /**
     * @param array $pathArray
     */
    protected function resetDeepElementWithLoop(array $pathArray)
    {
        $currentElement = &$this->data;

        foreach ($pathArray as $key => $pathSegment) {
            unset($pathArray[$key]);
            // if segments are left the item does not exist
            if (count($pathArray) === 0) {
                unset($currentElement[$pathSegment]);
                // when the element is empty after unsetting the path segment, we can remove it completely
                if (empty($currentElement)) {
                    unset($currentElement);
                }
            } else {
                $currentElement = &$currentElement[$pathSegment];
            }
        }
    }

    /**
     * @param string $path
     * @return array
     */
    protected function getPathAsArray(string $path): array
    {
        if (!$this->includePathSeparatorInKeys) {
            $pathArray = explode($this->pathSeparator, $path);
            return $pathArray !== false ? $pathArray : [];
        }

        $substitutedPath = str_replace($this->pathSeparator, $this->pathSeparator . '@@@', trim($path));
        return array_filter(explode('@@@', $substitutedPath));
    }
}
