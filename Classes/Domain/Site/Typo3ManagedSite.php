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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Site\Entity\Site as Typo3Site;


/**
 * Class Typo3ManagedSite
 */
class Typo3ManagedSite extends Site
{

    /**
     * @var Typo3Site|null
     */
    protected ?Typo3Site $typo3SiteObject;

    /**
     * @var array
     */
    protected array $solrConnectionConfigurations;

    /**
     * @var array
     */
    protected array $freeContentModeLanguages = [];


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
    public function hasFreeModeLanguages(): bool
    {
        return !empty($this->getFreeModeLanguages());
    }

    /**
     * Return all free content mode languages.
     *
     * Note: There is no "fallback type" nor "fallbacks" for default language 0
     *       See "displayCond" on https://github.com/TYPO3/typo3/blob/1394a4cff5369df3f835dae254b3d4ada2f83c7b/typo3/sysext/backend/Configuration/SiteConfiguration/site_language.php#L403-L416
     *           or https://review.typo3.org/c/Packages/TYPO3.CMS/+/56505/ for more information.
     *
     * @return array|null
     */
    public function getFreeModeLanguages(): array
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
}
