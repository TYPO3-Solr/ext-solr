<?php

namespace ApacheSolrForTypo3\Solr\Domain\Site;

/*
 * This source file is proprietary property of Beech Applications B.V.
 * Date: 13-3-19
 * All code (c) Beech Applications B.V. all rights reserved
 */

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;

class Typo3ManagedSite extends Site
{

    /**
     * @var Typo3Site
     */
    protected $typo3SiteObject;

    
    public function __construct(TypoScriptConfiguration $configuration, array $page, $domain, $siteHash, PagesRepository $pagesRepository = null, $defaultLanguageId = 0, $availableLanguageIds = [], Typo3Site $typo3SiteObject = null)
    {
        $this->configuration = $configuration;
        $this->rootPage = $page;
        $this->domain = $domain;
        $this->siteHash = $siteHash;
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
        $this->defaultLanguageId = $defaultLanguageId;
        $this->availableLanguageIds = $availableLanguageIds;
        $this->typo3SiteObject = $typo3SiteObject;
    }

    public function getSysLanguageMode($languageUid = 0)
    {
        // TODO: Implement getSysLanguageMode() method.
    }


    public function getSolrConnectionConfiguration(int $language = 0): array
    {
        return ['read' => [], 'write' => []];
//        $this->typo3SiteObject->getConfiguration()
        // TODO: Implement getSolrConnectionConfiguration() method.
    }
}