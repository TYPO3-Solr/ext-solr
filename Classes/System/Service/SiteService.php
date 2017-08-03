<?php
declare(strict_types = 1);

namespace ApacheSolrForTypo3\Solr\System\Service;

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

use TYPO3\CMS\Core\SingletonInterface;

/**
 * Retrieves site related information.
 *
 * @todo When we have something like this available in TYPO39 and drop TYPO8 compatibility we can drop this class.
 */
class SiteService implements SingletonInterface
{

    /** @var array */
    protected $sites = [];

    /**
     * Initialize domain configuration
     * @param array $sites
     */
    public function __construct(array $sites = [])
    {
        $this->sites = (count($sites) === 0) ? (array)$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['sites'] : $sites;
    }

    /**
     * @param int $rootPageId
     * @return string
     */
    public function getFirstDomainForRootPage(int $rootPageId): string
    {
        $siteByPageId = $this->getSiteForRootPageId($rootPageId);
        return $this->getFirstDomainFromSiteArray($siteByPageId);
    }

    /**
     * Returns the configured domain from a site array or an empty string.
     *
     * @param array $site
     * @return string
     */
    protected function getFirstDomainFromSiteArray(array $site): string
    {
        return (empty($site['domains'][0])) ? '' : $site['domains'][0];
    }

    /**
     * Retrieves the configured site for a rootPageId.
     *
     * @param int $pageId
     * @return array
     */
    protected function getSiteForRootPageId(int $pageId): array
    {
        return is_array($this->sites[$pageId]) ? $this->sites[$pageId] : [];
    }
}
