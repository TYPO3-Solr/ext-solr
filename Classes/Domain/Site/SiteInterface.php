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

interface SiteInterface
{
    /**
     * Gets the site's root page ID (uid).
     *
     * @return int The site's root page ID.
     */
    public function getRootPageId(): int;

    /**
     * Gets available language id's for this site
     *
     * @return int[] array or language id's
     */
    public function getAvailableLanguageIds(): array;

    /**
     * Gets the site's label. The label is build from the site title and root
     * page ID (uid).
     *
     * @return string The site's label.
     */
    public function getLabel(): string;

    /**
     * Gets the site's Solr TypoScript configuration (plugin.tx_solr.*)
     *
     * @return TypoScriptConfiguration The Solr TypoScript configuration
     */
    public function getSolrConfiguration(): TypoScriptConfiguration;

    /**
     * Gets the site's default language as configured in
     * config.sys_language_uid. If sys_language_uid is not set, 0 is assumed to
     * be the default.
     *
     * @return int The site's default language.
     */
    public function getDefaultLanguageId(): int;

    /**
     * Generates a list of page IDs in this site.
     *
     * Attentions:
     * * This includes all page types!
     * * Deleted pages are not included.
     * * Uses the root page, if $pageId is not given
     * * Includes the given $pageId
     *
     * @param int|null $pageId Page ID from where to start collection sub pages. Uses and includes the root page if none given.
     * @param string|null $indexQueueConfigurationName The name of index queue.
     * @return array Array of pages (IDs) in this site
     */
    public function getPages(
        ?int $pageId = null,
        ?string $indexQueueConfigurationName = null
    ): array;

    /**
     * Generates the site's unique Site Hash.
     *
     * The Site Hash is build from the site's main domain, the system encryption
     * key, and the extension "tx_solr". These components are concatenated and
     * sha1-hashed.
     *
     * @return string Site Hash.
     */
    public function getSiteHash(): string;

    /**
     * Gets the site's main domain.
     *
     * @return string The site's main domain.
     */
    public function getDomain(): string;

    /**
     * Gets the site's root page record.
     *
     * @return array The site's root page.
     */
    public function getRootPage(): array;

    /**
     * Gets the site's root page's title.
     *
     * @return string The site's root page's title
     */
    public function getTitle(): string;

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
