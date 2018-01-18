<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The QueryParameterContainer is responsible to hold all parameters that are needed to build a solr query.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Query
 */
class QueryParametersContainer {


    /**
     * @var array
     */
    protected $queryParameters = [];

    /**
     * This method can be used to set a query parameter when the value is a string and not empty or unset it
     * in any other case. Extracted to avoid duplicate code.
     *
     * @param string $parameterName
     * @param mixed $value
     */
    public function setWhenStringOrUnsetWhenEmpty($parameterName, $value)
    {
        if (is_string($value) && !empty($value)) {
            $this->set($parameterName, $value);
        } else {
            unset($this->queryParameters[$parameterName]);
        }
    }

    /**
     * This method can be used to set a query parameter when the value is a int and not empty or unset it
     * in any other case. Extracted to avoid duplicate code.
     *
     * @param string $parameterName
     * @param int $value
     */
    public function setWhenIntOrUnsetWhenNull(string $parameterName, int $value = null)
    {
        if (null === $value) {
            unset($this->queryParameters[$parameterName]);
            return;
        }
        $this->set($parameterName, $value);
    }

    /**
     * Adds any generic query parameter.
     *
     * @param string $parameterName Query parameter name
     * @param mixed $parameterValue Parameter value
     */
    public function set($parameterName, $parameterValue)
    {
        $this->queryParameters[$parameterName] = $parameterValue;
    }

    /**
     * Removes a queryParameter.
     *
     * @param mixed $parameterName
     */
    public function remove($parameterName)
    {
        unset($this->queryParameters[$parameterName]);
    }

    /**
     * Removes multiple query parameters by name
     *
     * @param array $parameterNames
     */
    public function removeMany(array $parameterNames)
    {
        foreach ($parameterNames as $parameterName) {
            $this->remove($parameterName);
        }
    }

    /**
     * @param string $prefix
     */
    public function removeByPrefix($prefix)
    {
        foreach ($this->queryParameters as $parameterName => $parameterValue) {
            if (GeneralUtility::isFirstPartOfStr($parameterName, $prefix)) {
                unset($this->queryParameters[$parameterName]);
            }
        }
    }

    /**
     * Returns a queryParameter
     * @param string $parameterName
     * @return mixed
     */
    public function get($parameterName)
    {
        return $this->queryParameters[$parameterName];
    }

    /**
     * @param array $toMerge
     */
    public function merge(array $toMerge)
    {
        $this->queryParameters = array_merge($this->queryParameters, $toMerge);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->queryParameters;
    }
}