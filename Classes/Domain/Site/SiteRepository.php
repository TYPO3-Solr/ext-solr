<?php

namespace ApacheSolrForTypo3\Solr\Domain\Site;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 - Thomas Hohn <tho@systime.dk>
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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SiteRepository
 *
 * Responsible for...
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class SiteRepository
{
    /**
     * Rootpage resolver
     *
     * @var RootPageResolver
     */
    protected $rootPageResolver;

    /**
     * @var TwoLevelCache
     */
    protected $runtimeCache;

    /**
     * SiteRepository constructor.
     *
     * @param RootPageResolver|null $rootPageResolver
     * @param TwoLevelCache|null $twoLevelCache
     */
    public function __construct(RootPageResolver $rootPageResolver = null, TwoLevelCache $twoLevelCache = null)
    {
        $this->rootPageResolver = isset($rootPageResolver) ? $rootPageResolver : GeneralUtility::makeInstance(RootPageResolver::class);
        $this->runtimeCache = isset($twoLevelCache) ? $twoLevelCache : GeneralUtility::makeInstance(TwoLevelCache::class,
            'cache_runtime');
    }

    /**
     * Gets the Site for a specific page Id.
     *
     * @param int $pageId The page Id to get a Site object for.
     *
     * @return Site Site for the given page Id.
     */
    public function getSiteByPageId($pageId)
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($pageId);
        $cacheId = 'SiteRepository' . '_' . 'getSiteByPageId' . '_' . $rootPageId;

        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = GeneralUtility::makeInstance(Site::class, $rootPageId);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * Returns the first available Site.
     *
     * @param bool $stopOnInvalidSite
     *
     * @return Site
     */
    public function getFirstAvailableSite($stopOnInvalidSite = false)
    {
        $sites = $this->getAvailableSites($stopOnInvalidSite);
        return array_shift($sites);
    }

    /**
     * Gets all available TYPO3 sites with Solr configured.
     *
     * @param bool $stopOnInvalidSite
     *
     * @return Site[] An array of available sites
     */
    public function getAvailableSites($stopOnInvalidSite = false)
    {
        $sites = [];
        $cacheId = 'SiteRepository' . '_' . 'getAvailableSites';

        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $servers = $this->getSolrServersFromRegistry();

        foreach ($servers as $server) {
            if (isset($sites[$server['rootPageUid']])) {
                //get each site only once
                continue;
            }

            try {
                $sites[$server['rootPageUid']] = GeneralUtility::makeInstance(Site::class, $server['rootPageUid']);
            } catch (\InvalidArgumentException $e) {
                if ($stopOnInvalidSite) {
                    throw $e;
                }
            }
        }

        $methodResult = $sites;
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * Gets the system languages (IDs) for which Solr connections have been
     * configured.
     *
     * @return array Array of system language IDs for which connections have been configured on this site.
     */
    public function getAllLanguages(Site $site)
    {
        $siteLanguages = [];

        $servers = $this->getSolrServersFromRegistry();

        foreach ($servers as $connectionKey => $solrConnection) {
            list($siteRootPageId, $systemLanguageId) = explode('|',
                $connectionKey);

            if ($siteRootPageId == $site->getRootPage()) {
                $siteLanguages[] = $systemLanguageId;
            }
        }

        return $siteLanguages;
    }

    /**
     * Creates a dropdown selector of available TYPO3 sites with Solr
     * configured.
     *
     * @param string $selectorName Name to be used in the select's name attribute
     * @param Site $selectedSite Optional, currently selected site
     *
     * @return string Site selector HTML code
     * @todo Extract into own class like indexing configuration selector
     */
    public function getAvailableSitesSelector(
        $selectorName,
        Site $selectedSite = null
    ) {
        $sites = $this->getAvailableSites();
        $selector = '<select name="' . $selectorName . '" class="form-control">';

        foreach ($sites as $site) {
            $selectedAttribute = '';
            if ($selectedSite !== null && $site->getRootPageId() == $selectedSite->getRootPageId()) {
                $selectedAttribute = ' selected="selected"';
            }

            $selector .= '<option value="' . $site->getRootPageId() . '"' . $selectedAttribute . '>'
                . $site->getLabel()
                . '</option>';
        }

        $selector .= '</select>';

        return $selector;
    }

    /**
     * Retrieves the configured solr servers from the registry.
     *
     * @return array
     */
    protected function getSolrServersFromRegistry()
    {
        /** @var $registry Registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $servers = (array)$registry->get('tx_solr', 'servers', []);
        return $servers;
    }
}
