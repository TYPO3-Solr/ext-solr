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

interface SiteInterface
{
    /**
     * Gets the site's root page ID (uid).
     *
     * @return int The site's root page ID.
     */
    public function getRootPageId();

    /**
     * Gets available language id's for this site
     *
     * @return int[] array or language id's
     */
    public function getAvailableLanguageIds(): array;

    /**
     * Gets the site's label. The label is build from the the site title and root
     * page ID (uid).
     *
     * @return string The site's label.
     */
    public function getLabel();

    /**
     * Gets the site's Solr TypoScript configuration (plugin.tx_solr.*)
     *
     * @return  \ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration The Solr TypoScript configuration
     */
    public function getSolrConfiguration();

    /**
     * Gets the site's default language as configured in
     * config.sys_language_uid. If sys_language_uid is not set, 0 is assumed to
     * be the default.
     *
     * @return int The site's default language.
     */
    public function getDefaultLanguage();

    /**
     * Generates a list of page IDs in this site. Attention, this includes
     * all page types! Deleted pages are not included.
     *
     * @param int|string $rootPageId Page ID from where to start collection sub pages
     * @param int $maxDepth Maximum depth to descend into the site tree
     * @return array Array of pages (IDs) in this site
     */
    public function getPages($rootPageId = 'SITE_ROOT', $maxDepth = 999);

    /**
     * Generates the site's unique Site Hash.
     *
     * The Site Hash is build from the site's main domain, the system encryption
     * key, and the extension "tx_solr". These components are concatenated and
     * sha1-hashed.
     *
     * @return string Site Hash.
     */
    public function getSiteHash();

    /**
     * Gets the site's main domain. More specifically the first domain record in
     * the site tree.
     *
     * @return string The site's main domain.
     */
    public function getDomain();

    /**
     * Gets the site's root page.
     *
     * @return array The site's root page.
     */
    public function getRootPage();

    /**
     * Gets the site's root page's title.
     *
     * @return string The site's root page's title
     */
    public function getTitle();


    /**
     * @param int $language
     * @return array
     * @throws NoSolrConnectionFoundException
     */
    public function getSolrConnectionConfiguration(int $language = 0): array;

    /**
     * @return array
     * @throws NoSolrConnectionFoundException
     */
    public function getAllSolrConnectionConfigurations(): array;

    public function isEnabled(): bool;
}
