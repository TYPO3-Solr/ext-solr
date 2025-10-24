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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Uri;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Event\Routing\AfterUriIsProcessedEvent;
use ApacheSolrForTypo3\Solr\Event\Routing\BeforeCachedVariablesAreProcessedEvent;
use ApacheSolrForTypo3\Solr\Event\Routing\BeforeVariableInCachedUrlAreReplacedEvent;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use ApacheSolrForTypo3\Solr\Utility\ParameterSortingUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * SearchUriBuilder
 *
 * Responsibility:
 *
 * The SearchUriBuilder is responsible to build uris, that are used in the
 * searchContext. It can use the previous request with its persistent
 * arguments to build the url for a search sub request.
 */
class SearchUriBuilder
{
    protected ?UriBuilder $uriBuilder = null;

    protected static array $preCompiledLinks = [];

    protected static int $hitCount = 0;

    protected static int $missCount = 0;

    protected static array $additionalArgumentsCache = [];

    protected EventDispatcherInterface $eventDispatcher;

    protected ?RoutingService $routingService = null;

    public function injectUriBuilder(UriBuilder $uriBuilder): void
    {
        $this->uriBuilder = $uriBuilder;
    }

    public function injectRoutingService(RoutingService $routingService): void
    {
        $this->routingService = $routingService;
    }

    public function injectEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getAddFacetValueUri(SearchRequest $previousSearchRequest, string $facetName, $facetValue): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->addFacetValue($facetName, $facetValue)
            ->getAsArray();

        $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);

        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $this->sortFilterParametersIfNecessary($previousSearchRequest, $arguments);

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    /**
     * Removes all other facet values for this name and only set's the passed value for the facet.
     */
    public function getSetFacetValueUri(SearchRequest $previousSearchRequest, $facetName, $facetValue): string
    {
        $previousSearchRequest = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeAllFacetValuesByName($facetName);

        return $this->getAddFacetValueUri($previousSearchRequest, $facetName, $facetValue);
    }

    public function getRemoveFacetValueUri(SearchRequest $previousSearchRequest, $facetName, $facetValue): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeFacetValue($facetName, $facetValue)
            ->getAsArray();

        $additionalArguments = [];
        if (
            ($typoScriptConfiguration = $previousSearchRequest->getContextTypoScriptConfiguration())
            && $typoScriptConfiguration instanceof TypoScriptConfiguration
            && $typoScriptConfiguration->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl()
        ) {
            $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        }
        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $this->sortFilterParametersIfNecessary($previousSearchRequest, $arguments);

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    public function getRemoveFacetUri(SearchRequest $previousSearchRequest, $facetName): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeAllFacetValuesByName($facetName)
            ->getAsArray();

        $additionalArguments = [];
        if (
            ($typoScriptConfiguration = $previousSearchRequest->getContextTypoScriptConfiguration())
            && $typoScriptConfiguration instanceof TypoScriptConfiguration
            && $typoScriptConfiguration->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl()
        ) {
            $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        }

        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $this->sortFilterParametersIfNecessary($previousSearchRequest, $arguments);

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    public function getRemoveAllFacetsUri(SearchRequest $previousSearchRequest): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeAllFacets()
            ->getAsArray();

        $additionalArguments = [];
        if (
            ($typoScriptConfiguration = $previousSearchRequest->getContextTypoScriptConfiguration())
            && $typoScriptConfiguration instanceof TypoScriptConfiguration
            && $typoScriptConfiguration->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl()
        ) {
            $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        }

        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $this->sortFilterParametersIfNecessary($previousSearchRequest, $arguments);

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    public function getResultPageUri(SearchRequest $previousSearchRequest, $page): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->setPage($page)
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    public function getResultGroupItemPageUri(SearchRequest $previousSearchRequest, GroupItem $groupItem, int $page): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->setGroupItemPage($groupItem->getGroup()->getGroupName(), $groupItem->getGroupValue(), $page)
            ->getAsArray();
        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    public function getNewSearchUri(SearchRequest $previousSearchRequest, $queryString): string
    {
        $contextConfiguration = $previousSearchRequest->getContextTypoScriptConfiguration();
        $contextSystemLanguage = $previousSearchRequest->getContextSystemLanguageUid();
        $contextPageUid = $previousSearchRequest->getContextPageUid();

        /** @var SearchRequest $request */
        $request = GeneralUtility::makeInstance(
            SearchRequest::class,
            [],
            $contextPageUid,
            $contextSystemLanguage,
            $contextConfiguration,
        );
        $arguments = $request->setRawQueryString($queryString)->getAsArray();

        $this->sortFilterParametersIfNecessary($previousSearchRequest, $arguments);

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    public function getSetSortingUri(SearchRequest $previousSearchRequest, $sortingName, $sortingDirection): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->setSorting($sortingName, $sortingDirection)
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    public function getRemoveSortingUri(SearchRequest $previousSearchRequest): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeSorting()
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    public function getCurrentSearchUri(SearchRequest $previousSearchRequest): string
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    protected function getAdditionalArgumentsFromRequestConfiguration(SearchRequest $request): array
    {
        if ($request->getContextTypoScriptConfiguration() === null) {
            return [];
        }

        $reQuestId = $request->getId();
        if (isset(self::$additionalArgumentsCache[$reQuestId])) {
            return self::$additionalArgumentsCache[$reQuestId];
        }

        self::$additionalArgumentsCache[$reQuestId] = $request->getContextTypoScriptConfiguration()
            ->getSearchFacetingFacetLinkUrlParametersAsArray();

        return self::$additionalArgumentsCache[$reQuestId];
    }

    protected function getTargetPageUidFromRequestConfiguration(SearchRequest $request): ?int
    {
        return $request->getContextTypoScriptConfiguration()?->getSearchTargetPage();
    }

    /**
     * Build the link with an i memory cache that reduces the amount of required typolink calls.
     */
    protected function buildLinkWithInMemoryCache(?int $pageUid, array $arguments): string
    {
        $values = [];
        $structure = $arguments;
        $this->getSubstitution($structure, $values);
        $hash = hash('md5', $pageUid . json_encode($structure));
        if (isset(self::$preCompiledLinks[$hash])) {
            self::$hitCount++;
            $uriCacheTemplate = self::$preCompiledLinks[$hash];
        } else {
            self::$missCount++;
            $this->uriBuilder->reset()->setTargetPageUid($pageUid);
            try {
                $uriCacheTemplate = $this->uriBuilder->setArguments($structure)->build();

                /** @var UrlHelper $urlHelper */
                $urlHelper = GeneralUtility::makeInstance(UrlHelper::class, $uriCacheTemplate);
                self::$preCompiledLinks[$hash] = (string)$urlHelper;
            } catch (InvalidParameterException $exception) {
                // the placeholders may result in an exception when route enhancers with requirements are active
                // In this case, try to build the URL with original arguments
                $hash = hash('md5', $pageUid . json_encode($arguments));
                if (isset(self::$preCompiledLinks[$hash])) {
                    self::$hitCount++;
                    $uriCacheTemplate = self::$preCompiledLinks[$hash];
                } else {
                    $uriCacheTemplate = $this->uriBuilder->setArguments($arguments)->build();
                    /** @var UrlHelper $urlHelper */
                    $urlHelper = GeneralUtility::makeInstance(UrlHelper::class, $uriCacheTemplate);
                    self::$preCompiledLinks[$hash] = (string)$urlHelper;
                }
            }
        }

        $keys = array_map(static function ($value) {
            return urlencode((string)$value);
        }, array_keys($values));
        $values = array_map(static function ($value) {
            return urlencode((string)$value);
        }, $values);

        $routingConfigurations = $this->routingService
            ->fetchEnhancerByPageUid($pageUid);
        $enhancedRouting = count($routingConfigurations) > 0;
        $this->routingService->reset();
        if ($enhancedRouting && is_array($routingConfigurations[0] ?? null)) {
            $this->routingService->fromRoutingConfiguration($routingConfigurations[0]);
        }

        /** @var Uri $uri */
        $uri = GeneralUtility::makeInstance(
            Uri::class,
            $uriCacheTemplate,
        );

        $urlEvent = new BeforeVariableInCachedUrlAreReplacedEvent($uri, $enhancedRouting);
        /** @var BeforeVariableInCachedUrlAreReplacedEvent $urlEvent */
        $urlEvent = $this->eventDispatcher->dispatch($urlEvent);
        $uriCacheTemplate = (string)$urlEvent->getUri();

        $variableEvent = new BeforeCachedVariablesAreProcessedEvent(
            $uri,
            $routingConfigurations,
            $keys,
            $values,
        );
        $this->eventDispatcher->dispatch($variableEvent);

        $values = $variableEvent->getVariableValues();
        // Take care that everything is urlencoded!
        $keys = array_map(static function ($value) {
            if (!str_contains($value, '###')) {
                return $value;
            }
            return urlencode($value);
        }, array_keys($values));

        $uri = str_replace($keys, $values, $uriCacheTemplate);
        $uri = GeneralUtility::makeInstance(
            Uri::class,
            $uri,
        );
        $uriEvent = new AfterUriIsProcessedEvent($uri, $routingConfigurations);
        $this->eventDispatcher->dispatch($uriEvent);
        $uri = $uriEvent->getUri();
        return (string)$uri;
    }

    /**
     * Flushes the internal in memory cache.
     */
    public function flushInMemoryCache(): void
    {
        self::$preCompiledLinks = [];
    }

    /**
     * This method is used to build two arrays from a nested array. The first one represents the structure.
     * In this structure the values are replaced with the pass to the value. At the same time the values get collected
     * in the $values array, with the path as key. This can be used to build a comparable hash from the arguments
     * in order to reduce the amount of typolink calls
     *
     * Example input
     *
     * $data = [
     *  'foo' => [
     *      'bar' => 111
     *   ]
     * ]
     *
     * will return:
     *
     * $structure = [
     *  'foo' => [
     *      'bar' => '###foo:bar###'
     *   ]
     * ]
     *
     * $values = [
     *  '###foo:bar###' => 111
     * ]
     */
    protected function getSubstitution(array &$structure, array &$values, array $branch = []): void
    {
        /*
         * Adds information about the filter facet to the placeholder.
         *
         * This feature allows the handle even placeholder in RouteEnhancer
         */
        $filter = false;
        if (count($branch) > 0 && $branch[count($branch) - 1] === 'filter') {
            $filter = true;
        }
        foreach ($structure as $key => &$value) {
            $branch[] = $key;
            if (is_array($value)) {
                $this->getSubstitution($value, $values, $branch);
            } else {
                // @todo: Refactor to multi-dimensional array.
                // https://solr-ddev-site.ddev.site/content-examples/form-elements/search?tx_solr[filter][type:tx_news_domain_model_news]=1&tx_solr[q]=*
                // https://solr-ddev-site.ddev.site/content-examples/form-elements/search?tx_solr[filter][0]=type:pages&tx_solr[q]=*
                if ($filter && $value !== 1) {
                    [$facetType] = explode(':', $value);
                    $branch[] = $facetType;
                }
                $path = '###' . implode(':', $branch) . '###';
                $values[$path] = $value;
                $structure[$key] = $path;
                if ($filter) {
                    array_pop($branch);
                }
            }
            array_pop($branch);
        }
    }

    /**
     * Sorts filter arguments if enabled.
     */
    protected function sortFilterParametersIfNecessary(SearchRequest $searchRequest, array &$arguments): void
    {
        if (!$searchRequest->isActiveFacetsSorted()) {
            return;
        }

        if (
            ($typoScriptConfiguration = $searchRequest->getContextTypoScriptConfiguration())
            && $typoScriptConfiguration instanceof TypoScriptConfiguration
        ) {
            $pluginNameSpace = $typoScriptConfiguration->getSearchPluginNamespace();
            if (!empty($arguments[$pluginNameSpace]['filter']) && is_array($arguments[$pluginNameSpace]['filter'])) {
                $arguments[$pluginNameSpace]['filter'] = ParameterSortingUtility::sortByType(
                    $arguments[$pluginNameSpace]['filter'],
                    $searchRequest->getActiveFacetsUrlParameterStyle(),
                );
            }
        }
    }
}
