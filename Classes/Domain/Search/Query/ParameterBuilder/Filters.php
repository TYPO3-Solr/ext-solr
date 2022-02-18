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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Filters ParameterProvider is responsible to build the solr query parameters
 * that are needed for the filtering.
 */
class Filters
{

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * Removes a filter on a field
     *
     * @param string $filterFieldName The field name the filter should be removed for
     * @return void
     */
    public function removeByFieldName($filterFieldName)
    {
        $this->removeByPrefix($filterFieldName . ':');
    }

    /**
     * @param string $filterFieldName
     */
    public function removeByPrefix($filterFieldName)
    {
        foreach ($this->filters as $key => $filterString) {
            if (\str_starts_with($filterString, $filterFieldName)) {
                unset($this->filters[$key]);
            }
        }
    }

    /**
     * Removes a filter based on name of filter array
     *
     * @param string $name name of the filter
     */
    public function removeByName($name)
    {
        unset($this->filters[$name]);
    }


    /**
     * @param string $filterString
     * @param string $name
     */
    public function add($filterString, $name = '')
    {
        if ($name !== '') {
            $this->filters[$name] = $filterString;
        } else {
            $this->filters[] = $filterString;
        }
    }

    /**
     * Add's multiple filters to the filter collection.
     *
     * @param array $filterArray
     * @return Filters
     */
    public function addMultiple($filterArray)
    {
        foreach($filterArray as $key => $value) {
            if (!$this->hasWithName($key)) {
                $this->add($value, $key);
            }
        }

        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasWithName($name)
    {
        return array_key_exists($name, $this->filters);
    }

    /**
     * Removes a filter by the filter value. The value has the following format:
     *
     * "fieldname:value"
     *
     * @param string $filterString The filter to remove, in the form of field:value
     */
    public function removeByValue($filterString)
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
     *
     * @return array Array of filters
     */
    public function getValues()
    {
        return $this->filters;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return Filters
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        return new Filters();
    }

    /**
     * @return Filters
     */
    public static function getEmpty()
    {
        return new Filters();
    }
}
