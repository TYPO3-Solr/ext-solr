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

use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Represents all information for EXT:solr of a given TYPO3 Site to retrieve
 * configuration and setup from a page.
 */
class Site
{
    protected PagesRepository $pagesRepository;

    protected ?Typo3Site $typo3SiteObject = null;

    /**
     * @var int[]
     */
    protected array $freeContentModeLanguages = [];

    /**
     * @param TypoScriptConfiguration $configuration
     * @param array{
     *    'uid': int,
     *    'pid': int,
     *    'title': string
     * } $rootPageRecord
     * @param string $domain
     * @param string $siteHash
     * @param PagesRepository|null $pagesRepository
     * @param int $defaultLanguageId
     * @param int[] $availableLanguageIds Available language ids
     * @param array<int, array{
     *    'connectionKey': string,
     *    'language': string,
     *    'rootPageUid': int,
     *    'read': array{
     *        'scheme': string,
     *        'host': string,
     *        'port': int,
     *        'path': string,
     *        'context': string,
     *        'collection': string,
     *        'core': string,
     *        'leader': bool,
     *        'username': string,
     *        'password': string
     *    },
     *    'write': array{
     *        'scheme': string,
     *        'host': string,
     *        'port': int,
     *        'path': string,
     *        'context': string,
     *        'collection': string,
     *        'core': string,
     *        'leader': bool,
     *        'username': string,
     *        'password': string
     *    }
     * }> $solrConnectionConfigurations
     * @param Typo3Site|null $typo3SiteObject
     */
    public function __construct(
        protected TypoScriptConfiguration $configuration,
        protected array $rootPageRecord,
        protected string $domain,
        protected string $siteHash,
        ?PagesRepository $pagesRepository = null,
        protected int $defaultLanguageId = 0,
        protected array $availableLanguageIds = [],
        protected array $solrConnectionConfigurations = [],
        ?Typo3Site $typo3SiteObject = null,
    ) {
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->typo3SiteObject = $typo3SiteObject;
    }

    /**
     * Returns Solr connection configurations indexed by language id.
     *
     * @return array{
     *    'connectionKey': string,
     *    'language': string,
     *    'rootPageUid': int,
     *    'read': array{
     *        'scheme': string,
     *        'host': string,
     *        'port': int,
     *        'path': string,
     *        'context': string,
     *        'collection': string,
     *        'core': string,
     *        'leader': bool,
     *        'username': string,
     *        'password': string
     *    },
     *    'write': array{
     *        'scheme': string,
     *        'host': string,
     *        'port': int,
     *        'path': string,
     *        'context': string,
     *        'collection': string,
     *        'core': string,
     *        'leader': bool,
     *        'username': string,
     *        'password': string
     *    }
     * }
     *
     * @throws NoSolrConnectionFoundException
     */
    public function getSolrConnectionConfiguration(int $language = 0): array
    {
        if (!is_array($this->solrConnectionConfigurations[$language] ?? null)) {
            /** @var NoSolrConnectionFoundException $noSolrConnectionException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                NoSolrConnectionFoundException::class,
                'Could not find a Solr connection for root page [' . $this->getRootPageId() . '] and language [' . $language . '].',
                1552491117,
            );
            $noSolrConnectionException->setRootPageId($this->getRootPageId());
            $noSolrConnectionException->setLanguageId($language);

            throw $noSolrConnectionException;
        }

        return $this->solrConnectionConfigurations[$language];
    }

    public function getTypo3SiteObject(): Typo3Site
    {
        return $this->typo3SiteObject;
    }

    /**
     * Checks if current TYPO3 site has free content mode languages
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
     *
     * @return array<int, int>
     */
    public function getFreeContentModeLanguages(): array
    {
        if (!empty($this->freeContentModeLanguages)) {
            return $this->freeContentModeLanguages;
        }

        if (!$this->typo3SiteObject instanceof Typo3Site) {
            return [];
        }

        foreach ($this->availableLanguageIds as $languageId) {
            if ($languageId > 0 && $this->typo3SiteObject->getLanguageById($languageId)->getFallbackType() === 'free') {
                $this->freeContentModeLanguages[$languageId] = $languageId;
            }
        }
        return $this->freeContentModeLanguages;
    }

    /**
     * Gets the site's root page ID (uid).
     */
    public function getRootPageId(): int
    {
        return (int)$this->rootPageRecord['uid'];
    }

    /**
     * Gets available language id's for this site
     *
     * @return int[]
     */
    public function getAvailableLanguageIds(): array
    {
        return $this->availableLanguageIds;
    }

    /**
     * Gets the site's label. The label is build from the site title and root page ID (uid).
     */
    public function getLabel(): string
    {
        $rootlineTitles = [];
        $rootLine = BackendUtility::BEgetRootLine($this->rootPageRecord['uid']);
        // Remove last
        array_pop($rootLine);
        $rootLine = array_reverse($rootLine);
        foreach ($rootLine as $rootLineItem) {
            $rootlineTitles[] = $rootLineItem['title'];
        }
        return implode(' - ', $rootlineTitles) . ', Root Page ID: ' . $this->rootPageRecord['uid'];
    }

    /**
     * Gets the site's Solr TypoScript configuration (plugin.tx_solr.*)
     */
    public function getSolrConfiguration(): TypoScriptConfiguration
    {
        return $this->configuration;
    }

    /**
     * Gets the site's default language as configured in
     * config.sys_language_uid. If sys_language_uid is not set, 0 is assumed to
     * be the default.
     */
    public function getDefaultLanguageId(): int
    {
        return $this->defaultLanguageId;
    }

    /**
     * Generates a list of page IDs in this site and returns it.
     *
     * Attentions:
     * * This includes all page types!
     * * Deleted pages are not included.
     * * Uses the root page, if $pageId is not given
     * * Includes the given $pageId
     *
     * @param int|null $pageId Page ID from where to start collection sub-pages. Uses and includes the root page if none given.
     * @param string|null $indexQueueConfigurationName The name of index queue.
     * @param string|null $additionalWhereClause
     *
     * @return int[] Array of pages (IDs) in this site
     *
     * @throws DBALException
     */
    public function getPages(
        ?int $pageId = null,
        ?string $indexQueueConfigurationName = null,
        ?string $additionalWhereClause = null,
    ): array {
        $pageId = $pageId ?? (int)$this->rootPageRecord['uid'];

        $initialPagesAdditionalWhereClause = '';
        // Fetch configuration in order to be able to read initialPagesAdditionalWhereClause
        if ($indexQueueConfigurationName !== null) {
            $solrConfiguration = $this->getSolrConfiguration();
            $initialPagesAdditionalWhereClause = $solrConfiguration->getInitialPagesAdditionalWhereClause($indexQueueConfigurationName);
        }

        if ($additionalWhereClause !== null) {
            $initialPagesAdditionalWhereClause .=
                ($initialPagesAdditionalWhereClause !== '' ? ' AND ' : '')
                . '(' . $additionalWhereClause . ')';
        }

        return $this->pagesRepository->findAllSubPageIdsByRootPage($pageId, $initialPagesAdditionalWhereClause);
    }

    /**
     * Generates the site's unique Site Hash.
     *
     * The Site Hash is build from the site's main domain, the system encryption
     * key, and the extension "tx_solr". These components are concatenated and
     * sha1-hashed.
     */
    public function getSiteHash(): string
    {
        return $this->siteHash;
    }

    /**
     * Gets the site's main domain.
     *
     * @return string The site's main domain.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Gets the site's root page record.
     *
     * @return array{
     *    'uid': int,
     *    'pid': int,
     *    'title': string
     *  } The site's root page.
     */
    public function getRootPageRecord(): array
    {
        return $this->rootPageRecord;
    }

    /**
     * Gets the site's root page's title.
     *
     * @return string The site's root page's title
     */
    public function getTitle(): string
    {
        return $this->rootPageRecord['title'] ?? '';
    }

    /**
     * Returns all Solr connection configurations.
     * @return array<int, array{
     *    'connectionKey': string,
     *    'language': string,
     *    'rootPageUid': int,
     *    'read': array{
     *        'scheme': string,
     *        'host': string,
     *        'port': int,
     *        'path': string,
     *        'context': string,
     *        'collection': string,
     *        'core': string,
     *        'leader': bool,
     *        'username': string,
     *        'password': string
     *    },
     *    'write': array{
     *        'scheme': string,
     *        'host': string,
     *        'port': int,
     *        'path': string,
     *        'context': string,
     *        'collection': string,
     *        'core': string,
     *        'leader': bool,
     *        'username': string,
     *        'password': string
     *    }
     * }>
     */
    public function getAllSolrConnectionConfigurations(): array
    {
        $configs = [];
        foreach ($this->getAvailableLanguageIds() as $languageId) {
            try {
                $configs[$languageId] = $this->getSolrConnectionConfiguration($languageId);
            } catch (NoSolrConnectionFoundException) {
            }
        }
        return $configs;
    }

    /**
     * Returns the EXT:solr state configured in site.
     */
    public function isEnabled(): bool
    {
        return !empty($this->getAllSolrConnectionConfigurations());
    }
}
