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

namespace ApacheSolrForTypo3\Solr\Domain\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
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
     * The default plugin namespace.
     *
     * @var string
     */
    const DEFAULT_PLUGIN_NAMESPACE = 'tx_solr';

    /**
     * @var string
     */
    protected string $id;

    /**
     * Default namespace overwritten with the configured plugin namespace.
     *
     * @var string
     */
    protected string $argumentNameSpace = self::DEFAULT_PLUGIN_NAMESPACE;

    /**
     * Arguments that should be kept for sub requests.
     *
     * Default values, overwritten in the constructor with the namespaced arguments
     *
     * @var array
     */
    protected array $persistentArgumentsPaths = [
        'tx_solr:q',
        'tx_solr:filter',
        'tx_solr:sort',
    ];

    /**
     * @var bool
     */
    protected bool $stateChanged = false;

    /**
     * @var ArrayAccessor|null
     */
    protected ?ArrayAccessor $argumentsAccessor = null;

    /**
     * The sys_language_uid that was used in the context where the request was build.
     * This could be different from the "L" parameter and not relevant for urls,
     * because typolink itself will handle it.
     *
     * @var int
     */
    protected int $contextSystemLanguageUid = 0;

    /**
     * The page_uid that was used in the context where the request was build.
     *
     * The pageUid is not relevant for the typolink additionalArguments and therefore
     * a separate property.
     *
     * @var int
     */
    protected int $contextPageUid;

    /**
     * @var TypoScriptConfiguration|null
     */
    protected ?TypoScriptConfiguration $contextTypoScriptConfiguration;

    /**
     * Container for all active facets inside the URL(TYPO3/FE)
     *
     * @var UrlFacetContainer|null
     */
    protected ?UrlFacetContainer $activeFacetContainer;

    /**
     * @var array
     */
    protected array $persistedArguments = [];

    /**
     * @param array $argumentsArray
     * @param int $pageUid
     * @param int $sysLanguageUid
     * @param TypoScriptConfiguration|null $typoScriptConfiguration
     */
    public function __construct(
        array $argumentsArray = [],
        int $pageUid = 0,
        int $sysLanguageUid = 0,
        TypoScriptConfiguration $typoScriptConfiguration = null
    ) {
        $this->stateChanged = true;
        $this->persistedArguments = $argumentsArray;
        $this->contextPageUid = $pageUid;
        $this->contextSystemLanguageUid = $sysLanguageUid;
        $this->contextTypoScriptConfiguration = $typoScriptConfiguration;
        $this->id = spl_object_hash($this);

        // overwrite the plugin namespace and the persistentArgumentsPaths
        if (!is_null($typoScriptConfiguration)) {
            $this->argumentNameSpace = $typoScriptConfiguration->getSearchPluginNamespace() ?? self::DEFAULT_PLUGIN_NAMESPACE;
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
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Can be used do merge arguments into the request arguments
     *
     * @param array $argumentsToMerge
     * @return SearchRequest
     */
    public function mergeArguments(array $argumentsToMerge): SearchRequest
    {
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->persistedArguments,
            $argumentsToMerge
        );

        $this->reset();

        return $this;
    }

    /**
     * Helper method to prefix an accessor with the argument's namespace.
     *
     * @param string $path
     * @return string
     */
    protected function prefixWithNamespace(string $path): string
    {
        return $this->argumentNameSpace . ':' . $path;
    }

    /**
     * @return array
     */
    public function getActiveFacetNames(): array
    {
        return $this->activeFacetContainer->getActiveFacetNames();
    }

    /**
     * Returns all facet values for a certain facetName
     * @param string $facetName
     * @return array
     */
    public function getActiveFacetValuesByName(string $facetName): array
    {
        return $this->activeFacetContainer->getActiveFacetValuesByName($facetName);
    }

    /**
     * @return array
     */
    public function getActiveFacets(): array
    {
        return $this->activeFacetContainer->getActiveFacets();
    }

    /**
     * Enable sorting of URL parameters
     * @noinspection PhpUnused
     */
    public function sortActiveFacets(): void
    {
        $this->activeFacetContainer->enableSort();
    }

    /**
     * @return bool
     */
    public function isActiveFacetsSorted(): bool
    {
        return $this->activeFacetContainer->isSorted();
    }

    /**
     * @return string
     */
    public function getActiveFacetsUrlParameterStyle(): string
    {
        return $this->activeFacetContainer->getParameterStyle();
    }

    /**
     * Returns the active count of facets
     *
     * @return int
     */
    public function getActiveFacetCount(): int
    {
        return $this->activeFacetContainer->count();
    }

    /**
     * @param array $activeFacets
     *
     * @return SearchRequest
     * @noinspection PhpUnused
     */
    protected function setActiveFacets(array $activeFacets = []): SearchRequest
    {
        $this->activeFacetContainer->setActiveFacets($activeFacets);

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
    public function addFacetValue(string $facetName, $facetValue): SearchRequest
    {
        $this->activeFacetContainer->addFacetValue($facetName, $facetValue);

        if ($this->activeFacetContainer->hasChanged()) {
            $this->stateChanged = true;
            $this->activeFacetContainer->acknowledgeChange();
        }

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
    public function removeFacetValue(string $facetName, $facetValue): SearchRequest
    {
        $this->activeFacetContainer->removeFacetValue($facetName, $facetValue);
        if ($this->activeFacetContainer->hasChanged()) {
            $this->stateChanged = true;
            $this->activeFacetContainer->acknowledgeChange();
        }

        return $this;
    }

    /**
     * Removes all facet values from the request by a certain facet name
     *
     * @param string $facetName
     *
     * @return SearchRequest
     */
    public function removeAllFacetValuesByName(string $facetName): SearchRequest
    {
        $this->activeFacetContainer->removeAllFacetValuesByName($facetName);
        if ($this->activeFacetContainer->hasChanged()) {
            $this->stateChanged = true;
            $this->activeFacetContainer->acknowledgeChange();
        }
        return $this;
    }

    /**
     * Removes all active facets from the request.
     *
     * @return SearchRequest
     */
    public function removeAllFacets(): SearchRequest
    {
        $this->activeFacetContainer->removeAllFacets();
        if ($this->activeFacetContainer->hasChanged()) {
            $this->stateChanged = true;
            $this->activeFacetContainer->acknowledgeChange();
        }
        return $this;
    }

    /**
     * Check if an active facet has a given value
     *
     * @param string $facetName
     * @param mixed $facetValue
     * @return bool
     */
    public function getHasFacetValue(string $facetName, $facetValue): bool
    {
        return $this->activeFacetContainer->hasFacetValue($facetName, $facetValue);
    }

    /**
     * @return bool
     */
    public function getHasSorting(): bool
    {
        $path = $this->prefixWithNamespace('sort');
        return $this->argumentsAccessor->has($path);
    }

    /**
     * Returns the sorting string in the url e.g. title asc.
     *
     * @return string
     */
    public function getSorting(): string
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
    protected function getSortingPart(int $index): ?string
    {
        $sorting = $this->getSorting();
        if ($sorting === '') {
            return null;
        }

        $parts = explode(' ', $sorting);
        return $parts[$index] ?? null;
    }

    /**
     * Returns the sorting configuration name that is currently used.
     *
     * @return string
     */
    public function getSortingName(): ?string
    {
        return $this->getSortingPart(0);
    }

    /**
     * Returns the sorting direction that is currently used.
     *
     * @return string
     */
    public function getSortingDirection(): string
    {
        return mb_strtolower($this->getSortingPart(1) ?? '');
    }

    /**
     * @return SearchRequest
     */
    public function removeSorting(): SearchRequest
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
    public function setSorting(string $sortingName, string $direction = 'asc'): SearchRequest
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
    public function setPage(int $page): SearchRequest
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
    public function getPage(): ?int
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
     * Removes all non-alphanumeric values from the groupItem value to have a valid array key.
     *
     * @param string $groupItemValue
     * @return string
     */
    protected function getEscapedGroupItemValue(string $groupItemValue): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $groupItemValue);
    }

    /**
     * Retrieves the highest page of the groups.
     *
     * @return int
     */
    public function getHighestGroupPage(): int
    {
        $max = 1;
        $path = $this->prefixWithNamespace('groupPage');
        $groupPages = $this->argumentsAccessor->get($path, []);
        foreach ($groupPages as $groups) {
            if (!is_array($groups)) {
                continue;
            }
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
    public function setRawQueryString(string $rawQueryString = ''): SearchRequest
    {
        $this->stateChanged = true;
        $path = $this->prefixWithNamespace('q');
        $this->argumentsAccessor->set($path, $rawQueryString);
        return $this;
    }

    /**
     * Returns the passed rawQueryString.
     *
     * @return string
     */
    public function getRawUserQuery(): string
    {
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path);
        return (string)($query ?? '');
    }

    /**
     * Method to check if the query string is an empty string
     * (also empty string or whitespaces only are handled as empty).
     *
     * When no query string is set (null) the method returns false.
     * @return bool
     */
    public function getRawUserQueryIsEmptyString(): bool
    {
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path);

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
    public function getRawUserQueryIsNull(): bool
    {
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path);
        return $query === null;
    }

    /**
     * Sets the results per page that are used during search.
     *
     * @param int $resultsPerPage
     * @return SearchRequest
     */
    public function setResultsPerPage(int $resultsPerPage): SearchRequest
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        $this->argumentsAccessor->set($path, $resultsPerPage);
        $this->stateChanged = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function getStateChanged(): bool
    {
        return $this->stateChanged;
    }

    /**
     * Returns the passed resultsPerPage value
     * @return int
     */
    public function getResultsPerPage(): int
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        return (int)$this->argumentsAccessor->get($path);
    }

    /**
     * Allows setting additional filters that are used on time and not transported during the request.
     *
     * @param array $additionalFilters
     * @return SearchRequest
     */
    public function setAdditionalFilters(array $additionalFilters): SearchRequest
    {
        $path = $this->prefixWithNamespace('additionalFilters');
        $this->argumentsAccessor->set($path, $additionalFilters);
        $this->stateChanged = true;

        return $this;
    }

    /**
     * Retrieves the additional filters that have been set
     *
     * @return array
     */
    public function getAdditionalFilters(): array
    {
        $path = $this->prefixWithNamespace('additionalFilters');
        return $this->argumentsAccessor->get($path, []);
    }

    /**
     * @return int
     */
    public function getContextSystemLanguageUid(): int
    {
        return $this->contextSystemLanguageUid;
    }

    /**
     * @return int
     */
    public function getContextPageUid(): int
    {
        return $this->contextPageUid;
    }

    /**
     * Get contextTypoScriptConfiguration
     *
     * @return TypoScriptConfiguration
     */
    public function getContextTypoScriptConfiguration(): ?TypoScriptConfiguration
    {
        return $this->contextTypoScriptConfiguration;
    }

    /**
     * Assigns the last known persistedArguments and restores their state.
     *
     * @return SearchRequest
     */
    public function reset(): SearchRequest
    {
        $this->argumentsAccessor = new ArrayAccessor($this->persistedArguments);
        $this->stateChanged = false;
        $this->activeFacetContainer = new UrlFacetContainer(
            $this->argumentsAccessor,
            $this->argumentNameSpace ?? self::DEFAULT_PLUGIN_NAMESPACE,
            $this->contextTypoScriptConfiguration === null ?
                UrlFacetContainer::PARAMETER_STYLE_INDEX :
                $this->contextTypoScriptConfiguration->getSearchFacetingUrlParameterStyle()
        );

        // If the default of sorting parameter should be true, a modification of this condition is needed.
        // If instance of contextTypoScriptConfiguration is not TypoScriptConfiguration the sort should be enabled too
        if ($this->contextTypoScriptConfiguration instanceof TypoScriptConfiguration &&
                $this->contextTypoScriptConfiguration->getSearchFacetingUrlParameterSort(false)) {
            $this->activeFacetContainer->enableSort();
        }

        return $this;
    }

    /**
     * This can be used to start a new sub request, e.g. for a faceted search.
     *
     * @param bool $onlyPersistentArguments
     * @return SearchRequest
     */
    public function getCopyForSubRequest(bool $onlyPersistentArguments = true): SearchRequest
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
     * @noinspection PhpUnused
     */
    public function getArgumentNamespace(): string
    {
        return $this->argumentNameSpace;
    }

    /**
     * @return array
     */
    public function getAsArray(): array
    {
        return $this->argumentsAccessor->getData();
    }

    /**
     * Returns only the arguments as array.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return $this->argumentsAccessor->get($this->argumentNameSpace) ?? [];
    }
}
