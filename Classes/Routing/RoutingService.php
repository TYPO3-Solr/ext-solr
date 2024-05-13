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

namespace ApacheSolrForTypo3\Solr\Routing;

use ApacheSolrForTypo3\Solr\Routing\Enhancer\SolrRouteEnhancerInterface;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageSlugCandidateProvider;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This service class bundles method required to process and manipulate routes.
 */
class RoutingService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Default plugin namespace
     */
    public const PLUGIN_NAMESPACE = 'tx_solr';

    /**
     * Settings from routing configuration
     */
    protected array $settings = [];

    /**
     * List of filter that are placed as path arguments
     */
    protected array $pathArguments = [];

    /**
     * Plugin/extension namespace
     */
    protected string $pluginNamespace = 'tx_solr';

    /**
     * List of TYPO3 core parameters, that we should ignore
     *
     * @see \TYPO3\CMS\Frontend\Page\CacheHashCalculator::isCoreParameter()
     * @var string[]
     */
    protected array $coreParameters = [
        'no_cache',
        'cHash',
        'id',
        'MP',
        'type',
    ];

    protected ?UrlFacetService $urlFacetPathService = null;

    protected ?UrlFacetService $urlFacetQueryService = null;

    /**
     * RoutingService constructor.
     */
    public function __construct(array $settings = [], string $pluginNamespace = self::PLUGIN_NAMESPACE)
    {
        $this->settings = $settings;
        $this->pluginNamespace = $pluginNamespace;
        if (empty($this->pluginNamespace)) {
            $this->pluginNamespace = self::PLUGIN_NAMESPACE;
        }
        $this->initUrlFacetService();
    }

    /**
     * Creates a clone of the current service and replace the settings inside
     */
    public function withSettings(array $settings): RoutingService
    {
        $service = clone $this;
        $service->settings = $settings;
        $service->initUrlFacetService();
        return $service;
    }

    /**
     * Creates a clone of the current service and replace the settings inside
     */
    public function withPathArguments(array $pathArguments): RoutingService
    {
        $service = clone $this;
        $service->pathArguments = $pathArguments;
        $service->initUrlFacetService();
        return $service;
    }

    /**
     * Load configuration from routing configuration
     */
    public function fromRoutingConfiguration(array $routingConfiguration): RoutingService
    {
        if (empty($routingConfiguration) ||
            empty($routingConfiguration['type']) ||
            !$this->isRouteEnhancerForSolr((string)$routingConfiguration['type'])) {
            return $this;
        }

        if (isset($routingConfiguration['solr'])) {
            $this->settings = $routingConfiguration['solr'];
            $this->initUrlFacetService();
        }

        if (isset($routingConfiguration['_arguments'])) {
            $this->pathArguments = $routingConfiguration['_arguments'];
        }

        return $this;
    }

    /**
     * Reset the routing service
     */
    public function reset(): RoutingService
    {
        $this->settings = [];
        $this->pathArguments = [];
        $this->pluginNamespace = self::PLUGIN_NAMESPACE;
        return $this;
    }

    /**
     * Initialize url facet services for different types
     */
    protected function initUrlFacetService(): RoutingService
    {
        $this->urlFacetPathService = new UrlFacetService('path', $this->settings);
        $this->urlFacetQueryService = new UrlFacetService('query', $this->settings);

        return $this;
    }

    public function getUrlFacetPathService(): UrlFacetService
    {
        return $this->urlFacetPathService;
    }

    public function getUrlFacetQueryService(): UrlFacetService
    {
        return $this->urlFacetQueryService;
    }

    /**
     * Test if the given parameter is a Core parameter
     *
     * @see \TYPO3\CMS\Frontend\Page\CacheHashCalculator::isCoreParameter
     */
    public function isCoreParameter(string $parameterName): bool
    {
        return in_array($parameterName, $this->coreParameters);
    }

    /**
     * This returns the plugin namespace
     * @see https://docs.typo3.org/p/apache-solr-for-typo3/solr/main/en-us/Configuration/Reference/TxSolrView.html#pluginnamespace
     */
    public function getPluginNamespace(): string
    {
        return $this->pluginNamespace;
    }

    /**
     * Determine if an enhancer is in use for Solr
     */
    public function isRouteEnhancerForSolr(string $enhancerName): bool
    {
        if (empty($enhancerName)) {
            return false;
        }

        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers'][$enhancerName])) {
            return false;
        }
        $className = $GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers'][$enhancerName];

        if (!class_exists($className)) {
            return false;
        }

        $interfaces = class_implements($className);

        return in_array(SolrRouteEnhancerInterface::class, $interfaces);
    }

    /**
     * Masks Solr filter inside the query parameters
     */
    public function finalizePathQuery(string $uriPath): string
    {
        $pathSegments = explode('/', $uriPath);
        $query = array_pop($pathSegments);
        $queryValues = explode($this->urlFacetPathService->getMultiValueSeparator(), $query);
        $queryValues = array_map([$this->urlFacetPathService, 'decodeSingleValue'], $queryValues);
        /*
         * In some constellations the path query contains the facet type in front.
         * This leads to the result, that the query values could contain the same facet value multiple times.
         *
         * In order to avoid this behaviour, the query values need to be checked and clean up.
         * 1. Remove possible prefix information
         * 2. Apply character replacements
         * 3. Filter duplicate values
         */
        $queryValuesCount = count($queryValues);
        for ($i = 0; $i < $queryValuesCount; $i++) {
            $queryValues[$i] = urldecode($queryValues[$i]);
            if ($this->containsFacetAndValueSeparator($queryValues[$i])) {
                [$facetName, $facetValue] = explode(
                    $this->detectFacetAndValueSeparator($queryValues[$i]),
                    $queryValues[$i],
                    2
                );

                if ($this->isPathArgument($facetName)) {
                    $queryValues[$i] = $facetValue;
                }
            }
            $queryValues[$i] = $this->urlFacetPathService->applyCharacterMap($queryValues[$i]);
        }

        $queryValues = array_unique($queryValues);
        $queryValues = array_map([$this->urlFacetPathService, 'encodeSingleValue'], $queryValues);
        sort($queryValues);
        $pathSegments[] = implode(
            $this->urlFacetPathService->getMultiValueSeparator(),
            $queryValues
        );
        return implode('/', $pathSegments);
    }

    /**
     * This method checks if the query parameter should be masked.
     */
    public function shouldMaskQueryParameter(): bool
    {
        if (!isset($this->settings['query']['mask']) ||
            !$this->settings['query']['mask']) {
            return false;
        }

        $targetFields = $this->getQueryParameterMap();

        return !empty($targetFields);
    }

    /**
     * Masks Solr filter inside the query parameters
     */
    public function maskQueryParameters(array $queryParams): array
    {
        if (!$this->shouldMaskQueryParameter()) {
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()])) {
            $this->logger
                ->
                error('Mask error: Query parameters has no entry for namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()]['filter']) ||
            empty($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->
                info('Mask info: Query parameters has no filter in namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }

        if (!is_array($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->
                warning('Mask info: Filter within the Query parameters is not an array');
            return $queryParams;
        }

        $queryParameterMap = $this->getQueryParameterMap();
        $newQueryParams = $queryParams;

        $newFilterArray = [];
        foreach ($newQueryParams[$this->getPluginNamespace()]['filter'] as $queryParamValue) {
            $defaultSeparator = $this->detectFacetAndValueSeparator((string)$queryParamValue);
            [$facetName, $facetValue] = explode($defaultSeparator, $queryParamValue, 2);
            $keep = false;
            if (isset($queryParameterMap[$facetName]) &&
                isset($newQueryParams[$queryParameterMap[$facetName]])) {
                $this->logger->error(
                    'Mask error: Facet "' . $facetName . '" as "' . $queryParameterMap[$facetName] .
                    '" already in query!'
                );
                $keep = true;
            }
            if (!isset($queryParameterMap[$facetName]) || $keep) {
                $newFilterArray[] = $queryParamValue;
                continue;
            }

            $newQueryParams[$queryParameterMap[$facetName]] = $facetValue;
        }

        $newQueryParams[$this->getPluginNamespace()]['filter'] = $newFilterArray;

        return $this->cleanUpQueryParameters($newQueryParams);
    }

    /**
     * Unmask incoming parameters if needed
     */
    public function unmaskQueryParameters(array $queryParams): array
    {
        if (!$this->shouldMaskQueryParameter()) {
            return $queryParams;
        }

        /*
         * The array $queryParameterMap contains the mapping of
         * facet name to new url name. In order to unmask we need to switch key and values.
         */
        $queryParameterMap = $this->getQueryParameterMap();
        $queryParameterMapSwitched = [];
        foreach ($queryParameterMap as $value => $key) {
            $queryParameterMapSwitched[$key] = $value;
        }

        $newQueryParams = [];
        foreach ($queryParams as $queryParamName => $queryParamValue) {
            // A merge is needed!
            if (!isset($queryParameterMapSwitched[$queryParamName])) {
                if (isset($newQueryParams[$queryParamName])) {
                    $newQueryParams[$queryParamName] = array_merge_recursive(
                        $newQueryParams[$queryParamName],
                        $queryParamValue
                    );
                } else {
                    $newQueryParams[$queryParamName] = $queryParamValue;
                }
                continue;
            }
            if (!isset($newQueryParams[$this->getPluginNamespace()])) {
                $newQueryParams[$this->getPluginNamespace()] = [];
            }
            if (!isset($newQueryParams[$this->getPluginNamespace()]['filter'])) {
                $newQueryParams[$this->getPluginNamespace()]['filter'] = [];
            }

            $newQueryParams[$this->getPluginNamespace()]['filter'][] =
                $queryParameterMapSwitched[$queryParamName] . ':' . $queryParamValue;
        }

        return $this->cleanUpQueryParameters($newQueryParams);
    }

    /**
     * This method check if the query parameters should be touched or not.
     *
     * There are following requirements:
     * - Masking is activated and the mal is valid or
     * - Concat is activated
     */
    public function shouldConcatQueryParameters(): bool
    {
        /*
         * The concat will activate automatically if parameters should be masked.
         * This solution is less complex since not every mapping parameter needs to be tested
         */
        if ($this->shouldMaskQueryParameter()) {
            return true;
        }

        return isset($this->settings['query']['concat']) && (bool)$this->settings['query']['concat'] === true;
    }

    /**
     * Returns the query parameter map
     *
     * Note TYPO3 core query arguments removed from the configured map!
     */
    public function getQueryParameterMap(): array
    {
        if (!isset($this->settings['query']['map']) ||
            !is_array($this->settings['query']['map']) ||
            empty($this->settings['query']['map'])) {
            return [];
        }
        // TODO: Test if there is more than one value!
        $self = $this;
        return array_filter(
            $this->settings['query']['map'],
            static function($value) use ($self) {
                return !$self->isCoreParameter($value);
            }
        );
    }

    /**
     * Group all filter values together and concat e
     * Note: this will just handle filter values
     *
     * IN:
     * tx_solr => [
     *   filter => [
     *      color:red
     *      product:candy
     *      color:blue
     *      taste:sour
     *   ]
     * ]
     *
     * OUT:
     * tx_solr => [
     *   filter => [
     *      color:blue,red
     *      product:candy
     *      taste:sour
     *   ]
     * ]
     */
    public function concatQueryParameter(array $queryParams = []): array
    {
        if (!$this->shouldConcatQueryParameters()) {
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()])) {
            $this->logger
                ->error('Mask error: Query parameters has no entry for namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()]['filter']) ||
            empty($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->info('Mask info: Query parameters has no filter in namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }

        if (!is_array($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->
                warning('Mask info: Filter within the Query parameters is not an array');
            return $queryParams;
        }

        $queryParams[$this->getPluginNamespace()]['filter'] =
            $this->concatFilterValues($queryParams[$this->getPluginNamespace()]['filter']);

        return $this->cleanUpQueryParameters($queryParams);
    }

    /**
     * This method expect a filter array that should be concat instead of the whole query
     */
    public function concatFilterValues(array $filterArray): array
    {
        if (empty($filterArray) || !$this->shouldConcatQueryParameters()) {
            return $filterArray;
        }

        $queryParameterMap = $this->getQueryParameterMap();
        $newFilterArray = [];
        $defaultSeparator = $this->detectFacetAndValueSeparator((string)$filterArray[0]);
        // Collect parameter names and rename parameter if required
        foreach ($filterArray as $set) {
            $separator = $this->detectFacetAndValueSeparator((string)$set);
            [$facetName, $facetValue] = explode($separator, $set, 2);
            if (isset($queryParameterMap[$facetName])) {
                $facetName = $queryParameterMap[$facetName];
            }
            if (!isset($newFilterArray[$facetName])) {
                $newFilterArray[$facetName] = [$facetValue];
            } else {
                $newFilterArray[$facetName][] = $facetValue;
            }
        }

        foreach ($newFilterArray as $facetName => $facetValues) {
            $newFilterArray[$facetName] = $facetName . $defaultSeparator . $this->queryParameterFacetsToString($facetValues);
        }

        return array_values($newFilterArray);
    }

    /**
     * Inflate given query parameters if configured
     * Note: this will just combine filter values
     *
     * IN:
     * tx_solr => [
     *   filter => [
     *      color:blue,red
     *      product:candy
     *      taste:sour
     *   ]
     * ]
     *
     * OUT:
     * tx_solr => [
     *   filter => [
     *      color:red
     *      product:candy
     *      color:blue
     *      taste:sour
     *   ]
     * ]
     */
    public function inflateQueryParameter(array $queryParams = []): array
    {
        if (!$this->shouldConcatQueryParameters()) {
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()])) {
            $queryParams[$this->getPluginNamespace()] = [];
        }

        if (!isset($queryParams[$this->getPluginNamespace()]['filter']) ||
            is_null($queryParams[$this->getPluginNamespace()]['filter'])) {
            $queryParams[$this->getPluginNamespace()]['filter'] = [];
        }

        if (!is_array($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->
                warning('Inflate query: Expected filter to be an array. Replace it with an array structure!');
            $queryParams[$this->getPluginNamespace()]['filter'] = [];
        }

        $newQueryParams = [];
        foreach ($queryParams[$this->getPluginNamespace()]['filter'] as $set) {
            $separator = $this->detectFacetAndValueSeparator((string)$set);
            [$facetName, $facetValuesString] = explode($separator, $set, 2);
            if ($facetValuesString == null) {
                continue;
            }
            $facetValues = explode($this->urlFacetQueryService->getMultiValueSeparator(), $facetValuesString);

            /**
             * A facet value could contain the multi value separator. This value is masked in order to
             * avoid problems during separation of the values (line above).
             *
             * After splitting the values, the character inside the value need to be restored
             *
             * @see RoutingService::queryParameterFacetsToString
             */
            $facetValues = array_map([$this->urlFacetQueryService, 'decodeSingleValue'], $facetValues);

            foreach ($facetValues as $facetValue) {
                $newQueryParams[] = $facetName . $separator . $facetValue;
            }
        }
        $queryParams[$this->getPluginNamespace()]['filter'] = array_values($newQueryParams);

        return $this->cleanUpQueryParameters($queryParams);
    }

    /**
     * Cleanup the query parameters, to avoid empty solr arguments
     */
    public function cleanUpQueryParameters(array $queryParams): array
    {
        if (empty($queryParams[$this->getPluginNamespace()]['filter'])) {
            unset($queryParams[$this->getPluginNamespace()]['filter']);
        }

        if (empty($queryParams[$this->getPluginNamespace()])) {
            unset($queryParams[$this->getPluginNamespace()]);
        }
        return $queryParams;
    }

    /**
     * Builds a string out of multiple facet values
     *
     * A facet value could contain the multi value separator. This value has to be masked in order to
     * avoid problems during separation of the values later.
     *
     * This mask has to be applied before contact the values
     */
    public function queryParameterFacetsToString(array $facets): string
    {
        $facets = array_map([$this->urlFacetQueryService, 'encodeSingleValue'], $facets);
        sort($facets);
        return implode($this->urlFacetQueryService->getMultiValueSeparator(), $facets);
    }

    /**
     * Returns the string which separates the facet from the value
     */
    public function detectFacetAndValueSeparator(string $facetWithValue): string
    {
        $separator = ':';
        if (str_contains($facetWithValue, '%3A')) {
            $separator = '%3A';
        }

        return $separator;
    }

    /**
     * Check if given facet value combination contains a separator
     */
    public function containsFacetAndValueSeparator(string $facetWithValue): bool
    {
        return str_contains($facetWithValue, ':') || str_contains($facetWithValue, '%3A');
    }

    /**
     * Cleanup facet values (strip type if needed)
     */
    public function cleanupFacetValues(array $facetValues): array
    {
        $facetValuesCount = count($facetValues);
        for ($i = 0; $i < $facetValuesCount; $i++) {
            if (!$this->containsFacetAndValueSeparator((string)$facetValues[$i])) {
                continue;
            }

            $separator = $this->detectFacetAndValueSeparator((string)$facetValues[$i]);
            [$type, $value] = explode($separator, $facetValues[$i]);

            if ($this->isMappingArgument($type) || $this->isPathArgument($type)) {
                $facetValues[$i] = $value;
            }
        }
        return $facetValues;
    }

    /**
     * Builds a string out of multiple facet values
     */
    public function pathFacetsToString(array $facets): string
    {
        $facets = $this->cleanupFacetValues($facets);
        sort($facets);
        $facets = array_map([$this->urlFacetPathService, 'applyCharacterMap'], $facets);
        $facets = array_map([$this->urlFacetPathService, 'encodeSingleValue'], $facets);
        return implode($this->urlFacetPathService->getMultiValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     */
    public function facetsToString(array $facets): string
    {
        $facets = $this->cleanupFacetValues($facets);
        sort($facets);
        return implode($this->getDefaultMultiValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     *
     * This method is used in two different situation
     *  1. Middleware: Here the values should not be decoded
     *  2. Within the event listener CachedPathVariableModifier
     */
    public function pathFacetStringToArray(string $facets, bool $decode = true): array
    {
        $facetString = $this->urlFacetPathService->applyCharacterMap($facets);
        $facets = explode($this->urlFacetPathService->getMultiValueSeparator(), $facetString);
        if (!$decode) {
            return $facets;
        }
        return array_map([$this->urlFacetPathService, 'decodeSingleValue'], $facets);
    }

    /**
     * Returns the multi value separator
     */
    public function getDefaultMultiValueSeparator(): string
    {
        return $this->settings['multiValueSeparator'] ?? ',';
    }

    /**
     * Find an enhancer configuration by a given page id
     */
    public function fetchEnhancerByPageUid(int $pageUid): array
    {
        $site = $this->findSiteByUid($pageUid);
        if (!$site instanceof Site) {
            return [];
        }

        return $this->fetchEnhancerInSiteConfigurationByPageUid(
            $site,
            $pageUid
        );
    }

    /**
     * Returns the route enhancer configuration by given site and page uid
     */
    public function fetchEnhancerInSiteConfigurationByPageUid(Site $site, int $pageUid): array
    {
        $configuration = $site->getConfiguration();
        if (empty($configuration['routeEnhancers']) || !is_array($configuration['routeEnhancers'])) {
            return [];
        }
        $result = [];
        foreach ($configuration['routeEnhancers'] as $settings) {
            // Not the page we are looking for
            if (isset($settings['limitToPages']) &&
                is_array($settings['limitToPages']) &&
                !in_array($pageUid, $settings['limitToPages'])) {
                continue;
            }

            if (empty($settings) || !isset($settings['type']) ||
                !$this->isRouteEnhancerForSolr((string)$settings['type'])
            ) {
                continue;
            }
            $result[] = $settings;
        }

        return $result;
    }

    /**
     * Add heading slash to given slug
     */
    public function cleanupHeadingSlash(string $slug): string
    {
        if (!str_starts_with($slug, '/')) {
            return '/' . $slug;
        }
        if (str_starts_with($slug, '//')) {
            return mb_substr($slug, 1);
        }

        return $slug;
    }

    /**
     * Add heading slash to given slug
     */
    public function addHeadingSlash(string $slug): string
    {
        if (str_starts_with($slug, '/')) {
            return $slug;
        }

        return '/' . $slug;
    }

    /**
     * Remove heading slash from given slug
     */
    public function removeHeadingSlash(string $slug): string
    {
        if (!str_starts_with($slug, '/')) {
            return $slug;
        }

        return mb_substr($slug, 1);
    }

    /**
     * Retrieve the site by given UID
     */
    public function findSiteByUid(int $pageUid): ?Site
    {
        try {
            return $this->getSiteFinder()
                ->getSiteByPageId($pageUid);
        } catch (SiteNotFoundException) {
            return null;
        }
    }

    public function getSlugCandidateProvider(Site $site): PageSlugCandidateProvider
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return GeneralUtility::makeInstance(
            PageSlugCandidateProvider::class,
            $context,
            $site,
            null
        );
    }

    /**
     * Convert the base string into a URI object
     */
    public function convertStringIntoUri(string $base): ?UriInterface
    {
        try {
            return GeneralUtility::makeInstance(
                Uri::class,
                $base
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * In order to search for a path, a possible language prefix need to remove
     */
    public function stripLanguagePrefixFromPath(SiteLanguage $language, string $path): string
    {
        if ($language->getBase()->getPath() === '/') {
            return $path;
        }

        $pathLength = mb_strlen($language->getBase()->getPath());

        $path = mb_substr($path, $pathLength);
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Enrich the current query Params with data from path information
     */
    public function addPathArgumentsToQuery(
        ServerRequestInterface $request,
        array $arguments,
        array $parameters
    ): ServerRequestInterface {
        $queryParams = $request->getQueryParams();
        foreach ($arguments as $fieldName => $queryPath) {
            // Skip if there is no parameter
            if (!isset($parameters[$fieldName])) {
                continue;
            }
            $pathElements = explode('/', $queryPath);

            if (!empty($this->pluginNamespace)) {
                array_unshift($pathElements, $this->pluginNamespace);
            }

            $queryParams = $this->processUriPathArgument(
                $queryParams,
                $fieldName,
                $parameters,
                $pathElements
            );
        }

        return $request->withQueryParams($queryParams);
    }

    /**
     * Check if given argument is a mapping argument
     */
    public function isMappingArgument(string $facetName): bool
    {
        $map = $this->getQueryParameterMap();

        return isset($map[$facetName]) && $this->shouldMaskQueryParameter();
    }

    /**
     * Check if given facet type is a path argument
     */
    public function isPathArgument(string $facetName): bool
    {
        return isset($this->pathArguments[$facetName]);
    }

    public function reviewVariable(string $variable): string
    {
        if (!$this->containsFacetAndValueSeparator($variable)) {
            return $variable;
        }

        $separator = $this->detectFacetAndValueSeparator($variable);
        [$type, $value] = explode($separator, $variable, 2);

        return $this->isMappingArgument($type) ? $value : $variable;
    }

    /**
     * Remove type prefix from filter
     */
    public function reviseFilterVariables(array $variables): array
    {
        $newVariables = [];
        foreach ($variables as $key => $value) {
            $matches = [];
            if (!preg_match('/###' . $this->getPluginNamespace() . ':filter:\d+:(.+?)###/', $key, $matches)) {
                $newVariables[$key] = $value;
                continue;
            }
            if (!$this->isMappingArgument($matches[1]) && !$this->isPathArgument($matches[1])) {
                $newVariables[$key] = $value;
                continue;
            }
            $separator = $this->detectFacetAndValueSeparator((string)$value);
            $parts = explode($separator, $value);

            do {
                if ($parts[0] === $matches[1]) {
                    array_shift($parts);
                }
            } while ($parts[0] === $matches[1]);

            $newVariables[$key] = implode($separator, $parts);
        }

        return $newVariables;
    }

    /**
     * Converts path segment information into query parameters
     *
     * Example:
     * /products/household
     *
     * tx_solr:
     *      filter:
     *          - type:household
     */
    protected function processUriPathArgument(
        array $queryParams,
        string $fieldName,
        array $parameters,
        array $pathElements
    ): array {
        $queryKey = array_shift($pathElements);
        $queryKey = (string)$queryKey;

        $tmpQueryKey = $queryKey;
        if (str_contains($queryKey, '-')) {
            [$tmpQueryKey, $filterName] = explode('-', $tmpQueryKey, 2);
        }
        if (!isset($queryParams[$tmpQueryKey])) {
            $queryParams[$tmpQueryKey] = [];
        }

        if (str_contains($queryKey, '-')) {
            [$queryKey, $filterName] = explode('-', $queryKey, 2);
            // explode multiple values
            $values = $this->pathFacetStringToArray($parameters[$fieldName], false);
            sort($values);

            // @TODO: Support URL data bag
            foreach ($values as $value) {
                $value = $this->urlFacetPathService->applyCharacterMap($value);
                $queryParams[$queryKey][] = $filterName . ':' . $value;
            }
        } else {
            $queryParams[$queryKey] = $this->processUriPathArgument(
                $queryParams[$queryKey],
                $fieldName,
                $parameters,
                $pathElements
            );
        }

        return $queryParams;
    }

    public function getSiteMatcher(): SiteMatcher
    {
        return GeneralUtility::makeInstance(SiteMatcher::class);
    }

    protected function getSiteFinder(): SiteFinder
    {
        return GeneralUtility::makeInstance(SiteFinder::class);
    }
}
