<?php
namespace ApacheSolrForTypo3\Solr\System\Util;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains related functions for the new site management that was introduced with TYPO3 9.
 *
 * @package ApacheSolrForTypo3\Solr\System\Util
 */
class SiteUtility
{

    /**
     * Determines if the site where the page belongs to is managed with the TYPO3 site management.
     *
     * @return boolean
     */
    public static function getIsSiteManagedSite($pageId)
    {
        if (Util::getIsTYPO3VersionBelow9()) {
            return false;
        }

        $siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByPageId($pageId);
        } catch (\TYPO3\CMS\Core\Exception\SiteNotFoundException $e) {
            return false;
        }

        return $site instanceof \TYPO3\CMS\Core\Site\Entity\Site;
    }
}