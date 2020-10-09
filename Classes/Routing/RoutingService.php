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
     * Query namespace
     *
     * @var string
     */
    protected $queryNamespace = 'tx_solr';

    /**
     * List of default parameters, that we should ignore
     *
     * @var string[]
     */
    protected $noneSolrParameters = ['no_cache', 'cHash', 'id', 'MP', 'type'];

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
     * Should same parameters be flatten?
     *
     * @return bool
     */
    public function shouldFlattenSameParameter(): bool
    {
        if (isset($this->settings['flattenParameter'])) {
            return (bool)$this->settings['flattenParameter'];
        }
        return false;
    }

    /**
     * Deflate the query parameters if configured
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
    public function deflateQueryParameter(array $queryParams = []): array
    {
        if (!$this->shouldFlattenSameParameter()) {
            return $queryParams;
        }

        if (!isset($queryParams['filter'])) {
            return $queryParams;
        }

        $newQueryParams = [];
        foreach ($queryParams['filter'] as $set) {
            [$facetName, $facetValue] = explode(':', $set, 2);
            if (!isset($newQueryParams[$facetName])) {
                $newQueryParams[$facetName] = [$facetValue];
            } else {
                $newQueryParams[$facetName][] = $facetValue;
            }
        }

        foreach ($newQueryParams as $facetName => $facetValues) {
            $newQueryParams[$facetName] = $facetName . ':' . $this->facetsToString($facetValues);
        }

        $queryParams['filter'] = array_values($newQueryParams);

        return $queryParams;
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
        if (!$this->shouldFlattenSameParameter()) {
            return $queryParams;
        }

        if (!isset($queryParams['filter'])) {
            return $queryParams;
        }

        $newQueryParams = [];
        foreach ($queryParams['filter'] as $set) {
            [$facetName, $facetValuesString] = explode(':', $set, 2);

            $facetValues = explode($this->getFacetValueSeparator(), $facetValuesString);
            foreach ($facetValues as $facetValue) {
                $newQueryParams[] = $facetName . ':' . $facetValue;
            }
        }
        $queryParams['filter'] = array_values($newQueryParams);

        return $queryParams;
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
     * Returns the list of parameters, that should ignored
     *
     * @return array
     */
    public function getNonSolrParameters(): array
    {
        $parameters = $this->noneSolrParameters;

        if (isset($this->settings['keepUrlKeys']) && is_array($this->settings['keepUrlKeys'])) {
            $keepKeys = $this->settings['keepUrlKeys'];
            $parameters = array_filter(
                $parameters,
                function ($value) use ($keepKeys) {
                    return !in_array($value, $keepKeys);
                }
            );
        }

        if (isset($this->settings['ignoreUrlKeys']) && is_array($this->settings['ignoreUrlKeys'])) {
            $parameters = array_merge($parameters, $this->settings['ignoreUrlKeys']);
        }

        return array_unique($parameters);
    }

    /**
     * Resolve a URI into a page uid
     *
     * @param UriInterface $uri
     * @return int
     */
    public function uriToPageUid(UriInterface $uri): int
    {
        return 0;
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
            if (empty($settings) || !isset($settings['type']) || $settings['type'] !== 'CombinedFacetEnhancer') {
                continue;
            }
            if (!in_array($pageUid, $settings['limitToPages'])) {
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