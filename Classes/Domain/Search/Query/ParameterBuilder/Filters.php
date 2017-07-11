<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Filters ParameterProvider is responsible to build the solr query parameters
 * that are needed for the filtering.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder
 */
class Filters implements ParameterBuilder, \ArrayAccess, \Countable, \IteratorAggregate
{

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * Filters constructor.
     *
     * private constructor should only be created with the from* methods
     */
    private function __construct() {}

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
            if (GeneralUtility::isFirstPartOfStr($filterString, $filterFieldName )) {
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
     * @return array
     */
    public function build()
    {
        return ['fq' => array_values($this->filters)];
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
     * @deprecated Just for backwards compatibility of the filters array, will be dropped in 8.0.
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        GeneralUtility::logDeprecatedFunction();
        if (is_null($offset)) {
            $this->filters[] = $value;
        } else {
            $this->filters[$offset] = $value;
        }
    }

    /**
     * @deprecated Just for backwards compatibility of the filters array, will be dropped in 8.0.
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        GeneralUtility::logDeprecatedFunction();
        return isset($this->filters[$offset]);
    }

    /**
     * @deprecated Just for backwards compatibility of the filters array, will be dropped in 8.0.
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        GeneralUtility::logDeprecatedFunction();
        unset($this->filters[$offset]);
    }

    /**
     * @deprecated Just for backwards compatibility of the filters array, will be dropped in 8.0.
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        GeneralUtility::logDeprecatedFunction();
        return isset($this->filters[$offset]) ? $this->filters[$offset] : null;
    }

    /**
     * @deprecated Just for backwards compatibility of the filters array, will be dropped in 8.0.
     * @return int
     */
    public function count()
    {
        GeneralUtility::logDeprecatedFunction();
        return count($this->filters);
    }

    /**
     * @deprecated Just for backwards compatibility of the filters array, will be dropped in 8.0.
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        GeneralUtility::logDeprecatedFunction();
        return new \ArrayIterator($this->filters);
    }
}