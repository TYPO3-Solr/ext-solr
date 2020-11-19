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
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * @var SiteFinder
     */
    protected $siteFinder;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * @var FrontendEnvironment
     */
    protected $frontendEnvironment = null;

    /**
     * SiteRepository constructor.
     *
     * @param RootPageResolver|null $rootPageResolver
     * @param TwoLevelCache|null $twoLevelCache
     * @param Registry|null $registry
     * @param SiteFinder|null $siteFinder
     * @param ExtensionConfiguration| null
     */
    public function __construct(
        RootPageResolver $rootPageResolver = null,
        TwoLevelCache $twoLevelCache = null,
        Registry $registry = null,
        SiteFinder $siteFinder = null,
        ExtensionConfiguration $extensionConfiguration = null,
        FrontendEnvironment $frontendEnvironment = null
    )
    {
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */'cache_runtime');
        $this->registry = $registry ?? GeneralUtility::makeInstance(Registry::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Gets the Site for a specific page Id.
     *
     * @param int $pageId The page Id to get a Site object for.
     * @param string $mountPointIdentifier
     * @return SiteInterface Site for the given page Id.
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
     * @return SiteInterface Site for the given page Id.
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
     * @throws \Exception
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
     * @throws \Exception
     * @return Site[] An array of availablesites
     */
    public function getAvailableSites($stopOnInvalidSite = false)
    {
        $cacheId = 'SiteRepository' . '_' . 'getAvailableSites';

        $sites = $this->runtimeCache->get($cacheId);
        if (!empty($sites)) {
            return $sites;
        }

        $sites = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $this->runtimeCache->set($cacheId, $sites);

        return $sites;
    }

    /**
     * @param bool $stopOnInvalidSite
     * @return array
     * @throws \Exception
     */
    protected function getAvailableTYPO3ManagedSites(bool $stopOnInvalidSite): array
    {
        $typo3ManagedSolrSites = [];
        $typo3Sites = $this->siteFinder->getAllSites();
        foreach ($typo3Sites as $typo3Site) {
            try {
                $rootPageId = $typo3Site->getRootPageId();
                if (isset($typo3ManagedSolrSites[$rootPageId])) {
                    //get each site only once
                    continue;
                }
                $typo3ManagedSolrSite = $this->buildSite($rootPageId);
                if ($typo3ManagedSolrSite->isEnabled()) {
                    $typo3ManagedSolrSites[$rootPageId] = $typo3ManagedSolrSite;
                }

            } catch (\Exception $e) {
                if ($stopOnInvalidSite) {
                    throw $e;
                }
            }
        }
        return $typo3ManagedSolrSites;
    }

     /**
     * Creates an instance of the Site object.
     *
     * @param integer $rootPageId
     * @throws \InvalidArgumentException
     * @return SiteInterface
     */
    protected function buildSite($rootPageId)
    {
        if (empty($rootPageId)) {
            throw new \InvalidArgumentException('Root page id can not be empty');
        }
        $rootPageRecord = (array)BackendUtility::getRecord('pages', $rootPageId);

        $this->validateRootPageRecord($rootPageId, $rootPageRecord);

        return $this->buildTypo3ManagedSite($rootPageRecord);
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

    /**
     * @param array $rootPageRecord
     * @return Typo3ManagedSite
     */
    protected function buildTypo3ManagedSite(array $rootPageRecord): ?Typo3ManagedSite
    {
        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId($rootPageRecord['uid']);
        /** @var \TYPO3\CMS\Core\Site\Entity\Site $typo3Site */
        try {
            $typo3Site = $this->siteFinder->getSiteByPageId($rootPageRecord['uid']);
        } catch (SiteNotFoundException $e) {
            return null;
        }
        $domain = $typo3Site->getBase()->getHost();

        $siteHash = $this->getSiteHashForDomain($domain);
        $defaultLanguage = $typo3Site->getDefaultLanguage()->getLanguageId();
        $pageRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $availableLanguageIds = array_map(function($language) {
            return $language->getLanguageId();
        }, $typo3Site->getLanguages());

        $solrConnectionConfigurations = [];

        foreach ($availableLanguageIds as $languageUid) {
            $solrEnabled = SiteUtility::getConnectionProperty($typo3Site, 'enabled', $languageUid, 'read', true);
            if ($solrEnabled) {
                $solrConnectionConfigurations[$languageUid] = [
                    'connectionKey' =>  $rootPageRecord['uid'] . '|' . $languageUid,
                    'rootPageTitle' => $rootPageRecord['title'],
                    'rootPageUid' => $rootPageRecord['uid'],
                    'read' => [
                        'scheme' => SiteUtility::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'read', 'http'),
                        'host' => SiteUtility::getConnectionProperty($typo3Site, 'host', $languageUid, 'read', 'localhost'),
                        'port' => (int)SiteUtility::getConnectionProperty($typo3Site, 'port', $languageUid, 'read', 8983),
                        // @todo: transform core to path
                        'path' =>
                            SiteUtility::getConnectionProperty($typo3Site, 'path', $languageUid, 'read', '/solr/') .
                            SiteUtility::getConnectionProperty($typo3Site, 'core', $languageUid, 'read', 'core_en') . '/' ,
                        'username' => SiteUtility::getConnectionProperty($typo3Site, 'username', $languageUid, 'read', ''),
                        'password' => SiteUtility::getConnectionProperty($typo3Site, 'password', $languageUid, 'read', ''),
                        'timeout' => SiteUtility::getConnectionProperty($typo3Site, 'timeout', $languageUid, 'read', 0)
                    ],
                    'write' => [
                        'scheme' => SiteUtility::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'write', 'http'),
                        'host' => SiteUtility::getConnectionProperty($typo3Site, 'host', $languageUid, 'write', 'localhost'),
                        'port' => (int)SiteUtility::getConnectionProperty($typo3Site, 'port', $languageUid, 'write', 8983),
                        // @todo: transform core to path
                        'path' =>
                            SiteUtility::getConnectionProperty($typo3Site, 'path', $languageUid, 'read', '/solr/') .
                            SiteUtility::getConnectionProperty($typo3Site, 'core', $languageUid, 'read', 'core_en') . '/' ,
                        'username' => SiteUtility::getConnectionProperty($typo3Site, 'username', $languageUid, 'write', ''),
                        'password' => SiteUtility::getConnectionProperty($typo3Site, 'password', $languageUid, 'write', ''),
                        'timeout' => SiteUtility::getConnectionProperty($typo3Site, 'timeout', $languageUid, 'write', 0)
                    ],

                    'language' => $languageUid
                ];
            }
        }

        return GeneralUtility::makeInstance(
            Typo3ManagedSite::class,
            /** @scrutinizer ignore-type */
            $solrConfiguration,
            /** @scrutinizer ignore-type */
            $rootPageRecord,
            /** @scrutinizer ignore-type */
            $domain,
            /** @scrutinizer ignore-type */
            $siteHash,
            /** @scrutinizer ignore-type */
            $pageRepository,
            /** @scrutinizer ignore-type */
            $defaultLanguage,
            /** @scrutinizer ignore-type */
            $availableLanguageIds,
            /** @scrutinizer ignore-type */
            $solrConnectionConfigurations,
            /** @scrutinizer ignore-type */
            $typo3Site
        );
    }

}
