<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * The searchRequest is used to act as an api to the arguments that have been passed
 * with GET and POST.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class SearchRequest implements SingletonInterface
{

    /**
     * @var array
     */
    protected $arguments = array();

    /**
     * @param array $arguments
     */
    public function __construct($arguments = array())
    {
        $this->arguments = $arguments;
    }

    /**
     * Can be used do merge arguments into the request arguments
     *
     * @param array $argumentsToMerge
     * @return SearchRequest
     */
    public function mergeArguments(array $argumentsToMerge)
    {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->arguments,
            $argumentsToMerge
        );

        return $this;
    }

    /**
     * @param $key
     * @param null $defaultValue
     * @return null
     */
    protected function getArgumentByKey($key, $defaultValue = null)
    {
        return isset($this->arguments[$key]) ? $this->arguments[$key] : $defaultValue;
    }

    /**
     * Returns the passed page.
     *
     * @return integer|null
     */
    public function getPage()
    {
        return $this->getArgumentByKey('page');
    }

    /**
     * Returns the passed rawQueryString.
     *
     * @return integer|string
     */
    public function getRawUserQuery()
    {
        return $this->getArgumentByKey('q');
    }

    /**
     * Method to overwrite the query string.
     *
     * @param string $rawQueryString
     */
    public function setRawQueryString($rawQueryString)
    {
        $this->arguments['q'] = $rawQueryString;
    }

    /**
     * Method to check if the query string is an empty string
     * (also empty string or whitespaces only are handled as empty).
     *
     * When no query string is set (null) the method returns false.
     * @return bool
     */
    public function getRawUserQueryIsEmptyString()
    {
        $query = $this->getArgumentByKey('q', null);

        if ($query === null) {
            return false;
        }

        if (trim($query) === '') {
            return true;
        }

        return false;
    }

    /**
     * This method returns true when no querystring is present at all.
     * Which means no search by the user was triggered
     *
     * @return boolean
     */
    public function getRawUserQueryIsNull()
    {
        $query = $this->getArgumentByKey('q', null);
        return $query === null;
    }

    /**
     * Returns the passed resultsPerPage value
     * @return integer|null
     */
    public function getResultsPerPage()
    {
        return $this->getArgumentByKey('resultsPerPage');
    }
}
