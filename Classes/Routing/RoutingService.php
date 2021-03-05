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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageSlugCandidateProvider;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This service class bundles method required to process and manipulate routes.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class RoutingService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Default plugin namespace
     */
    const PLUGIN_NAMESPACE = 'tx_solr';

    /**
     * Settings from routing configuration
     *
     * @var array
     */
    protected $settings = [];

    /**
     * List of filter that are placed as path arguments
     *
     * @var array
     */
    protected $pathArguments = [];

    /**
     * Plugin/extension namespace
     *
     * @var string
     */
    protected $pluginNamespace = 'tx_solr';

    /**
     * List of TYPO3 core parameters, that we should ignore
     *
     * @see \TYPO3\CMS\Frontend\Page\CacheHashCalculator::isCoreParameter
     * @var string[]
     */
    protected $coreParameters = ['no_cache', 'cHash', 'id', 'MP', 'type'];

    /**
     * RoutingService constructor.
     *
     * @param array $settings
     * @param string $pluginNamespace
     */
    public function __construct(array $settings = [], string $pluginNamespace = self::PLUGIN_NAMESPACE)
    {
        $this->settings = $settings;
        $this->pluginNamespace = $pluginNamespace;
        if (empty($this->pluginNamespace)) {
            $this->pluginNamespace = self::PLUGIN_NAMESPACE;
        }
    }

    /**
     * Creates a clone of the current service and replace the settings inside
     *
     * @param array $settings
     * @return RoutingService
     */
    public function withSettings(array $settings): RoutingService
    {
        $service = clone $this;
        $service->settings = $settings;
        return $service;
    }

    /**
     * Creates a clone of the current service and replace the settings inside
     *
     * @param array $pathArguments
     * @return RoutingService
     */
    public function withPathArguments(array $pathArguments): RoutingService
    {
        $service = clone $this;
        $service->pathArguments = $pathArguments;
        return $service;
    }

    /**
     * Load configuration from routing configuration
     *
     * @param array $routingConfiguration
     * @return $this
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
        }

        if (isset($routingConfiguration['_arguments'])) {
            $this->pathArguments = $routingConfiguration['_arguments'];
        }


        return $this;
    }

    /**
     * Reset the routing service
     *
     * @return $this
     */
    public function reset(): RoutingService
    {
        $this->settings = [];
        $this->pathArguments = [];
        $this->pluginNamespace = self::PLUGIN_NAMESPACE;
        return $this;
    }

    /**
     * Test if the given parameter is a Core parameter
     *
     * @see \TYPO3\CMS\Frontend\Page\CacheHashCalculator::isCoreParameter
     * @param string $parameterName
     * @return bool
     */
    public function isCoreParameter(string $parameterName): bool
    {
        return in_array($parameterName, $this->coreParameters);
    }

    /**
     * This returns the plugin namespace
     * @see https://docs.typo3.org/p/apache-solr-for-typo3/solr/master/en-us/Configuration/Reference/TxSolrView.html#pluginnamespace
     *
     * @return string
     */
    public function getPluginNamespace(): string
    {
        return $this->pluginNamespace;
    }

    /**
     * Determine if an enhancer is in use for Solr
     *
     * @param string $enhancerName
     * @return bool
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
     * Masks Solr filter inside of the query parameters
     *
     * @param string $uriPath
     * @return string
     */
    public function finalizePathQuery(string $uriPath): string
    {
        $pathSegments = explode('/', $uriPath);
        $query = array_pop($pathSegments);
        $queryValues = explode($this->getMultiValueSeparatorForPathSegment(), $query);

        /*
         * In some constellations the path query contains the facet type in front.
         * This leads to the result, that the query values could contain the same facet value multiple times.
         *
         * In order to avoid this behaviour, the query values need to be checked and clean up.
         * 1. Remove possible prefix information
         * 2. Apply character replacements
         * 3. Filter duplicate values
         */
        for ($i = 0; $i < count($queryValues); $i++) {
            $queryValues[$i] = urldecode($queryValues[$i]);
            if ($this->containsFacetAndValueSeparator((string)$queryValues[$i])) {
                [$facetName, $facetValue] = explode(
                    $this->detectFacetAndValueSeparator((string)$queryValues[$i]),
                    (string)$queryValues[$i],
                    2
                );

                if ($this->isPathArgument((string)$facetName)) {
                    $queryValues[$i] = $facetValue;
                }

            }
            $queryValues[$i] = $this->encodeStringForPathSegment($queryValues[$i]);
        }

        $queryValues = array_unique($queryValues);
        sort($queryValues);

        $pathSegments[] = implode(
            $this->getMultiValueSeparatorForPathSegment(),
            $queryValues
        );
        return implode('/', $pathSegments);
    }

    /**
     * This method checks if the query parameter should be masked.
     *
     * @return bool
     */
    public function shouldMaskQueryParameter(): bool
    {
        if (!isset($this->settings['query']['mask']) ||
            !(bool)$this->settings['query']['mask']) {
            return false;
        }

        $targetFields = $this->getQueryParameterMap();

        return !empty($targetFields);
    }

    /**
     * Masks Solr filter inside of the query parameters
     *
     * @param array $queryParams
     * @return array
     */
    public function maskQueryParameters(array $queryParams): array
    {
        if (!$this->shouldMaskQueryParameter()) {
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()])) {
            $this->logger
                ->error('Mask error: Query parameters has no entry for namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->info('Mask info: Query parameters has no filter in namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }
        $queryParameterMap = $this->getQueryParameterMap();
        $newQueryParams = $queryParams;

        $newFilterArray = [];
        foreach ($newQueryParams[$this->getPluginNamespace()]['filter'] as $queryParamName => $queryParamValue) {
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
     *
     * @param array $queryParams
     * @return array
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
     *
     * @return bool
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

        return isset($this->settings['query']['concat']) && (bool)$this->settings['query']['concat'];
    }

    /**
     * Returns the query parameter map
     *
     * Note TYPO3 core query arguments removed from the configured map!
     *
     * @return array
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
            function($value) use ($self) {
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
     * @param array $queryParams
     * @return array
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

        if (!isset($queryParams[$this->getPluginNamespace()]['filter'])) {
            $this->logger
                ->info('Mask info: Query parameters has no filter in namespace ' . $this->getPluginNamespace());
            return $queryParams;
        }

        $queryParams[$this->getPluginNamespace()]['filter'] =
            $this->concatFilterValues($queryParams[$this->getPluginNamespace()]['filter']);

        return $this->cleanUpQueryParameters($queryParams);
    }

    /**
     * This method expect a filter array that should be concat instead of the whole query
     *
     * @param array $filterArray
     * @return array
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
     *
     * @param array $queryParams
     * @return array
     */
    public function inflateQueryParameter(array $queryParams = []): array
    {
        if (!$this->shouldConcatQueryParameters()) {
            return $queryParams;
        }

        if (!isset($queryParams[$this->getPluginNamespace()])) {
            $queryParams[$this->getPluginNamespace()] = [];
        }

        if (!isset($queryParams[$this->getPluginNamespace()]['filter'])) {
            $queryParams[$this->getPluginNamespace()]['filter'] = [];
        }

        $newQueryParams = [];
        foreach ($queryParams[$this->getPluginNamespace()]['filter'] as $set) {
            $separator = $this->detectFacetAndValueSeparator((string)$set);
            [$facetName, $facetValuesString] = explode($separator, $set, 2);

            $facetValues = explode($this->getQueryParameterValueSeparator(), $facetValuesString);
            foreach ($facetValues as $facetValue) {
                $newQueryParams[] = $facetName . $separator . $facetValue;
            }
        }
        $queryParams[$this->getPluginNamespace()]['filter'] = array_values($newQueryParams);

        return $this->cleanUpQueryParameters($queryParams);
    }

    /**
     * Cleanup the query parameters, to avoid empty solr arguments
     *
     * @param array $queryParams
     * @return array
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
     * @param array $facets
     * @return string
     */
    public function queryParameterFacetsToString(array $facets): string
    {
        sort($facets);
        return implode($this->getQueryParameterValueSeparator(), $facets);
    }

    /**
     * Returns the string which separates the facet from the value
     *
     * @param string $facetWithValue
     * @return string
     */
    public function detectFacetAndValueSeparator(string $facetWithValue): string
    {
        $separator = ':';
        if (mb_strpos($facetWithValue, '%3A') !== false) {
            $separator = '%3A';
        }

        return $separator;
    }

    /**
     * Check if given facet value combination contains a separator
     *
     * @param string $facetWithValue
     * @return bool
     */
    public function containsFacetAndValueSeparator(string $facetWithValue): bool
    {
        if (mb_strpos($facetWithValue, ':') === false && mb_strpos($facetWithValue, '%3A') === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the mapping array to replace characters within a facet value for a given type
     *
     * @param string $type
     * @return array
     */
    protected function getReplacementMap(string $type): array
    {
        if (is_array($this->settings['facet-' . $type]['replaceCharacters'])) {
            return $this->settings['facet-' . $type]['replaceCharacters'];
        }
        if (is_array($this->settings['replaceCharacters'])) {
            return $this->settings['replaceCharacters'];
        }
        return [];
    }

    /**
     * Apply character map for a given type and url encode it
     *
     * @param string $type
     * @param string $string
     * @return string
     */
    public function applyCharacterReplacementForType(string $type, string $string): string
    {
        $replacementMap = $this->getReplacementMap($type);
        if (!empty($replacementMap)) {
            foreach ($replacementMap as $search => $replace) {
                $string = str_replace($search, $replace, $string);
            }
        }

        return urlencode($string);
    }

    /**
     * Encode a string for path segment
     *
     * @param string $string
     * @return string
     */
    public function encodeStringForPathSegment(string $string): string
    {
        return $this->applyCharacterReplacementForType(
            'path',
            $string
        );
    }

    /**
     * Encode a string for path segment
     *
     * @param string $string
     * @return string
     */
    public function decodeStringForPathSegment(string $string): string
    {
        $replacementMap = $this->getReplacementMap('path');
        $string = urldecode($string);

        if (!empty($replacementMap)) {
            foreach ($replacementMap as $search => $replace) {
                $string = str_replace($replace, $search, $string);
            }
        }

        return $string;
    }

    /**
     * Encode a string for query value
     *
     * @param string $string
     * @return string
     */
    public function encodeStringForQueryValue(string $string): string
    {
        return $this->applyCharacterReplacementForType(
            'query',
            $string
        );
    }

    /**
     * Encode a string for path segment
     *
     * @param string $string
     * @return string
     */
    public function decodeStringForQueryValue(string $string): string
    {
        $replacementMap = $this->getReplacementMap('query');
        $string = urldecode($string);
        if (!empty($replacementMap)) {
            foreach ($replacementMap as $search => $replace) {
                $string = str_replace($replace, $search, $string);
            }
        }

        return $string;
    }

    /**
     * Cleanup facet values (strip type if needed)
     *
     * @param array $facetValues
     * @return array
     */
    public function cleanupFacetValues(array $facetValues): array
    {
        for ($i = 0; $i < count($facetValues); $i++) {
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
     *
     * @param array $facets
     * @return string
     */
    public function pathFacetsToString(array $facets): string
    {
        $facets = $this->cleanupFacetValues($facets);
        sort($facets);
        for ($i = 0; $i < count($facets); $i++) {
            $facets[$i] = $this->encodeStringForPathSegment($facets[$i]);
        }
        return implode($this->getDefaultMultiValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     *
     * @param array $facets
     * @return string
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
     * @param string $facets
     * @return array
     */
    public function pathFacetStringToArray(string $facets): array
    {
        $facets = $this->decodeStringForPathSegment($facets);
        return explode($this->getDefaultMultiValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     *
     * @param string $facets
     * @return array
     */
    public function facetStringToArray(string $facets): array
    {
        return explode($this->getDefaultMultiValueSeparator(), $facets);
    }

    /**
     * Returns the multi value separator
     * @return string
     */
    public function getDefaultMultiValueSeparator(): string
    {
        return $this->settings['multiValueSeparator'] ?? ',';
    }

    /**
     * Returns the multi value separator for query parameters
     *
     * @return string
     */
    public function getQueryParameterValueSeparator(): string
    {
        if (isset($this->settings['query']['multiValueSeparator'])) {
            return (string)$this->settings['query']['multiValueSeparator'];
        }

        // Fall back
        return $this->getDefaultMultiValueSeparator();
    }

    /**
     * Returns the multi value separator for query parameters
     *
     * @return string
     */
    public function getMultiValueSeparatorForPathSegment(): string
    {
        if (isset($this->settings['path']['multiValueSeparator'])) {
            return (string)$this->settings['path']['multiValueSeparator'];
        }

        // Fall back
        return $this->getDefaultMultiValueSeparator();
    }

    /**
     * Find a enhancer configuration by a given page id
     *
     * @param int $pageUid
     * @return array
     */
    public function fetchEnhancerByPageUid(int $pageUid): array
    {
        $site = $this->findSiteByUid($pageUid);
        if ($site instanceof NullSite) {
            return [];
        }

        return $this->fetchEnhancerInSiteConfigurationByPageUid(
            $site,
            $pageUid
        );
    }

    /**
     * Returns the route enhancer configuration by given site and page uid
     *
     * @param Site $site
     * @param int $pageUid
     * @return array
     */
    public function fetchEnhancerInSiteConfigurationByPageUid(Site $site, int $pageUid): array
    {
        $configuration = $site->getConfiguration();
        if (empty($configuration['routeEnhancers']) || !is_array($configuration['routeEnhancers'])) {
            return [];
        }
        $result = [];
        foreach ($configuration['routeEnhancers'] as $routing => $settings) {
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
     *
     * @param string $slug
     * @return string
     */
    public function cleanupHeadingSlash(string $slug): string
    {
        if (mb_substr($slug, 0, 1) !== '/') {
            return '/' . $slug;
        } else if (mb_substr($slug, 0, 2) === '//') {
            return mb_substr($slug, 1, mb_strlen($slug) - 1);
        }

        return $slug;
    }

    /**
     * Add heading slash to given slug
     *
     * @param string $slug
     * @return string
     */
    public function addHeadingSlash(string $slug): string
    {
        if (mb_substr($slug, 0, 1) === '/') {
            return $slug;
        }

        return '/' . $slug;
    }

    /**
     * Remove heading slash from given slug
     *
     * @param string $slug
     * @return string
     */
    public function removeHeadingSlash(string $slug): string
    {
        if (mb_substr($slug, 0, 1) !== '/') {
            return $slug;
        }

        return mb_substr($slug, 1, mb_strlen($slug) - 1);
    }

    /**
     * Retrieve the site by given UID
     *
     * @param int $pageUid
     * @return SiteInterface
     */
    public function findSiteByUid(int $pageUid): SiteInterface
    {
        try {
            $site = $this->getSiteFinder()
                ->getSiteByPageId($pageUid);
            return $site;
        } catch (SiteNotFoundException $exception) {
            return new NullSite();
        }
    }

    /**
     * @param Site $site
     * @return PageSlugCandidateProvider
     */
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
     *
     * @param string $base
     * @return UriInterface|null
     */
    public function convertStringIntoUri(string $base): ?UriInterface
    {
        try {
            /* @var Uri $uri */
            $uri = GeneralUtility::makeInstance(
                Uri::class,
                $base
            );

            return $uri;
        } catch (\InvalidArgumentException $argumentException) {
            return null;
        }
    }

    /**
     * In order to search for a path, a possible language prefix need to remove
     *
     * @param SiteLanguage $language
     * @param string $path
     * @return string
     */
    public function stripLanguagePrefixFromPath(SiteLanguage $language, string $path): string
    {
        if ($language->getBase()->getPath() === '/') {
            return $path;
        }

        $pathLength = mb_strlen($language->getBase()->getPath());

        $path = mb_substr($path, $pathLength, mb_strlen($path) - $pathLength);
        if (mb_substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Enrich the current query Params with data from path information
     *
     * @param ServerRequestInterface $request
     * @param array $arguments
     * @param array $parameters
     * @return ServerRequestInterface
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
     *
     * @param string $facetName
     * @return bool
     */
    public function isMappingArgument(string $facetName): bool
    {
        $map = $this->getQueryParameterMap();
        if (isset($map[$facetName]) && $this->shouldMaskQueryParameter()) {
            return true;
        }

        return false;
    }

    /**
     * Check if given facet type is an path argument
     *
     * @param string $facetName
     * @return bool
     */
    public function isPathArgument(string $facetName): bool
    {
        return isset($this->pathArguments[$facetName]);
    }

    /**
     * @param string $variable
     * @return string
     */
    public function reviewVariable(string $variable): string
    {
        if (!$this->containsFacetAndValueSeparator((string)$variable)) {
            return $variable;
        }

        $separator = $this->detectFacetAndValueSeparator((string)$variable);
        [$type, $value] = explode($separator, $variable, 2);

        return $this->isMappingArgument($type) ? $value : $variable;
    }

    /**
     * Remove type prefix from filter
     *
     * @param array $variables
     * @return array
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
     *
     * @param array $queryParams
     * @param string $fieldName
     * @param array $parameters
     * @param array $pathElements
     * @return array
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
        if (strpos($queryKey, '-') !== false) {
            [$tmpQueryKey, $filterName] = explode('-', $tmpQueryKey, 2);
        }
        if (!isset($queryParams[$tmpQueryKey]) || $queryParams[$tmpQueryKey] === null) {
            $queryParams[$tmpQueryKey] = [];
        }

        if (strpos($queryKey, '-') !== false) {
            [$queryKey, $filterName] = explode('-', $queryKey, 2);
            // explode multiple values
            $values = $this->pathFacetStringToArray($parameters[$fieldName]);
            sort($values);

            // @TODO: Support URL data bag
            foreach ($values as $value) {
                $value = $this->decodeStringForPathSegment($value);
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

    /**
     * Return site matcher
     *
     * @return SiteMatcher
     */
    public function getSiteMatcher(): SiteMatcher
    {
        return GeneralUtility::makeInstance(SiteMatcher::class, $this->getSiteFinder());
    }

    /**
     * Returns the site finder
     *
     * @return SiteFinder|null
     */
    protected function getSiteFinder(): ?SiteFinder
    {
        return GeneralUtility::makeInstance(SiteFinder::class);
    }
}
