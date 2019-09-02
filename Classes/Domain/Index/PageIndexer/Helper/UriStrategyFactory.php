<?php declare(strict_types = 1);

namespace ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper;

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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\AbstractUriStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\SolrSiteStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\TYPO3SiteStrategy;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is responsible to retrieve an "UriStrategy" the can build uri's for the site where the
 * passed page belongs to.
 *
 * This can be:
 * * A TYPO3 site managed with site management
 * * A TYPO3 site without site management where the url is build by EXT:solr with L and id param and information from the domain
 * record or solr specific configuration.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper
 */
class UriStrategyFactory
{
    /**
     * @param integer $pageId
     * @oaram array $overrideConfiguration
     * @return AbstractUriStrategy
     */
    public function getForPageId(int $pageId): AbstractUriStrategy
    {
        // @todo by now using the site urls leads to problems
        // since e.g. fallbacks do not work and urls could be indexed that lead to a 404 error
        // when this is resolved we could generate the urls with the router of the site for indexing with solr
        if (SiteUtility::getIsSiteManagedSite($pageId)) {
            return GeneralUtility::makeInstance(TYPO3SiteStrategy::class);
        }

        return GeneralUtility::makeInstance(SolrSiteStrategy::class);
    }
}