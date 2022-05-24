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

namespace ApacheSolrForTypo3\Solr\Domain\Site;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use InvalidArgumentException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Site\Entity\Site as CoreSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
    protected RootPageResolver $rootPageResolver;

    /**
     * @var TwoLevelCache
     */
    protected TwoLevelCache $runtimeCache;

    /**
     * @var Registry
     */
    protected Registry $registry;

    /**
     * @var SiteFinder
     */
    protected SiteFinder $siteFinder;

    /**
     * @var ExtensionConfiguration
     */
    protected ExtensionConfiguration $extensionConfiguration;

    /**
     * @var FrontendEnvironment
     */
    protected FrontendEnvironment $frontendEnvironment;

    /**
     * SiteRepository constructor.
     *
     * @param RootPageResolver|null $rootPageResolver
     * @param TwoLevelCache|null $twoLevelCache
     * @param Registry|null $registry
     * @param SiteFinder|null $siteFinder
     * @param ExtensionConfiguration|null $extensionConfiguration
     * @param FrontendEnvironment|null $frontendEnvironment
     */
    public function __construct(
        RootPageResolver $rootPageResolver = null,
        TwoLevelCache $twoLevelCache = null,
        Registry $registry = null,
        SiteFinder $siteFinder = null,
        ExtensionConfiguration $extensionConfiguration = null,
        FrontendEnvironment $frontendEnvironment = null
    ) {
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */'runtime');
        $this->registry = $registry ?? GeneralUtility::makeInstance(Registry::class);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
    }

    /**
     * Gets the Site for a specific page ID.
     *
     * @param int $pageId The page ID to get a Site object for.
     * @param string $mountPointIdentifier
     * @return SiteInterface Site for the given page ID.
     * @throws DBALDriverException
     */
    public function getSiteByPageId(int $pageId, string $mountPointIdentifier = '')
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($pageId, false, $mountPointIdentifier);
        return $this->getSiteByRootPageId($rootPageId);
    }

    /**
     * Gets the Site for a specific root page-id.
     *
     * @param int $rootPageId Root page Id to get a Site object for.
     * @return SiteInterface Site for the given page-id.
     * @throws DBALDriverException
     */
    public function getSiteByRootPageId(int $rootPageId)
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
     * @return Site|null
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getFirstAvailableSite(bool $stopOnInvalidSite = false): ?Site
    {
        $sites = $this->getAvailableSites($stopOnInvalidSite);
        return array_shift($sites);
    }

    /**
     * Gets all available TYPO3 sites with Solr configured.
     *
     * @param bool $stopOnInvalidSite
     * @return Site[] An array of available sites
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getAvailableSites(bool $stopOnInvalidSite = false): array
    {
        $cacheId = 'SiteRepository' . '_' . 'getAvailableSites';

        $sites = $this->runtimeCache->get($cacheId);
        if (!empty($sites)) {
            return $sites;
        }

        $sites = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $this->runtimeCache->set($cacheId, $sites);

        return $sites ?? [];
    }

    /**
     * @param bool $stopOnInvalidSite
     * @return array
     * @throws DBALDriverException
     * @throws Throwable
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
            } catch (Throwable $e) {
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
     * @param int $rootPageId
     * @return SiteInterface
     * @throws DBALDriverException
     */
    protected function buildSite(int $rootPageId)
    {
        $rootPageRecord = BackendUtility::getRecord('pages', $rootPageId);
        if (empty($rootPageRecord)) {
            throw new InvalidArgumentException(
                "The rootPageRecord for the given rootPageRecord ID '$rootPageId' could not be found in the database and can therefore not be used as site root rootPageRecord.",
                1487326416
            );
        }

        $this->validateRootPageRecord($rootPageId, $rootPageRecord);

        return $this->buildTypo3ManagedSite($rootPageRecord);
    }

    /**
     * @param string $domain
     * @return string
     */
    protected function getSiteHashForDomain(string $domain): string
    {
        /** @var $siteHashService SiteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        return $siteHashService->getSiteHashForDomain($domain);
    }

    /**
     * @param int $rootPageId
     * @param array $rootPageRecord
     * @throws InvalidArgumentException
     */
    protected function validateRootPageRecord(int $rootPageId, array $rootPageRecord)
    {
        if (!Site::isRootPage($rootPageRecord)) {
            throw new InvalidArgumentException(
                "The rootPageRecord for the given rootPageRecord ID '$rootPageId' is not marked as root rootPageRecord and can therefore not be used as site root rootPageRecord.",
                1309272922
            );
        }
    }

    /**
     * Builds a TYPO3 managed site with TypoScript configuration.
     *
     * @param array $rootPageRecord
     *
     * @return Site
     *
     * @throws DBALDriverException
     */
    protected function buildTypo3ManagedSite(array $rootPageRecord): ?Site
    {
        $typo3Site = $this->getTypo3Site($rootPageRecord['uid']);
        if (!$typo3Site instanceof CoreSite) {
            return null;
        }

        $domain = $typo3Site->getBase()->getHost();

        $siteHash = $this->getSiteHashForDomain($domain);
        $defaultLanguage = $typo3Site->getDefaultLanguage()->getLanguageId();
        $pageRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $availableLanguageIds = array_map(function ($language) {
            return $language->getLanguageId();
        }, $typo3Site->getLanguages());

        // Try to get first instantiable TSFE for one of site languages, to get TypoScript with `plugin.tx_solr.index.*`,
        // to be able to collect indexing configuration,
        // which are required for BE-Modules/CLI-Commands or RecordMonitor within BE/TCE-commands.
        // If TSFE for none of languages can be initialized, then the \ApacheSolrForTypo3\Solr\Domain\Site\Site object unusable at all,
        // so the rest of the steps in this method are not necessary, and therefore the null will be returned.
        $tsfeFactory = GeneralUtility::makeInstance(FrontendEnvironment\Tsfe::class);
        $tsfeToUseForTypoScriptConfiguration = $tsfeFactory->getTsfeByPageIdAndLanguageFallbackChain($typo3Site->getRootPageId(), ...$availableLanguageIds);
        if (!$tsfeToUseForTypoScriptConfiguration instanceof TypoScriptFrontendController) {
            return null;
        }

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
                    ],
                    'write' => [
                        'scheme' => SiteUtility::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'write', 'http'),
                        'host' => SiteUtility::getConnectionProperty($typo3Site, 'host', $languageUid, 'write', 'localhost'),
                        'port' => (int)SiteUtility::getConnectionProperty($typo3Site, 'port', $languageUid, 'write', 8983),
                        // @todo: transform core to path
                        'path' =>
                            SiteUtility::getConnectionProperty($typo3Site, 'path', $languageUid, 'write', '/solr/') .
                            SiteUtility::getConnectionProperty($typo3Site, 'core', $languageUid, 'write', 'core_en') . '/' ,
                        'username' => SiteUtility::getConnectionProperty($typo3Site, 'username', $languageUid, 'write', ''),
                        'password' => SiteUtility::getConnectionProperty($typo3Site, 'password', $languageUid, 'write', ''),
                    ],

                    'language' => $languageUid,
                ];
            }
        }

        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId(
            $rootPageRecord['uid'],
            $tsfeToUseForTypoScriptConfiguration->getLanguage()->getLanguageId()
        );

        return GeneralUtility::makeInstance(
            Site::class,
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

    /**
     * Returns {@link \TYPO3\CMS\Core\Site\Entity\Site}.
     *
     * @param int $pageUid
     * @return CoreSite|null
     */
    protected function getTypo3Site(int $pageUid): ?CoreSite
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageUid);
        } catch (Throwable $e) {
        }
        return null;
    }
}
