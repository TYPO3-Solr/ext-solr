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

use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
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
     * Settings from routing configuration
     *
     * @var array
     */
    protected $settings = [];

    /**
     * List of TYPO3 core parameters, that we should ignore
     *
     * @see \TYPO3\CMS\Frontend\Page\CacheHashCalculator::isCoreParameter
     * @var string[]
     */
    protected $coreParameters = ['no_cache', 'cHash', 'id', 'MP', 'type'];

    /**
     * RoutingService constructor.
     * @param array $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
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
        if (isset($this->settings['pluginNamespace']) && !empty(trim($this->settings['pluginNamespace']))) {
            return (string)$this->settings['pluginNamespace'];
        }
        return 'tx_solr';
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
            [$facetName, $facetValue] = explode(':', $queryParamValue, 2);
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
            if (!isset($queryParameterMapSwitched[$queryParamName])) {
                $newQueryParams[$queryParamName] = $queryParamValue;
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
            function ($value) use ($self) {
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
            $this->contactFilterValues($queryParams[$this->getPluginNamespace()]['filter']);

        return $this->cleanUpQueryParameters($queryParams);
    }

    /**
     * This method expect a filter array that should be concat instead of the whole query
     *
     * @param array $filterArray
     * @return array
     */
    public function contactFilterValues(array $filterArray): array
    {
        if (empty($filterArray)) {
            return $filterArray;
        }

        if (!$this->shouldConcatQueryParameters()) {
            return $filterArray;
        }

        $queryParameterMap = $this->getQueryParameterMap();
        $newFilterArray = [];
        // Collect parameter names and rename parameter if required
        foreach ($filterArray as $set) {
            [$facetName, $facetValue] = explode(':', $set, 2);
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
            $newFilterArray[$facetName] = $facetName . ':' . $this->queryParameterFacetsToString($facetValues);
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
            [$facetName, $facetValuesString] = explode(':', $set, 2);

            $facetValues = explode($this->getQueryParameterValueSeparator(), $facetValuesString);
            foreach ($facetValues as $facetValue) {
                $newQueryParams[] = $facetName . ':' . $facetValue;
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
     * Builds a string out of multiple facet values
     *
     * @param array $facets
     * @return string
     */
    public function pathFacetsToString(array $facets): string
    {
        sort($facets);
        return implode($this->getFacetValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     *
     * @param array $facets
     * @return string
     */
    public function facetsToString(array $facets): string
    {
        sort($facets);
        return implode($this->getFacetValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     *
     * @param string $facets
     * @return array
     */
    public function pathFacetStringToArray(string $facets): array
    {
        return explode($this->getFacetValueSeparator(), $facets);
    }

    /**
     * Builds a string out of multiple facet values
     *
     * @param string $facets
     * @return array
     */
    public function facetStringToArray(string $facets): array
    {
        return explode($this->getFacetValueSeparator(), $facets);
    }

    /**
     * Returns the multi value separator
     * @return string
     */
    public function getFacetValueSeparator(): string
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
        return $this->getFacetValueSeparator();
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
        if (!($site instanceof Site)) {
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
            if (!in_array($pageUid, $settings['limitToPages'])) {
                continue;
            }
            // TODO: Instead of checking a string, check an interface (special interface for combined enhancer)
            //       This have be enabled by configuration to avoid long rendering times
            if (empty($settings) || !isset($settings['type']) || $settings['type'] !== 'CombinedFacetEnhancer') {
                continue;
            }
            $result[] = $settings;
        }

        return $result;
    }

    /**
     * Retrieve the site configuration by URI
     *
     * @param UriInterface $uri
     * @return Site|null
     */
    public function findSiteByUri(UriInterface $uri): ?Site
    {
        $sites = $this->getSiteFinder()->getAllSites();
        if (count($sites) === 1) {
            return array_values($sites)[0];
        }

        foreach ($sites as $siteKey => $site) {
            if ($site->getBase()->getHost() !== $uri->getHost()) {
                continue;
            }

            return $site;
        }

        return null;
    }

    /**
     * Retrieve the site by given UID
     *
     * @param int $pageUid
     * @return Site|null
     */
    public function findSiteByUid(int $pageUid): ?Site
    {
        try {
            $site = $this->getSiteFinder()
                ->getSiteByPageId($pageUid);
            return $site;
        } catch (SiteNotFoundException $exception) {
            return null;
        }
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
     * Returns the current language
     * @TODO Improvement required: Currently we expect that the longest length for base is at the end of the language array
     *       This may be incorrect and lead to wrong results.
     *
     * @param Site $site
     * @param UriInterface $uri
     * @return SiteLanguage
     */
    public function determineSiteLanguage(Site $site, UriInterface $uri): SiteLanguage
    {
        $configuration = $site->getConfiguration();
        if (empty($configuration) || empty($configuration['languages']) || !is_array($configuration['languages'])) {
            $this->logger
                ->info('No language configuration available! Return default language');
            return $site->getDefaultLanguage();
        }
        $languageId = -1;
        $languages = array_reverse($configuration['languages']);

        foreach ($languages as $language) {
            if (empty($language['base'])) {
                continue;
            }

            $basePath = $language['base'];

            /*
             * There different versions of a domain are possible
             * - http://domain.example
             * - https://domain.example
             * - ://domain.example
             *
             * It is possible that the base contains a path too.
             * In order to keep it simple as possible, we convert the base into an URI object
             */
            if (mb_substr($language['base'], 0, 1) !== '/') {
                try {
                    $baseUri = new Uri($language['base']);

                    // Host not match ... base is not what we are looking for
                    if ($baseUri->getHost() !== $uri->getHost()) {
                        continue;
                    }
                    // TODO Needs to be testet on URL encoding
                    $basePath = $baseUri->getPath();
                } catch (\Exception $exception) {
                    // Base could not be parsed as a URI
                    $this->logger
                        ->error(vsprintf('Could not parse language base "%1$s" as URI', [$language['base']]));
                }
            }

            /*
             * Only the path segment need to be checked
             */
            if (mb_substr($uri->getPath(), 0, mb_strlen($basePath)) === $basePath) {
                $languageId = (int)$language['languageId'];
                break;
            }
            if (mb_substr(urldecode($uri->getPath()), 0, mb_strlen($basePath)) === $basePath) {
                $languageId = (int)$language['languageId'];
                break;
            }
        }

        $language = null;
        if ($languageId > 0) {
            try {
                $language = $site->getLanguageById($languageId);
            } catch (\InvalidArgumentException $invalidArgumentException) {
                $this->logger
                    ->error(vsprintf('Could not find language by ID "%1$s"', [$languageId]));
            }
        }

        if (!($language instanceof SiteLanguage)) {
            $language = $site->getDefaultLanguage();
        }

        return $language;
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