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
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Event\Site\AfterDomainHasBeenDeterminedForSiteEvent;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\FrontendEnvironment;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use Doctrine\DBAL\Exception as DBALException;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site as CoreSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SiteRepository is responsible to retrieve instances of Site objects
 */
class SiteRepository
{
    protected RootPageResolver $rootPageResolver;

    protected TwoLevelCache $runtimeCache;

    protected SiteFinder $siteFinder;

    protected ExtensionConfiguration $extensionConfiguration;

    protected FrontendEnvironment $frontendEnvironment;

    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ?RootPageResolver $rootPageResolver = null,
        ?TwoLevelCache $twoLevelCache = null,
        ?SiteFinder $siteFinder = null,
        ?ExtensionConfiguration $extensionConfiguration = null,
        ?FrontendEnvironment $frontendEnvironment = null,
        ?EventDispatcherInterface $eventDispatcherInterface = null,
    ) {
        $this->rootPageResolver = $rootPageResolver ?? GeneralUtility::makeInstance(RootPageResolver::class);
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, 'runtime');
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
        $this->extensionConfiguration = $extensionConfiguration ?? GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->frontendEnvironment = $frontendEnvironment ?? GeneralUtility::makeInstance(FrontendEnvironment::class);
        $this->eventDispatcher = $eventDispatcherInterface ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Gets the Site for a specific page ID.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     */
    public function getSiteByPageId(int $pageId, string $mountPointIdentifier = ''): ?Site
    {
        $rootPageId = $this->rootPageResolver->getRootPageId($pageId, false, $mountPointIdentifier);
        return $this->getSiteByRootPageId($rootPageId);
    }

    /**
     * Gets the Site for a specific root page-id.
     *
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     */
    public function getSiteByRootPageId(int $rootPageId): ?Site
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
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getFirstAvailableSite(bool $stopOnInvalidSite = false): ?Site
    {
        $siteGenerator = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $siteGenerator->rewind();
        if (!$siteGenerator->valid()) {
            return null;
        }
        $site = $siteGenerator->current();

        return $site instanceof Site ? $site : null;
    }

    /**
     * Gets all available TYPO3 sites with Solr configured.
     *
     * @return Site[] An array of available sites
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function getAvailableSites(bool $stopOnInvalidSite = false): array
    {
        $cacheId = 'SiteRepository' . '_' . 'getAvailableSites';

        $sites = $this->runtimeCache->get($cacheId);
        if (is_array($sites) && $sites !== []) {
            return $sites;
        }

        $siteGenerator = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $siteGenerator->rewind();

        $sites = [];
        if (!$siteGenerator->valid()) {
            return $sites;
        }
        foreach ($siteGenerator as $rootPageId => $site) {
            if (isset($sites[$rootPageId])) {
                //get each site only once
                continue;
            }
            $sites[$rootPageId] = $site;
        }
        $this->runtimeCache->set($cacheId, $sites);

        return $sites;
    }

    /**
     * Check, if there are any managed sites available
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function hasAvailableSites(bool $stopOnInvalidSite = false): bool
    {
        $siteGenerator = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $siteGenerator->rewind();
        if (!$siteGenerator->valid()) {
            return false;
        }

        return ($site = $siteGenerator->current()) && $site instanceof Site;
    }

    /**
     * Check, if there is exactly one managed site available
     * Needed in AbstractModuleController::autoSelectFirstSiteAndRootPageWhenOnlyOneSiteIsAvailable
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    public function hasExactlyOneAvailableSite(bool $stopOnInvalidSite = false): bool
    {
        if (!$this->hasAvailableSites($stopOnInvalidSite)) {
            return false;
        }

        $siteGenerator = $this->getAvailableTYPO3ManagedSites($stopOnInvalidSite);
        $siteGenerator->rewind();
        if (!$siteGenerator->valid()) {
            return false;
        }

        // We start with 1 here as we know from hasAvailableSites() above we have at least one site
        $counter = 1;
        foreach ($siteGenerator as $_) {
            if ($counter > 1) {
                return false;
            }
            $counter++;
        }

        return true;
    }

    /**
     * Returns available TYPO3 sites
     *
     * @return Site[]|Generator
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function getAvailableTYPO3ManagedSites(bool $stopOnInvalidSite): Generator
    {
        foreach ($this->siteFinder->getAllSites() as $typo3Site) {
            try {
                $rootPageId = $typo3Site->getRootPageId();
                $typo3ManagedSolrSite = $this->buildSite($rootPageId);
                if ($typo3ManagedSolrSite->isEnabled()) {
                    yield $rootPageId => $typo3ManagedSolrSite;
                }
            } catch (Throwable $e) {
                if ($stopOnInvalidSite) {
                    throw new UnexpectedTYPO3SiteInitializationException(
                        'Something went wrong on TYPO3 site initialization. See stack trace for more information.',
                        1680859613,
                        $e,
                    );
                }
            }
        }
    }

    /**
     * Creates an instance of the Site object.
     *
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     */
    protected function buildSite(int $rootPageId): ?Site
    {
        $rootPageRecord = BackendUtility::getRecord('pages', $rootPageId);
        if (empty($rootPageRecord)) {
            throw new InvalidArgumentException(
                "The rootPageRecord for the given rootPageRecord ID '$rootPageId' could not be found in the database and can therefore not be used as site root rootPageRecord.",
                1487326416,
            );
        }

        $this->validateRootPageRecord($rootPageRecord);

        return $this->buildTypo3ManagedSite($rootPageRecord);
    }

    /**
     * Returns the site hash for given site.
     */
    protected function getSiteHash(CoreSite $site): string
    {
        /** @var SiteHashService $siteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        return $siteHashService->getSiteHash($site);
    }

    /**
     * Returns the site hash for given domain.
     *
     * @deprecated SiteRepository::getSiteHashForDomain() is soft deprecated and will be removed in v13.1.x+.
     *             The SiteRepository::getSiteHash() will be used instead.
     */
    protected function getSiteHashForDomain(string $domain): string
    {
        /** @var SiteHashService $siteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        return $siteHashService->getSiteHashForSiteIdentifier($domain);
    }

    /**
     * Validates given root page record, if it fits requirements.
     *
     * @param array{
     *    'uid'?: int,
     *    'is_siteroot'?: int
     * } $rootPageRecord
     *
     * @throws InvalidArgumentException
     */
    protected function validateRootPageRecord(array $rootPageRecord): void
    {
        if (!SiteUtility::isRootPage($rootPageRecord)) {
            throw new InvalidArgumentException(
                'The rootPageRecord for the given rootPageRecord ID \'' . $rootPageRecord['uid'] . '\' is not marked as root rootPageRecord and can therefore not be used as site root rootPageRecord.',
                1309272922,
            );
        }
    }

    /**
     * Builds a TYPO3 managed site with TypoScript configuration.
     * @param array{
     *    'uid': int,
     *    'pid'?: int
     * } $rootPageRecord
     *
     * @throws SiteNotFoundException
     */
    protected function buildTypo3ManagedSite(array $rootPageRecord): ?Site
    {
        $typo3Site = $this->getTypo3Site($rootPageRecord['uid']);
        if (!$typo3Site instanceof CoreSite) {
            return null;
        }

        $domain = $typo3Site->getBase()->getHost();
        $event = $this->eventDispatcher->dispatch(
            new AfterDomainHasBeenDeterminedForSiteEvent($domain, $rootPageRecord, $typo3Site, $this->extensionConfiguration),
        );
        $domain = $event->getDomain();

        $siteHash = $this->getSiteHash($typo3Site);
        if ($this->extensionConfiguration->getSiteHashStrategy() === 0) {
            $siteHash = $this->getSiteHashForDomain($domain);
        }

        $defaultLanguage = $typo3Site->getDefaultLanguage()->getLanguageId();
        $pageRepository = GeneralUtility::makeInstance(PagesRepository::class);
        $availableLanguageIds = array_map(static function ($language) {
            return $language->getLanguageId();
        }, $typo3Site->getLanguages());

        // Try to get first instantiable TSFE for one of site languages, to get TypoScript with `plugin.tx_solr.index.*`,
        // to be able to collect indexing configuration,
        // which are required for BE-Modules/CLI-Commands or RecordMonitor within BE/TCE-commands.
        // If TSFE for none of languages can be initialized, then the \ApacheSolrForTypo3\Solr\Domain\Site\Site object unusable at all,
        // so the rest of the steps in this method are not necessary, and therefore the null will be returned.
        $solrConnectionConfigurations = [];

        $firstLanguage = null;
        foreach ($availableLanguageIds as $languageUid) {
            $solrConnection = SiteUtility::getSolrConnectionConfiguration($typo3Site, $languageUid);
            if ($solrConnection !== null) {
                $solrConnectionConfigurations[$languageUid] = $solrConnection;
            }
            if ($firstLanguage === null) {
                $firstLanguage = $typo3Site->getLanguageById($languageUid);
            }
        }

        $solrConfiguration = $this->frontendEnvironment->getSolrConfigurationFromPageId(
            $rootPageRecord['uid'],
            $firstLanguage->getLanguageId(),
        );

        return GeneralUtility::makeInstance(
            Site::class,
            $solrConfiguration,
            $rootPageRecord,
            $domain,
            $siteHash,
            $pageRepository,
            $defaultLanguage,
            $availableLanguageIds,
            $solrConnectionConfigurations,
            $typo3Site,
        );
    }

    /**
     * Returns {@link CoreSite}.
     */
    protected function getTypo3Site(int $pageUid): ?CoreSite
    {
        try {
            return $this->siteFinder->getSiteByPageId($pageUid);
        } catch (Throwable) {
        }
        return null;
    }
}
