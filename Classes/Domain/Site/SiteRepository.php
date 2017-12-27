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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Service\SiteService;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * SiteRepository
 *
 * Responsible to retrieve instances of Site objects
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
     * @var Registry
     */
    protected $registry;

    /**
     * SiteRepository constructor.
     *
     * @param RootPageResolver|null $rootPageResolver
     * @param TwoLevelCache|null $twoLevelCache
     * @param Registry|null $registry
     */
    public function __construct(RootPageResolver $rootPageResolver = null, TwoLevelCache $twoLevelCache = null, Registry $registry = null)
    {
        $this->rootPageResolver = isset($rootPageResolver) ? $rootPageResolver : GeneralUtility::makeInstance(RootPageResolver::class);
        $this->runtimeCache = isset($twoLevelCache) ? $twoLevelCache : GeneralUtility::makeInstance(TwoLevelCache::class, 'cache_runtime');
        $this->registry = isset($registry) ? $registry : GeneralUtility::makeInstance(Registry::class);
    }

    /**
     * Gets the Site for a specific page Id.
     *
     * @param int $pageId The page Id to get a Site object for.
     * @param string $mountPointIdentifier
     * @return Site Site for the given page Id.
     */
    public function getSiteByPageId($pageId, $mountPointIdentifier = '')
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($pageId, false, $mountPointIdentifier);
        return $this->getSiteByRootPageId($rootPageId);
    }

    /**
     * Gets the Site for a specific root page Id.
     *
     * @param int $rootPageId Root page Id to get a Site object for.
     * @return Site Site for the given page Id.
     */
    public function getSiteByRootPageId($rootPageId)
    {
        $cacheId = 'SiteRepository' . '_' . 'getSiteByPageId' . '_' . $rootPageId;

        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = $this->buildSite($rootPageId);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * Returns the first available Site.
     *
     * @param bool $stopOnInvalidSite
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
                $sites[$server['rootPageUid']] = $this->buildSite($server['rootPageUid']);
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
            list($siteRootPageId, $systemLanguageId) = explode('|', $connectionKey);

            if ($siteRootPageId == $site->getRootPageId()) {
                $siteLanguages[] = $systemLanguageId;
            }
        }

        return $siteLanguages;
    }

    /**
     * Creates an instance of the Site object.
     *
     * @param integer $rootPageId
     * @throws \InvalidArgumentException
     * @return Site
     */
    protected function buildSite($rootPageId)
    {
        $rootPageRecord = (array)BackendUtility::getRecord('pages', $rootPageId);

        $this->validateRootPageRecord($rootPageId, $rootPageRecord);
        $solrConfiguration = Util::getSolrConfigurationFromPageId($rootPageId);
        $domain = $this->getDomainFromConfigurationOrFallbackToDomainRecord($rootPageId);
        $siteHash = $this->getSiteHashForDomain($domain);

        return GeneralUtility::makeInstance(Site::class, $solrConfiguration, $rootPageRecord, $domain, $siteHash);
    }

    /**
     * Retrieves the configured solr servers from the registry.
     *
     * @return array
     */
    protected function getSolrServersFromRegistry()
    {
        $servers = (array)$this->registry->get('tx_solr', 'servers', []);
        return $servers;
    }

    /**
     * @param $rootPageId
     * @return NULL|string
     */
    protected function getDomainFromConfigurationOrFallbackToDomainRecord($rootPageId)
    {
            /** @var $siteService SiteService */
        $siteService = GeneralUtility::makeInstance(SiteService::class);
        $domain = $siteService->getFirstDomainForRootPage($rootPageId);
        if ($domain === '') {
            $pageSelect = GeneralUtility::makeInstance(PageRepository::class);
            $rootLine = $pageSelect->getRootLine($rootPageId);
            $domain = BackendUtility::firstDomainRecord($rootLine);
            return (string)$domain;
        }

        return $domain;
    }

    /**
     * @param string $domain
     * @return string
     */
    protected function getSiteHashForDomain($domain)
    {
        /** @var $siteHashService SiteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        $siteHash = $siteHashService->getSiteHashForDomain($domain);
        return $siteHash;
    }

    /**
     * @param int $rootPageId
     * @param array $rootPageRecord
     * @throws \InvalidArgumentException
     */
    protected function validateRootPageRecord($rootPageId, $rootPageRecord)
    {
        if (empty($rootPageRecord)) {
            throw new \InvalidArgumentException(
                'The rootPageRecord for the given rootPageRecord ID \'' . $rootPageId . '\' could not be found in the database and can therefore not be used as site root rootPageRecord.',
                1487326416
            );
        }

        if (!Site::isRootPage($rootPageRecord)) {
            throw new \InvalidArgumentException(
                'The rootPageRecord for the given rootPageRecord ID \'' . $rootPageId . '\' is not marked as root rootPageRecord and can therefore not be used as site root rootPageRecord.',
                1309272922
            );
        }
    }
}
