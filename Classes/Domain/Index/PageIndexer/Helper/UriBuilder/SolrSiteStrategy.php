<?php declare(strict_types = 1);

namespace ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder;

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

use ApacheSolrForTypo3\Solr\IndexQueue\Item;

/**
 * This class is used to build the indexing url based on the EXT:solr site.
 *
 * The EXT:solr site is the TYPO3 site where the domain record is located or an EXT:solr specific domain configuration
 * is located. EXT:solr uses this term "site" several years for the entry point.
 *
 * In TYPO3 9 "site's" have been introduced in TYPO3 and are managed with the "site management" for those sites
 * we build the indexing url in a different way since they have the pageId and languageId encoded in the speaking url by the core.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder
 */
class SolrSiteStrategy extends AbstractUriStrategy
{
    /**
     * @param Item $item
     * @param int $language
     * @param string $mountPointParameter
     * @return string
     */
    protected function buildPageIndexingUriFromPageItemAndLanguageId(Item $item, int $language = 0, string $mountPointParameter  = '')
    {
        $scheme = 'http';
        $host = $item->getSite()->getDomain();
        $path = '/';
        $pageId = $item->getRecordUid();

        $pageIndexUri = $scheme . '://' . $host . $path . 'index.php?id=' . $pageId;
        $pageIndexUri .= ($mountPointParameter !== '') ? '&MP=' . $mountPointParameter : '';
        $pageIndexUri .= '&L=' . $language;

        return $pageIndexUri;
    }
}