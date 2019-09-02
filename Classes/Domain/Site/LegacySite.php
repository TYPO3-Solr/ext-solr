<?php

namespace ApacheSolrForTypo3\Solr\Domain\Site;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Frans Saris <frans.saris@beech.it> & Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LegacySite
 *
 * @deprecated This class is used for the old solr setup based on TypoScript and sys_registry records.
 * @package ApacheSolrForTypo3\Solr\Domain\Site
 */
class LegacySite extends Site
{
    /**
     * The site's sys_language_mode
     *
     * @var string
     */
    protected $sysLanguageMode = null;

    /**
     * Constructor.
     *
     * @param TypoScriptConfiguration $configuration
     * @param array $page Site root page ID (uid). The page must be marked as site root ("Use as Root Page" flag).
     * @param string $domain The domain record used by this Site
     * @param string $siteHash The site hash used by this site
     * @param PagesRepository $pagesRepository
     * @param int $defaultLanguageId
     * @param int[] $availableLanguageIds
     */
    public function __construct(TypoScriptConfiguration $configuration, array $page, $domain, $siteHash, PagesRepository $pagesRepository = null, $defaultLanguageId = 0, $availableLanguageIds = [])
    {
        $this->configuration = $configuration;
        $this->rootPage = $page;
        $this->domain = $domain;
        $this->siteHash = $siteHash;
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->defaultLanguageId = $defaultLanguageId;
        $this->availableLanguageIds = $availableLanguageIds;
    }

    /**
     * Gets the site's config.sys_language_mode setting
     *
     * @param int $languageUid
     *
     * @return string The site's config.sys_language_mode
     */
    public function getSysLanguageMode($languageUid = 0)
    {
        if ($this->sysLanguageMode !== null) {
            return $this->sysLanguageMode;
        }

        try {
            Util::initializeTsfe($this->getRootPageId(), $languageUid);
            $this->sysLanguageMode = $GLOBALS['TSFE']->sys_language_mode;
            return $this->sysLanguageMode;

        } catch (\TYPO3\CMS\Core\Error\Http\ServiceUnavailableException $e) {
            // when there is an error during initialization we return the default sysLanguageMode
            return $this->sysLanguageMode;
        }
    }

    /**
     * @param int $language
     * @return array
     * @throws NoSolrConnectionFoundException
     */
    public function getSolrConnectionConfiguration(int $language = 0): array {
        $connectionKey = $this->getRootPageId() . '|' . $language;
        $solrConfiguration = $this->getSolrConnectionConfigFromRegistry($connectionKey);

        if (!is_array($solrConfiguration)) {
            /* @var $noSolrConnectionException NoSolrConnectionFoundException */
            $noSolrConnectionException = GeneralUtility::makeInstance(
                NoSolrConnectionFoundException::class,
                /** @scrutinizer ignore-type */  'Could not find a Solr connection for root page [' . $this->getRootPageId() . '] and language [' . $language . '].',
                /** @scrutinizer ignore-type */ 1275396474
            );
            $noSolrConnectionException->setRootPageId($this->getRootPageId());
            $noSolrConnectionException->setLanguageId($language);

            throw $noSolrConnectionException;
        }

        return $solrConfiguration;
    }

    /**
     * Gets all connection configurations found.
     *
     * @return array An array of connection configurations.
     */
    protected function getSolrConnectionConfigFromRegistry(string $connectionKey)
    {
        /** @var $registry Registry */
        $registry = GeneralUtility::makeInstance(Registry::class);
        $solrConfigurations = $registry->get('tx_solr', 'servers', []);

        return $solrConfigurations[$connectionKey] ?? null;
    }

}