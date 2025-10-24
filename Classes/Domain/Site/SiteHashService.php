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

use ApacheSolrForTypo3\Solr\Event\Site\AfterSiteHashHasBeenDeterminedForSiteEvent;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site as TYPO3Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SiteHashService
 *
 * Responsible to provide site-hash related service methods.
 */
class SiteHashService
{
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        protected SiteFinder $siteFinder,
        protected ExtensionConfiguration $extensionConfiguration,
        ?EventDispatcherInterface $eventDispatcherInterface = null,
    ) {
        $this->eventDispatcher = $eventDispatcherInterface ?? GeneralUtility::makeInstance(EventDispatcherInterface::class);
    }

    /**
     * Resolves magic keywords in allowed sites configuration.
     * Supported keywords:
     *   __solr_current_site - The domain of the site the query has been started from
     *   __current_site - Same as __solr_current_site
     *   __all - Adds all domains as allowed sites
     *   * - Means all sites are allowed, same as no siteHash
     *
     * @param int $pageId A page ID that is then resolved to the site it belongs to
     * @param string|null $allowedSitesConfiguration TypoScript setting for allowed sites
     * @return string List of allowed sites/domains, magic keywords resolved
     */
    public function getAllowedSitesForPageIdAndAllowedSitesConfiguration(
        int $pageId,
        ?string $allowedSitesConfiguration = '',
    ): string {
        if ($allowedSitesConfiguration === '__all') {
            return $this->extensionConfiguration->getSiteHashStrategy() === 1
                ? $this->getIdentifiersOfAllSites()
                : $this->getDomainListOfAllSites();
        }
        if ($allowedSitesConfiguration === '*') {
            return '*';
        }
        // we thread empty allowed site configurations as __solr_current_site since this is the default behaviour
        $allowedSitesConfiguration = empty($allowedSitesConfiguration) ? '__solr_current_site' : $allowedSitesConfiguration;

        if ($this->extensionConfiguration->getSiteHashStrategy() === 0) {
            return $this->getDomainByPageIdAndReplaceMarkers($pageId, $allowedSitesConfiguration);
        }
        return $this->getSiteIdentifierByPageIdAndReplaceMarkers($pageId, $allowedSitesConfiguration);
    }

    public function getSiteHash(TYPO3Site $site): string
    {
        static $siteHashes = [];
        if (isset($siteHashes[$site->getIdentifier()])) {
            return $siteHashes[$site->getIdentifier()];
        }

        $siteHash = $this->getSiteHashForSiteIdentifier($site->getIdentifier());

        $event = $this->eventDispatcher->dispatch(
            new AfterSiteHashHasBeenDeterminedForSiteEvent(
                $siteHash,
                $site,
                $this->extensionConfiguration,
            ),
        );
        $siteHashes[$site->getIdentifier()] = $event->getSiteHash();
        return $siteHashes[$site->getIdentifier()];
    }

    /**
     * Gets the site hash for a given domain
     *
     * @deprecated The method SiteHashService::getSiteHashForDomain() is deprecated and will be removed in version 13.1.x+.
     *             Use SiteHashService::getSiteHashForSiteIdentifier() or SiteHashService::getSiteHash() instead.
     */
    public function getSiteHashForDomain(string $domain): string
    {
        trigger_error(
            'The method SiteHashService::getSiteHashForDomain() is deprecated and will be removed in version 13.1.x+.' .
            'Use SiteHashService::getSiteHashForSiteIdentifier() or SiteHashService::getSiteHash() instead.',
            E_USER_DEPRECATED,
        );
        return $this->getSiteHashForSiteIdentifier($domain);
    }

    /**
     * Gets the site hash for a given site-identifier
     */
    public function getSiteHashForSiteIdentifier(string $siteIdentifier): string
    {
        static $siteHashes = [];
        if (isset($siteHashes[$siteIdentifier])) {
            return $siteHashes[$siteIdentifier];
        }

        $applicationContext = (string)Environment::getContext();
        if ($this->extensionConfiguration->getSiteHashStrategy() === 0) {
            $applicationContext = '';
        }
        $siteHashes[$siteIdentifier] = hash('sha1', $applicationContext . $siteIdentifier . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] . 'tx_solr');
        return $siteHashes[$siteIdentifier];
    }

    /**
     * Returns a comma separated list of all site-identifiers.
     */
    protected function getIdentifiersOfAllSites(): string
    {
        $sites = $this->siteFinder->getAllSites();
        $siteIdentifiers = [];
        foreach ($sites as $typo3Site) {
            $connections = SiteUtility::getAllSolrConnectionConfigurations($typo3Site);
            if (!empty($connections)) {
                $siteIdentifiers[] = $typo3Site->getIdentifier();
            }
        }

        return implode(',', $siteIdentifiers);
    }

    /**
     * Returns a comma separated list of all domains from all sites.
     *
     * @deprecated SiteHashService::getDomainListOfAllSites() is deprecated and will be removed in v14.
     *             Use SiteHashService::getIdentifiersOfAllSites() instead.
     */
    protected function getDomainListOfAllSites(): string
    {
        $sites = $this->siteFinder->getAllSites();
        $domains = [];
        foreach ($sites as $typo3Site) {
            $connections = SiteUtility::getAllSolrConnectionConfigurations($typo3Site);
            if (!empty($connections)) {
                $domains[] = $typo3Site->getBase()->getHost();
            }
        }

        return implode(',', $domains);
    }

    /**
     * Retrieves the site identifier of the site that belongs to the passed pageId and replaces their markers __solr_current_site
     * and __current_site.
     */
    protected function getSiteIdentifierByPageIdAndReplaceMarkers(int $pageId, string $allowedSitesConfiguration): string
    {
        try {
            $typo3Site = $this->siteFinder->getSiteByPageId($pageId);
            $siteIdentifierOfPage = $typo3Site->getIdentifier();
        } catch (SiteNotFoundException) {
            return '';
        }

        $allowedSites = str_replace(['__solr_current_site', '__current_site'], $siteIdentifierOfPage, $allowedSitesConfiguration);
        return (string)$allowedSites;
    }

    /**
     * Retrieves the domain of the site that belongs to the passed pageId and replaces their markers __solr_current_site
     * and __current_site.
     *
     * @deprecated SiteHashService::getDomainByPageIdAndReplaceMarkers() is deprecated and will be removed in v14.
     *             Use SiteHashService::getSiteIdentifierByPageIdAndReplaceMarkers() instead.
     */
    protected function getDomainByPageIdAndReplaceMarkers(int $pageId, string $allowedSitesConfiguration): string
    {
        try {
            $typo3Site = $this->siteFinder->getSiteByPageId($pageId);
            $domainOfPage = $typo3Site->getBase()->getHost();
        } catch (SiteNotFoundException) {
            return '';
        }

        $allowedSites = str_replace(['__solr_current_site', '__current_site'], $domainOfPage, $allowedSitesConfiguration);
        return (string)$allowedSites;
    }
}
