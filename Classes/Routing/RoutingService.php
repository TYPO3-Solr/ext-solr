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
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This service class bundles method required to process and manipulate routes.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class RoutingService
{
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

            return $result[] = $settings;
        }

        return [];
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
            $site = $this->getSiteFinder()->getSiteByPageId($pageUid);
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
     * Returns the site finder
     *
     * @return SiteFinder|null
     */
    protected function getSiteFinder(): ?SiteFinder
    {
        return GeneralUtility::makeInstance(SiteFinder::class);
    }
}