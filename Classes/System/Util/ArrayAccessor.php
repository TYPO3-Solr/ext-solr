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
 * $accessor = new ArrayAccessor($data);
 * $value = $accessor->get('foo.bar');
 *
 * echo $value;
 *
 * the example above will output "bla"
 */
class ArrayAccessor
{
    protected ?array $data = [];

    protected string $pathSeparator = ':';

    protected bool $includePathSeparatorInKeys = false;

    public function __construct(
        array $data = [],
        string $pathSeparator = ':',
        bool $includePathSeparatorInKeys = false,
    ) {
        $this->data = $data;
        $this->pathSeparator = $pathSeparator;
        $this->includePathSeparatorInKeys = $includePathSeparatorInKeys;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $path, mixed $defaultIfEmpty = null): mixed
    {
        $pathArray = $this->getPathAsArray($path);
        $pathSegmentCount = count($pathArray);

        // direct access for small paths
        switch ($pathSegmentCount) {
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

    protected function getDeepElementWithLoop(array $pathArray): mixed
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

    public function has(string $path): bool
    {
        return $this->get($path) !== null;
    }

    public function set(string $path, mixed $value): void
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

    protected function setDeepElementWithLoop(array $pathArray, mixed $value): void
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

    public function reset(string $path): void
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

    protected function resetDeepElementWithLoop(array $pathArray): void
    {
        $currentElement = &$this->data;

        foreach ($pathArray as $key => $pathSegment) {
            unset($pathArray[$key]);
            // if segments are left the item does not exist
            if (count($pathArray) === 0) {
                /** @noinspection PhpUndefinedVariableInspection */
                unset($currentElement[$pathSegment]);
                // when the element is empty after unsetting the path segment, we can remove it completely
                if (empty($currentElement)) {
                    unset($currentElement);
                }
            } elseif (isset($currentElement[$pathSegment])) {
                $currentElement = &$currentElement[$pathSegment];
            }
        }
    }

    protected function getPathAsArray(string $path): array
    {
        if (!$this->includePathSeparatorInKeys) {
            return explode($this->pathSeparator, $path);
        }

        $substitutedPath = str_replace($this->pathSeparator, $this->pathSeparator . '@@@', trim($path));
        return array_filter(explode('@@@', $substitutedPath));
    }
}
