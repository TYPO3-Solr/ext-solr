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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

use function str_starts_with;

/**
 * The Filters ParameterProvider is responsible to build the solr query parameters
 * that are needed for the filtering.
 */
class Filters
{
    protected array $filters = [];

    /**
     * Removes a filter on a field from field name the filter should be removed for
     */
    public function removeByFieldName(string $filterFieldName): void
    {
        $this->removeByPrefix($filterFieldName . ':');
    }

    public function removeByPrefix(string $filterFieldName): void
    {
        foreach ($this->filters as $key => $filterString) {
            if (str_starts_with($filterString, $filterFieldName)) {
                unset($this->filters[$key]);
            }
        }
    }

    /**
     * Removes a filter based on name of filter array
     */
    public function removeByName(string $name): void
    {
        unset($this->filters[$name]);
    }

    /**
     * Adds filter. Named if name given, incremental if name not defined.
     */
    public function add(string $filterString, string $name = ''): void
    {
        if ($name !== '') {
            $this->filters[$name] = $filterString;
        } else {
            $this->filters[] = $filterString;
        }
    }

    /**
     * Adds multiple filters to the filter collection.
     */
    public function addMultiple(array $filterArray): Filters
    {
        foreach ($filterArray as $key => $value) {
            if (!$this->hasWithName($key)) {
                $this->add($value, $key);
            }
        }

        return $this;
    }

    /**
     * Checks if named filter exists.
     */
    public function hasWithName(string $name): bool
    {
        return array_key_exists($name, $this->filters);
    }

    /**
     * Removes a filter by the filter value. The value has the following format:
     *
     * "fieldname:value"
     */
    public function removeByValue(string $filterString): void
    {
        $key = array_search($filterString, $this->filters);
        if ($key === false) {
            // value not found, nothing to do
            return;
        }
        unset($this->filters[$key]);
    }

    /**
     * Gets all currently applied filters.
     */
    public function getValues(): array
    {
        return $this->filters;
    }

    /**
     * @todo: Check why $solrConfiguration isn't used.
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): Filters
    {
        return new self();
    }

    /**
     * Returns new clean/empty instance of Filters.
     */
    public static function getEmpty(): Filters
    {
        return new self();
    }
}
