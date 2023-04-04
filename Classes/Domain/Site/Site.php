<?php

namespace ApacheSolrForTypo3\Solr\Domain\Site;

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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\ConfigurationAwareRecordService;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Base Class for Typo3ManagedSite and LegacySite
 *
 * (c) 2011-2015 Ingo Renner <ingo@typo3.org>
 */
abstract class Site implements SiteInterface
{
    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * Root page record.
     *
     * @var array
     */
    protected $rootPage = [];
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $siteHash;

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * @var int
     */
    protected $defaultLanguageId = 0;

    /**
     * @var int[] Available language ids
     */
    protected $availableLanguageIds = [];

    /**
     * Takes an pagerecord and checks whether the page is marked as root page.
     *
     * @param array $page pagerecord
     * @return bool true if the page is marked as root page, false otherwise
     * @todo: move to SiteUtility?
     */
    public static function isRootPage($page)
    {
        if ($page['is_siteroot']) {
            return true;
        }

        return false;
    }

    /**
     * Gets the site's root page ID (uid).
     *
     * @return int The site's root page ID.
     */
    public function getRootPageId()
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
    public function getLabel()
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
    public function getDefaultLanguage()
    {
        return $this->defaultLanguageId;
    }

    /**
     * @inheritDoc
     */
    public function getPages(
        ?int $pageId = null,
        ?string $indexQueueConfigurationName = null
    ): array {
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
     * @param int|null $rootPageId
     * @return array
     */
    public function getPagesWithinNoSearchSubEntriesPages(int $rootPageId = null): array
    {
        if ($rootPageId === null) {
            $rootPageId = (int)$this->rootPage['uid'];
        }

        /* @var ConfigurationAwareRecordService $configurationAwareRecordService */
        $configurationAwareRecordService = GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        // Fetch configuration in order to be able to read initialPagesAdditionalWhereClause
        $solrConfiguration = $this->getSolrConfiguration();
        $indexQueueConfigurationName = $configurationAwareRecordService->getIndexingConfigurationName('pages', $this->rootPage['uid'], $solrConfiguration);
        $initialPagesAdditionalWhereClause = $solrConfiguration->getInitialPagesAdditionalWhereClause($indexQueueConfigurationName);

        return $this->pagesRepository->findAllPagesWithinNoSearchSubEntriesMarkedPagesByRootPage(
            $rootPageId,
            999,
            $initialPagesAdditionalWhereClause
        );
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
    public function getSiteHash()
    {
        return $this->siteHash;
    }

    /**
     * Gets the site's main domain. More specifically the first domain record in
     * the site tree.
     *
     * @return string The site's main domain.
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Gets the site's root page.
     *
     * @return array The site's root page.
     */
    public function getRootPage()
    {
        return $this->rootPage;
    }

    /**
     * Gets the site's root page's title.
     *
     * @return string The site's root page's title
     */
    public function getTitle()
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
            } catch (NoSolrConnectionFoundException $e) {
            }
        }
        return $configs;
    }

    public function isEnabled(): bool
    {
        return !empty($this->getAllSolrConnectionConfigurations());
    }

    /**
     * @param int $language
     * @return array
     */
    abstract public function getSolrConnectionConfiguration(int $language = 0): array;
}
