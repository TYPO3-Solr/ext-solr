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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * The searchRequest is used to act as an api to the arguments that have been passed
 * with GET and POST.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchRequest
{
    /**
     * @var string
     */
    protected $id;

    /**
     * Default namespace overwritten with the configured plugin namespace.
     *
     * @var string
     */
    protected $argumentNameSpace = 'tx_solr';

    /**
     * Arguments that should be kept for sub requests.
     *
     * Default values, overwritten in the constructor with the namespaced arguments
     *
     * @var array
     */
    protected $persistentArgumentsPaths = ['tx_solr:q', 'tx_solr:filter', 'tx_solr:sort'];

    /**
     * @var bool
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
     * @var int
     */
    protected $contextSystemLanguageUid;

    /**
     * The page_uid that was used in the context where the request was build.
     *
     * The pageUid is not relevant for the typolink additionalArguments and therefore
     * a separate property.
     *
     * @var int
     */
    protected $contextPageUid;

    /**
     * @var TypoScriptConfiguration
     */
    protected $contextTypoScriptConfiguration;

    /**
     * @var array
     */
    protected $persistedArguments = [];

    /**
     * @param array $argumentsArray
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @param TypoScriptConfiguration $typoScriptConfiguration
     */
    public function __construct(array $argumentsArray = [], $pageUid = 0, $sysLanguageUid = 0, TypoScriptConfiguration $typoScriptConfiguration = null)
    {
        $this->stateChanged = true;
        $this->persistedArguments = $argumentsArray;
        $this->contextPageUid = $pageUid;
        $this->contextSystemLanguageUid = $sysLanguageUid;
        $this->contextTypoScriptConfiguration = $typoScriptConfiguration;
        $this->id = spl_object_hash($this);

        // overwrite the plugin namespace and the persistentArgumentsPaths
        if (!is_null($typoScriptConfiguration)) {
            $this->argumentNameSpace = $typoScriptConfiguration->getSearchPluginNamespace();
        }

        $this->persistentArgumentsPaths = [$this->argumentNameSpace . ':q', $this->argumentNameSpace . ':filter', $this->argumentNameSpace . ':sort', $this->argumentNameSpace . ':groupPage'];

        if (!is_null($typoScriptConfiguration)) {
            $additionalPersistentArgumentsNames = $typoScriptConfiguration->getSearchAdditionalPersistentArgumentNames();
            foreach ($additionalPersistentArgumentsNames ?? [] as $additionalPersistentArgumentsName) {
                $this->persistentArgumentsPaths[] = $this->argumentNameSpace . ':' . $additionalPersistentArgumentsName;
            }
            $this->persistentArgumentsPaths = array_unique($this->persistentArgumentsPaths);
        }

        $this->reset();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
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
     * @param string $path
     * @return string
     */
    protected function prefixWithNamespace($path)
    {
        return $this->argumentNameSpace . ':' . $path;
    }

    /**
     * @return array
     */
    public function getActiveFacetNames()
    {
        $activeFacets = $this->getActiveFacets();
        $facetNames = [];

        array_map(function($activeFacet) use (&$facetNames) {
            $facetNames[] = substr($activeFacet, 0, strpos($activeFacet, ':'));
        }, $activeFacets);

        return $facetNames;
    }

    /**
     * Returns all facet values for a certain facetName
     * @param string $facetName
     * @return array
     */
    public function getActiveFacetValuesByName($facetName)
    {
        $values = [];
        $activeFacets = $this->getActiveFacets();

        array_map(function($activeFacet) use (&$values, $facetName) {
            $parts = explode(':', $activeFacet, 2);
            if ($parts[0] === $facetName) {
                $values[] = $parts[1];
            }
        }, $activeFacets);

        return $values;
    }

    /**
     * @return array
     */
    public function getActiveFacets()
    {
        $path = $this->prefixWithNamespace('filter');
        $pathValue = $this->argumentsAccessor->get($path, []);

        return is_array($pathValue) ? $pathValue : [];
    }

    /**
     * @return int
     */
    public function getActiveFacetCount()
    {
        return count($this->getActiveFacets());
    }

    /**
     * @param $activeFacets
     *
     * @return SearchRequest
     */
    protected function setActiveFacets($activeFacets = [])
    {
        $path = $this->prefixWithNamespace('filter');
        $this->argumentsAccessor->set($path, $activeFacets);

        return $this;
    }

    /**
     * Adds a facet value to the request.
     *
     * @param string $facetName
     * @param mixed $facetValue
     *
     * @return SearchRequest
     */
    public function addFacetValue($facetName, $facetValue)
    {
        if ($this->getHasFacetValue($facetName, $facetValue)) {
            return $this;
        }

        $facetValues = $this->getActiveFacets();
        $facetValues[] = $facetName . ':' . $facetValue;
        $this->setActiveFacets($facetValues);

        $this->stateChanged = true;
        return $this;
    }

    /**
     * Removes a facet value from the request.
     *
     * @param string $facetName
     * @param mixed $facetValue
     *
     * @return SearchRequest
     */
    public function removeFacetValue($facetName, $facetValue)
    {
        if (!$this->getHasFacetValue($facetName, $facetValue)) {
            return $this;
        }
        $facetValues = $this->getActiveFacets();
        $facetValueToLookFor = $facetName . ':' . $facetValue;

        foreach ($facetValues as $index => $facetValue) {
            if ($facetValue === $facetValueToLookFor) {
                unset($facetValues[$index]);
                break;
            }
        }

        $this->setActiveFacets($facetValues);
        $this->stateChanged = true;
        return $this;
    }

    /**
     * Removes all facet values from the request by a certain facet name
     *
     * @param string $facetName
     *
     * @return SearchRequest
     */
    public function removeAllFacetValuesByName($facetName)
    {
        $facetValues = $this->getActiveFacets();
        $facetValues = array_filter($facetValues, function($facetValue) use ($facetName) {
            $parts = explode(':', $facetValue, 2);
            return $parts[0] !== $facetName;
        });

        $this->setActiveFacets($facetValues);
        $this->stateChanged = true;
        return $this;
    }

    /**
     * Removes all active facets from the request.
     *
     * @return SearchRequest
     */
    public function removeAllFacets()
    {
        $path = $this->prefixWithNamespace('filter');
        $this->argumentsAccessor->reset($path);
        $this->stateChanged = true;
        return $this;
    }

    /**
     * @param string $facetName
     * @param mixed $facetValue
     * @return bool
     */
    public function getHasFacetValue($facetName, $facetValue)
    {
        $facetNameAndValueToCheck = $facetName . ':' . $facetValue;
        return in_array($facetNameAndValueToCheck, $this->getActiveFacets());
    }

    /**
     * @return bool
     */
    public function getHasSorting()
    {
        $path = $this->prefixWithNamespace('sort');
        return $this->argumentsAccessor->has($path);
    }

    /**
     * Returns the sorting string in the url e.g. title asc.
     *
     * @return string
     */
    public function getSorting()
    {
        $path = $this->prefixWithNamespace('sort');
        return $this->argumentsAccessor->get($path, '');
    }

    /**
     * Helper function to get the sorting configuration name or direction.
     *
     * @param int $index
     * @return string
     */
    protected function getSortingPart($index)
    {
        $sorting = $this->getSorting();
        if ($sorting === '') {
            return null;
        }

        $parts = explode(' ', $sorting);
        return isset($parts[$index]) ? $parts[$index] : null;
    }

    /**
     * Returns the sorting configuration name that is currently used.
     *
     * @return string
     */
    public function getSortingName()
    {
        return $this->getSortingPart(0);
    }

    /**
     * Returns the sorting direction that is currently used.
     *
     * @return string
     */
    public function getSortingDirection()
    {
        return mb_strtolower($this->getSortingPart(1));
    }

    /**
     * @return SearchRequest
     */
    public function removeSorting()
    {
        $path = $this->prefixWithNamespace('sort');
        $this->argumentsAccessor->reset($path);
        $this->stateChanged = true;
        return $this;
    }

    /**
     * @param string $sortingName
     * @param string $direction (asc or desc)
     *
     * @return SearchRequest
     */
    public function setSorting($sortingName, $direction = 'asc')
    {
        $value = $sortingName . ' ' . $direction;
        $path = $this->prefixWithNamespace('sort');
        $this->argumentsAccessor->set($path, $value);
        $this->stateChanged = true;
        return $this;
    }

    /**
     * Method to set the paginated page of the search
     *
     * @param int $page
     * @return SearchRequest
     */
    public function setPage($page)
    {
        $this->stateChanged = true;
        $path = $this->prefixWithNamespace('page');
        $this->argumentsAccessor->set($path, $page);
        // use initial url by switching back to page 0
        if ($page === 0) {
            $this->argumentsAccessor->reset($path);
        }
        return $this;
    }

    /**
     * Returns the passed page.
     *
     * @return int|null
     */
    public function getPage()
    {
        $path = $this->prefixWithNamespace('page');
        return $this->argumentsAccessor->get($path);
    }

    /**
     * Can be used to reset all groupPages.
     *
     * @return SearchRequest
     */
    public function removeAllGroupItemPages(): SearchRequest
    {
        $path = $this->prefixWithNamespace('groupPage');
        $this->argumentsAccessor->reset($path);

        return $this;
    }

    /**
     * Can be used to paginate within a groupItem.
     *
     * @param string $groupName e.g. type
     * @param string $groupItemValue e.g. pages
     * @param int $page
     * @return SearchRequest
     */
    public function setGroupItemPage(string $groupName, string $groupItemValue, int $page): SearchRequest
    {
        $this->stateChanged = true;
        $escapedValue = $this->getEscapedGroupItemValue($groupItemValue);
        $path = $this->prefixWithNamespace('groupPage:' . $groupName . ':' . $escapedValue);
        $this->argumentsAccessor->set($path, $page);
        return $this;
    }

    /**
     * Retrieves the current page for this group item.
     *
     * @param string $groupName
     * @param string $groupItemValue
     * @return int
     */
    public function getGroupItemPage(string $groupName, string $groupItemValue): int
    {
        $escapedValue = $this->getEscapedGroupItemValue($groupItemValue);
        $path = $this->prefixWithNamespace('groupPage:' . $groupName . ':' . $escapedValue);
        return max(1, (int)$this->argumentsAccessor->get($path));
    }

    /**
     * Removes all non alphanumeric values from the groupItem value to have a valid array key.
     *
     * @param string $groupItemValue
     * @return string
     */
    protected function getEscapedGroupItemValue(string $groupItemValue)
    {
        return preg_replace("/[^A-Za-z0-9]/", '', $groupItemValue);
    }

    /**
     * Retrieves the highest page of the groups.
     *
     * @return int
     */
    public function getHighestGroupPage()
    {
        $max = 1;
        $path = $this->prefixWithNamespace('groupPage');
        $groupPages = $this->argumentsAccessor->get($path, []);
        foreach ($groupPages as $groups) {
            if (!is_array($groups)) continue;
            foreach ($groups as $groupItemPage) {
                if ($groupItemPage > $max) {
                    $max = $groupItemPage;
                }
            }
        }

        return $max;
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
        $path = $this->prefixWithNamespace('q');
        $this->argumentsAccessor->set($path, $rawQueryString);
        return $this;
    }

    /**
     * Returns the passed rawQueryString.
     *
     * @return string|null
     */
    public function getRawUserQuery()
    {
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path, null);
        return is_null($query) ? $query : (string)$query;
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
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path, null);

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
     * @return bool
     */
    public function getRawUserQueryIsNull()
    {
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path, null);
        return $query === null;
    }

    /**
     * Sets the results per page that are used during search.
     *
     * @param int $resultsPerPage
     * @return SearchRequest
     */
    public function setResultsPerPage($resultsPerPage)
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        $this->argumentsAccessor->set($path, $resultsPerPage);
        $this->stateChanged = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function getStateChanged()
    {
        return $this->stateChanged;
    }

    /**
     * Returns the passed resultsPerPage value
     * @return int|null
     */
    public function getResultsPerPage()
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        return $this->argumentsAccessor->get($path);
    }

    /**
     * Allows to set additional filters that are used on time and not transported during the request.
     *
     * @param array $additionalFilters
     * @return SearchRequest
     */
    public function setAdditionalFilters($additionalFilters)
    {
        $path = $this->prefixWithNamespace('additionalFilters');
        $this->argumentsAccessor->set($path, $additionalFilters);
        $this->stateChanged = true;

        return $this;
    }

    /**
     * Retrieves the addtional filters that have been set
     *
     * @return array
     */
    public function getAdditionalFilters()
    {
        $path = $this->prefixWithNamespace('additionalFilters');
        return $this->argumentsAccessor->get($path, []);
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
        $this->stateChanged = false;
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
        if (!$onlyPersistentArguments) {
            // create a new request with all data
            $argumentsArray = $this->argumentsAccessor->getData();
            return new SearchRequest(
                $argumentsArray,
                $this->contextPageUid,
                $this->contextSystemLanguageUid,
                $this->contextTypoScriptConfiguration
            );
        }

        $arguments = new ArrayAccessor();
        foreach ($this->persistentArgumentsPaths as $persistentArgumentPath) {
            if ($this->argumentsAccessor->has($persistentArgumentPath)) {
                $arguments->set($persistentArgumentPath, $this->argumentsAccessor->get($persistentArgumentPath));
            }
        }

        return new SearchRequest(
            $arguments->getData(),
            $this->contextPageUid,
            $this->contextSystemLanguageUid,
            $this->contextTypoScriptConfiguration
        );
    }

    /**
     * @return string
     */
    public function getArgumentNameSpace()
    {
        return $this->argumentNameSpace;
    }

    /**
     * @return array
     */
    public function getAsArray()
    {
        return $this->argumentsAccessor->getData();
    }

    /**
     * Returns only the arguments as array.
     *
     * @return array
     */
    public function getArguments() {
        return $this->argumentsAccessor->get($this->argumentNameSpace, []);
    }
}
