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
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The searchRequest is used to act as an api to the arguments that have been passed
 * with GET and POST.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class SearchRequest
{

    /**
     * @var string
     */
    protected $argumentNameSpace = 'tx_solr';

    /**
     * Arguments that should be kept for sub requests.
     *
     * @var array
     */
    protected $persistentArgumentsPaths = array('q', 'tx_solr:filter');

    /**
     * @var boolean
     */
    protected $stateChanged = false;

    /**
     * @var ArrayAccessor
     */
    protected $argumentsAccessor;

    /**
     * The sys_language_uid that was used in the context where the request was build.
     * This could be different from the "L" parameter and and not relevant for urls,
     * because typolink itself will handle it.
     *
     * @var integer
     */
    protected $contextSystemLanguageUid;

    /**
     * The page_uid that was used in the context where the request was build.
     *
     * The pageUid is not relevant for the typolink additionalArguments and therefore
     * a separate property.
     *
     * @var integer
     */
    protected $contextPageUid;

    /**
     * @var TypoScriptConfiguration
     */
    protected $contextTypoScriptConfiguration;

    /**
     * @param array $argumentsArray
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @param TypoScriptConfiguration $typoScriptConfiguration
     */
    public function __construct(array $argumentsArray = array(), $pageUid = 0, $sysLanguageUid = 0, TypoScriptConfiguration $typoScriptConfiguration = null)
    {
        $this->stateChanged = true;
        $this->persistedArguments = $argumentsArray;
        $this->contextPageUid = $pageUid;
        $this->contextSystemLanguageUid = $sysLanguageUid;
        $this->contextTypoScriptConfiguration = $typoScriptConfiguration;

        $this->reset();
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
            $this->persistedArguments,
            $argumentsToMerge
        );

        $this->reset();

        return $this;
    }

    /**
     * Helper method to prefix an accessor with the arguments namespace.
     *
     * @param $path
     * @return string
     */
    protected function prefixWithNamespace($path)
    {
        return $this->argumentNameSpace . ':'.$path;
    }

    /**
     * @return array
     */
    public function getActiveFacetNames()
    {
        $activeFacets = $this->getActiveFacets();
        $facetNames = array();
        foreach ($activeFacets as $activeFacet) {
            $facetName = explode(':', $activeFacet, 2);
            $facetNames[] = $facetName[0];
        }

        return $facetNames;
    }

    /**
     * @return array|null
     */
    protected function getActiveFacets()
    {
        $path = $this->prefixWithNamespace('filter');
        return $this->argumentsAccessor->get($path, array());
    }

    /**
     * @param $activeFacets
     * @return array|null
     *
     * @return SearchRequest
     */
    protected function setActiveFacets($activeFacets = array())
    {
        $path = $this->prefixWithNamespace('filter');
        $this->argumentsAccessor->set($path, $activeFacets);

        return $this;
    }

    /**
     * @param string $facetName
     * @param mixed $facetValue
     *
     * @return SearchRequest
     */
    public function addFacetValue($facetName, $facetValue)
    {
        $this->stateChanged = true;
        if ($this->hasFacetValue($facetName, $facetValue)) {
            return $this;
        }

        $facetValues = $this->getActiveFacets();
        $facetValues[] = $facetName.':'.$facetValue;
        $this->setActiveFacets($facetValues);

        return $this;
    }

    /**
     * @param string $facetName
     * @param mixed $facetValue
     * @return boolean
     */
    public function hasFacetValue($facetName, $facetValue)
    {
        $facetNameAndValueToCheck = $facetName.':'.$facetValue;
        foreach ($this->getActiveFacets() as $activeFacet) {
            if ($activeFacet == $facetNameAndValueToCheck) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method to set the paginated page of the search
     *
     * @param integer $page
     * @return SearchRequest
     */
    public function setPage($page)
    {
        $this->stateChanged = true;
        $path = $this->prefixWithNamespace('page');
        $this->argumentsAccessor->set($path, $page);
        return $this;
    }


    /**
     * Returns the passed page.
     *
     * @return integer|null
     */
    public function getPage()
    {
        $path = $this->prefixWithNamespace('page');
        return $this->argumentsAccessor->get($path);
    }

    /**
     * Method to overwrite the query string.
     *
     * @param string $rawQueryString
     * @return SearchRequest
     */
    public function setRawQueryString($rawQueryString)
    {
        $this->stateChanged = true;
        $this->argumentsAccessor->set('q', $rawQueryString);
        return $this;
    }

    /**
     * Returns the passed rawQueryString.
     *
     * @return integer|string
     */
    public function getRawUserQuery()
    {
        return $this->argumentsAccessor->get('q');
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
        $query = $this->argumentsAccessor->get('q', null);

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
        $query = $this->argumentsAccessor->get('q', null);
        return $query === null;
    }

    /**
     * Sets the results per page that are used during search.
     *
     * @param integer $resultsPerPage
     * @return SearchRequest
     */
    public function setResultsPerPage($resultsPerPage)
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        $this->argumentsAccessor->set($path, $resultsPerPage);

        return $this;
    }

    /**
     * Returns the passed resultsPerPage value
     * @return integer|null
     */
    public function getResultsPerPage()
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        return $this->argumentsAccessor->get($path);
    }

    /**
     * @return int
     */
    public function getContextSystemLanguageUid()
    {
        return $this->contextSystemLanguageUid;
    }

    /**
     * @return int
     */
    public function getContextPageUid()
    {
        return $this->contextPageUid;
    }

    /**
     * Get contextTypoScriptConfiguration
     *
     * @return TypoScriptConfiguration
     */
    public function getContextTypoScriptConfiguration()
    {
        return $this->contextTypoScriptConfiguration;
    }

    /**
     * Assigns the last known persistedArguments and restores their state.
     *
     * @return SearchRequest
     */
    public function reset()
    {
        $this->argumentsAccessor = new ArrayAccessor($this->persistedArguments);
        return $this;
    }

    /**
     * This can be used to start a new sub request, e.g. for a faceted search.
     *
     * @param bool $onlyPersistentArguments
     * @return SearchRequest
     */
    public function getCopyForSubRequest($onlyPersistentArguments = true)
    {
        $argumentsArray = $this->argumentsAccessor->getData();
        if ($onlyPersistentArguments) {
            $arguments = new ArrayAccessor();
            foreach ($this->persistentArgumentsPaths as $persistentArgumentPath) {
                if ($this->argumentsAccessor->has($persistentArgumentPath)) {
                    $arguments->set($persistentArgumentPath, $this->argumentsAccessor->get($persistentArgumentPath));
                }
            }

            $argumentsArray = $arguments->getData();
        }

        return new SearchRequest($argumentsArray);
    }

    /**
     * @return array
     */
    public function getAsArray()
    {
        return $this->argumentsAccessor->getData();
    }
}
