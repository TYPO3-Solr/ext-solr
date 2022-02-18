<?php

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

use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class
 *
 * (c) 2011-2015 Ingo Renner <ingo@typo3.org>
 */
class Site implements SiteInterface
{
    /**
     * @var TypoScriptConfiguration
     */
    protected TypoScriptConfiguration $configuration;

    /**
     * Root page record.
     *
     * @var array
     */
    protected array $rootPage = [];
    /**
     * @var string
     */
    protected string $domain;

    /**
     * @var string
     */
    protected string $siteHash;

    /**
     * @var PagesRepository
     */
    protected PagesRepository $pagesRepository;

    /**
     * @var int
     */
    protected int $defaultLanguageId = 0;

    /**
     * @var int[] Available language ids
     */
    protected array $availableLanguageIds = [];

    /**
     * @var Typo3Site|null
     */
    protected ?Typo3Site $typo3SiteObject = null;

    /**
     * @var array
     */
    protected array $solrConnectionConfigurations = [];

    /**
     * @var array
     */
    protected array $freeContentModeLanguages = [];


    /**
     * Constructor of Site
     *
     * @todo Use dependency injection instead.
     *
     * @param TypoScriptConfiguration $configuration
     * @param array $page
     * @param string $domain
     * @param string $siteHash
     * @param PagesRepository|null $pagesRepository
     * @param int $defaultLanguageId
     * @param array $availableLanguageIds
     * @param array $solrConnectionConfigurations
     * @param Typo3Site|null $typo3SiteObject
     */
    public function __construct(
        TypoScriptConfiguration $configuration,
        array $page,
        string $domain,
        string $siteHash,
        PagesRepository $pagesRepository = null,
        int $defaultLanguageId = 0,
        array $availableLanguageIds = [],
        array $solrConnectionConfigurations = [],
        Typo3Site $typo3SiteObject = null
    ) {
        $this->configuration = $configuration;
        $this->rootPage = $page;
        $this->domain = $domain;
        $this->siteHash = $siteHash;
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->defaultLanguageId = $defaultLanguageId;
        $this->availableLanguageIds = $availableLanguageIds;
        $this->solrConnectionConfigurations = $solrConnectionConfigurations;
        $this->typo3SiteObject = $typo3SiteObject;
    }

    /**
     * @param int $language
     * @return array
     * @throws NoSolrConnectionFoundException
     */
    public function getSolrConnectionConfiguration(int $language = 0): array
    {
        if (!is_array($this->solrConnectionConfigurations[$language])) {
            /* @var $noSolrConnectionException NoSolrConnectionFoundException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                NoSolrConnectionFoundException::class,
                /** @scrutinizer ignore-type */  'Could not find a Solr connection for root page [' . $this->getRootPageId() . '] and language [' . $language . '].',
                /** @scrutinizer ignore-type */ 1552491117
            );
            $noSolrConnectionException->setRootPageId($this->getRootPageId());
            $noSolrConnectionException->setLanguageId($language);

            throw $noSolrConnectionException;
        }

        return $this->solrConnectionConfigurations[$language];
    }

    /**
     * Returns \TYPO3\CMS\Core\Site\Entity\Site
     *
     * @return Typo3Site
     */
    public function getTypo3SiteObject(): Typo3Site
    {
        return $this->typo3SiteObject;
    }

    /**
     * Checks if current TYPO3 site has languages
     *
     * @return bool
     */
    public function hasFreeContentModeLanguages(): bool
    {
        return !empty($this->getFreeContentModeLanguages());
    }

    /**
     * Return all free content mode languages.
     *
     * Note: There is no "fallback type" nor "fallbacks" for default language 0
     *       See "displayCond" on https://github.com/TYPO3/typo3/blob/1394a4cff5369df3f835dae254b3d4ada2f83c7b/typo3/sysext/backend/Configuration/SiteConfiguration/site_language.php#L403-L416
     *         or https://review.typo3.org/c/Packages/TYPO3.CMS/+/56505/ for more information.
     *
     * @return array|null
     */
    public function getFreeContentModeLanguages(): array
    {
        if (!empty($this->freeContentModeLanguages)) {
            return $this->freeContentModeLanguages;
        }

        if (!$this->typo3SiteObject instanceof Typo3Site) {
            return false;
        }

        foreach ($this->availableLanguageIds as $languageId)
        {
            if ($languageId > 0 && $this->typo3SiteObject->getLanguageById($languageId)->getFallbackType() === 'free') {
                $this->freeContentModeLanguages[$languageId] = $languageId;
            }
        }
        return $this->freeContentModeLanguages;
    }

    /**
     * Takes a page record and checks whether the page is marked as root page.
     *
     * @param array $pageRecord page record
     * @return bool true if the page is marked as root page, false otherwise
     * @todo: move to SiteUtility?
     */
    public static function isRootPage(array $pageRecord): bool
    {
        if (($pageRecord['is_siteroot'] ?? null) == 1) {
            return true;
        }

        return false;
    }

    /**
     * Gets the site's root page ID (uid).
     *
     * @return int The site's root page ID.
     */
    public function getRootPageId(): int
    {
        return (int)$this->rootPage['uid'];
    }

    /**
     * Gets available language id's for this site
     *
     * @return int[] array or language id's
     */
    public function getAvailableLanguageIds(): array
    {
        return $this->availableLanguageIds;
    }

    /**
     * Gets the site's label. The label is build from the the site title and root
     * page ID (uid).
     *
     * @return string The site's label.
     */
    public function getLabel(): string
    {
        $rootlineTitles = [];
        $rootLine = BackendUtility::BEgetRootLine($this->rootPage['uid']);
        // Remove last
        array_pop($rootLine);
        $rootLine = array_reverse($rootLine);
        foreach ($rootLine as $rootLineItem) {
            $rootlineTitles[] = $rootLineItem['title'];
        }
        return implode(' - ', $rootlineTitles) . ', Root Page ID: ' . $this->rootPage['uid'];
    }

    /**
     * Gets the site's Solr TypoScript configuration (plugin.tx_solr.*)
     *
     * Purpose: Interface and Unit test mocking helper method.
     *
     * @return  TypoScriptConfiguration The Solr TypoScript configuration
     */
    public function getSolrConfiguration(): TypoScriptConfiguration
    {
        return $this->configuration;
    }

    /**
     * Gets the site's default language as configured in
     * config.sys_language_uid. If sys_language_uid is not set, 0 is assumed to
     * be the default.
     *
     * @return int The site's default language.
     */
    public function getDefaultLanguageId(): int
    {
        return $this->defaultLanguageId;
    }

    /**
     * @inheritDoc
     * @throws DBALDriverException
     */
    public function getPages(
        ?int $pageId = null,
        ?string $indexQueueConfigurationName = null
    ): array
    {
        $pageId = $pageId ?? (int)$this->rootPage['uid'];

        $initialPagesAdditionalWhereClause = '';
        // Fetch configuration in order to be able to read initialPagesAdditionalWhereClause
        if ($indexQueueConfigurationName !== null) {
            $solrConfiguration = $this->getSolrConfiguration();
            $initialPagesAdditionalWhereClause = $solrConfiguration->getInitialPagesAdditionalWhereClause($indexQueueConfigurationName);
        }
        return $this->pagesRepository->findAllSubPageIdsByRootPage($pageId, $initialPagesAdditionalWhereClause);
    }

    /**
     * Generates the site's unique Site Hash.
     *
     * The Site Hash is build from the site's main domain, the system encryption
     * key, and the extension "tx_solr". These components are concatenated and
     * sha1-hashed.
     *
     * @return string Site Hash.
     */
    public function getSiteHash(): string
    {
        return $this->siteHash;
    }

    /**
     * @inheritDoc
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Gets the site's root page record.
     *
     * @return array The site's root page.
     */
    public function getRootPage(): array
    {
        return $this->rootPage;
    }

    /**
     * Gets the site's root page's title.
     *
     * @return string The site's root page's title
     */
    public function getTitle(): string
    {
        return $this->rootPage['title'];
    }

    /**
     * Retrieves the rootPageIds as an array from a set of sites.
     *
     * @param array $sites
     * @return array
     * @todo: move to SiteUtility?
     */
    public static function getRootPageIdsFromSites(array $sites): array
    {
        $rootPageIds = [];
        foreach ($sites as $site) {
            $rootPageIds[] = (int)$site->getRootPageId();
        }

        return $rootPageIds;
    }

    /**
     * @return array
     */
    public function getAllSolrConnectionConfigurations(): array
    {
        $configs = [];
        foreach ($this->getAvailableLanguageIds() as $languageId) {
            try {
                $configs[$languageId] = $this->getSolrConnectionConfiguration($languageId);
            } catch (NoSolrConnectionFoundException $e) {}
        }
        return $configs;
    }

    public function isEnabled(): bool
    {
        return !empty($this->getAllSolrConnectionConfigurations());
    }
}
