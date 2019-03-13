<?php

namespace ApacheSolrForTypo3\Solr\Domain\Site;

/*
 * This source file is proprietary property of Beech Applications B.V.
 * Date: 13-3-19
 * All code (c) Beech Applications B.V. all rights reserved
 */

use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
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

    /**
     * @var array
     */
    protected $solrConnectionConfigurations;


    public function __construct(
        TypoScriptConfiguration $configuration,
        array $page, $domain, $siteHash, PagesRepository $pagesRepository = null, $defaultLanguageId = 0, $availableLanguageIds = [], array $solrConnectionConfigurations = [], Typo3Site $typo3SiteObject = null)
    {
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

    public function getSysLanguageMode($languageUid = 0)
    {
        // TODO: Implement getSysLanguageMode() method.
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
}