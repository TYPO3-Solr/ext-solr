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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The searchRequest is used to act as an api to the arguments that have been passed
 * with GET and POST.
 */
class SearchRequest
{
    public const DEFAULT_PLUGIN_NAMESPACE = 'tx_solr';

    protected string $id;

    /**
     * Default namespace overwritten with the configured plugin namespace.
     */
    protected string $argumentNameSpace = self::DEFAULT_PLUGIN_NAMESPACE;

    /**
     * Arguments that should be kept for sub requests.
     * Default values, overwritten in the constructor with the namespaced arguments
     */
    protected array $persistentArgumentsPaths = [
        'tx_solr:q',
        'tx_solr:filter',
        'tx_solr:sort',
        'tx_solr:groupPage',
    ];

    protected bool $stateChanged = false;

    protected ?ArrayAccessor $argumentsAccessor = null;

    /**
     * The sys_language_uid that was used in the context where the request was build.
     * This could be different from the "L" parameter and not relevant for urls,
     * because typolink itself will handle it.
     */
    protected int $contextSystemLanguageUid = 0;

    /**
     * The page_uid that was used in the context where the request was build.
     *
     * The pageUid is not relevant for the typolink additionalArguments and therefore
     * a separate property.
     */
    protected int $contextPageUid;

    protected ?TypoScriptConfiguration $contextTypoScriptConfiguration;

    /**
     * Container for all active facets inside the URL(TYPO3/FE)
     */
    protected ?UrlFacetContainer $activeFacetContainer;

    protected array $persistedArguments = [];

    public function __construct(
        array $argumentsArray = [],
        int $pageUid = 0,
        int $sysLanguageUid = 0,
        ?TypoScriptConfiguration $typoScriptConfiguration = null,
    ) {
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

        $this->persistentArgumentsPaths = [$this->argumentNameSpace . ':q', $this->argumentNameSpace . ':filter', $this->argumentNameSpace . ':sort', $this->argumentNameSpace . ':grouping', $this->argumentNameSpace . ':groupPage'];

        if (!is_null($typoScriptConfiguration)) {
            $additionalPersistentArgumentsNames = $typoScriptConfiguration->getSearchAdditionalPersistentArgumentNames();
            foreach ($additionalPersistentArgumentsNames as $additionalPersistentArgumentsName) {
                $this->persistentArgumentsPaths[] = $this->argumentNameSpace . ':' . $additionalPersistentArgumentsName;
            }
            $this->persistentArgumentsPaths = array_unique($this->persistentArgumentsPaths);
        }

        $this->reset();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Can be used do merge arguments into the request arguments
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
     */
    protected function prefixWithNamespace(string $path): string
    {
        return $this->argumentNameSpace . ':' . $path;
    }

    /**
     * Returns active facet names
     */
    public function getActiveFacetNames(): array
    {
        return $this->activeFacetContainer->getActiveFacetNames();
    }

    /**
     * Returns all facet values for a certain facetName
     */
    public function getActiveFacetValuesByName(string $facetName): array
    {
        return $this->activeFacetContainer->getActiveFacetValuesByName($facetName);
    }

    /**
     * Returns active facets
     */
    public function getActiveFacets(): array
    {
        return $this->activeFacetContainer->getActiveFacets();
    }

    /**
     * Enable sorting of URL parameters
     *
     * @noinspection PhpUnused
     */
    public function sortActiveFacets(): void
    {
        $this->activeFacetContainer->enableSort();
    }

    public function isActiveFacetsSorted(): bool
    {
        return $this->activeFacetContainer->isSorted();
    }

    public function getActiveFacetsUrlParameterStyle(): string
    {
        return $this->activeFacetContainer->getParameterStyle();
    }

    /**
     * Returns the active count of facets
     */
    public function getActiveFacetCount(): int
    {
        return $this->activeFacetContainer->count();
    }

    /**
     * Sets active facets for current result set
     *
     * @noinspection PhpUnused
     */
    protected function setActiveFacets(array $activeFacets = []): SearchRequest
    {
        $this->activeFacetContainer->setActiveFacets($activeFacets);

        return $this;
    }

    /**
     * Adds a facet value to the request.
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
     */
    public function removeFacetValue(string $facetName, mixed $facetValue): SearchRequest
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
     */
    public function getHasFacetValue(string $facetName, mixed $facetValue): bool
    {
        return $this->activeFacetContainer->hasFacetValue($facetName, $facetValue);
    }

    /**
     * Returns all sortings in the sorting string e.g. ['title' => 'asc', 'relevance' => 'desc']
     */
    public function getSeperatedSortings(): array
    {
        $parsedSortings = [];
        $explodedSortings = GeneralUtility::trimExplode(',', $this->getSorting(), true);

        foreach ($explodedSortings as $sorting) {
            $sortingSeperated = explode(' ', $sorting);
            if (count($sortingSeperated) === 2) {
                $parsedSortings[$sortingSeperated[0]] = $sortingSeperated[1];
            }
        }

        return $parsedSortings;
    }

    public function getHasSorting(): bool
    {
        $path = $this->prefixWithNamespace('sort');
        return $this->argumentsAccessor->has($path);
    }

    /**
     * Returns the sorting string in the url e.g. title asc.
     */
    public function getSorting(): string
    {
        $path = $this->prefixWithNamespace('sort');
        return $this->argumentsAccessor->get($path, '');
    }

    /**
     * Helper function to get the sorting configuration name or direction.
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
     */
    public function getSortingName(): ?string
    {
        return $this->getSortingPart(0);
    }

    /**
     * Returns the sorting direction that is currently used.
     */
    public function getSortingDirection(): string
    {
        return mb_strtolower($this->getSortingPart(1) ?? '');
    }

    public function removeSorting(): SearchRequest
    {
        $path = $this->prefixWithNamespace('sort');
        $this->argumentsAccessor->reset($path);
        $this->stateChanged = true;
        return $this;
    }

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
     */
    public function getPage(): ?int
    {
        $path = $this->prefixWithNamespace('page');
        return $this->argumentsAccessor->get($path);
    }

    /**
     * Can be used to reset all groupPages.
     */
    public function removeAllGroupItemPages(): SearchRequest
    {
        $path = $this->prefixWithNamespace('groupPage');
        $this->argumentsAccessor->reset($path);

        return $this;
    }

    /**
     * Can be used to paginate within a groupItem.
     */
    public function setGroupItemPage(
        string $groupName,
        string $groupItemValue,
        int $page,
    ): SearchRequest {
        $this->stateChanged = true;
        $escapedValue = $this->getEscapedGroupItemValue($groupItemValue);
        $path = $this->prefixWithNamespace('groupPage:' . $groupName . ':' . $escapedValue);
        $this->argumentsAccessor->set($path, $page);
        return $this;
    }

    /**
     * Retrieves the current page for this group item.
     */
    public function getGroupItemPage(string $groupName, string $groupItemValue): int
    {
        $escapedValue = $this->getEscapedGroupItemValue($groupItemValue);
        $path = $this->prefixWithNamespace('groupPage:' . $groupName . ':' . $escapedValue);
        return max(1, (int)$this->argumentsAccessor->get($path));
    }

    /**
     * Removes all non-alphanumeric values from the groupItem value to have a valid array key.
     */
    protected function getEscapedGroupItemValue(string $groupItemValue): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $groupItemValue);
    }

    /**
     * Retrieves the highest page of the groups.
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
                if ((int)$groupItemPage > $max) {
                    $max = (int)$groupItemPage;
                }
            }
        }

        return $max;
    }

    /**
     * Method to overwrite the query string.
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
     */
    public function getRawUserQueryIsNull(): bool
    {
        $path = $this->prefixWithNamespace('q');
        $query = $this->argumentsAccessor->get($path);
        return $query === null;
    }

    /**
     * Sets the results per page that are used during search.
     */
    public function setResultsPerPage(int $resultsPerPage): SearchRequest
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        $this->argumentsAccessor->set($path, $resultsPerPage);
        $this->stateChanged = true;

        return $this;
    }

    public function getStateChanged(): bool
    {
        return $this->stateChanged;
    }

    /**
     * Returns the passed resultsPerPage value
     */
    public function getResultsPerPage(): int
    {
        $path = $this->prefixWithNamespace('resultsPerPage');
        return (int)$this->argumentsAccessor->get($path);
    }

    /**
     * Allows setting additional filters that are used on time and not transported during the request.
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
     */
    public function getAdditionalFilters(): array
    {
        $path = $this->prefixWithNamespace('additionalFilters');
        return $this->argumentsAccessor->get($path, []);
    }

    public function getContextSystemLanguageUid(): int
    {
        return $this->contextSystemLanguageUid;
    }

    public function getContextPageUid(): int
    {
        return $this->contextPageUid;
    }

    /**
     * Get contextTypoScriptConfiguration
     */
    public function getContextTypoScriptConfiguration(): ?TypoScriptConfiguration
    {
        return $this->contextTypoScriptConfiguration;
    }

    /**
     * Assigns the last known persistedArguments and restores their state.
     */
    public function reset(): SearchRequest
    {
        $this->argumentsAccessor = new ArrayAccessor($this->persistedArguments);
        $this->stateChanged = false;
        $this->activeFacetContainer = new UrlFacetContainer(
            $this->argumentsAccessor,
            $this->argumentNameSpace ?? self::DEFAULT_PLUGIN_NAMESPACE,
            $this->contextTypoScriptConfiguration === null
                ? UrlFacetContainer::PARAMETER_STYLE_INDEX
                : $this->contextTypoScriptConfiguration->getSearchFacetingUrlParameterStyle()
        );

        // If the default of sorting parameter should be true, a modification of this condition is needed.
        // If instance of contextTypoScriptConfiguration is not TypoScriptConfiguration the sort should be enabled too
        if ($this->contextTypoScriptConfiguration instanceof TypoScriptConfiguration
            && $this->contextTypoScriptConfiguration->getSearchFacetingUrlParameterSort()
        ) {
            $this->activeFacetContainer->enableSort();
        }

        return $this;
    }

    /**
     * This can be used to start a new sub request, e.g. for a faceted search.
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
     * Returns argument's namespace
     *
     * @noinspection PhpUnused
     */
    public function getArgumentNamespace(): string
    {
        return $this->argumentNameSpace;
    }

    public function getAsArray(): array
    {
        return $this->argumentsAccessor->getData();
    }

    /**
     * Returns only the arguments as array.
     */
    public function getArguments(): array
    {
        return $this->argumentsAccessor->get($this->argumentNameSpace) ?? [];
    }
}
