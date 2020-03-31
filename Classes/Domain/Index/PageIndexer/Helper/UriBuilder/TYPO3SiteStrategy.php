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
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is used to build the indexing url for a TYPO3 site that is managed with the TYPO3 site management.
 *
 * These sites have the pageId and language information encoded in the speaking url.
 */
class TYPO3SiteStrategy extends AbstractUriStrategy
{
    /**
     * @var SiteFinder
     */
    protected $siteFinder = null;

    /**
     * TYPO3SiteStrategy constructor.
     * @param SolrLogManager|null $logger
     * @param SiteFinder|null $siteFinder
     */
    public function __construct(SolrLogManager $logger = null, SiteFinder $siteFinder = null)
    {
        parent::__construct($logger);
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * @param Item $item
     * @param int $language
     * @param string $mountPointParameter
     * @return string
     * @throws SiteNotFoundException
     * @throws InvalidRouteArgumentsException
     */
    protected function buildPageIndexingUriFromPageItemAndLanguageId(Item $item, int $language = 0,  string $mountPointParameter = '')
    {
        $site = $this->siteFinder->getSiteByPageId((int)$item->getRecordUid());
        $parameters = [];

        if ($language > 0) {
            $parameters['_language'] = $language;
        };

        if ($mountPointParameter !== '') {
            $parameters['MP'] = $mountPointParameter;
        }

        $pageIndexUri = (string)$site->getRouter()->generateUri($item->getRecord(), $parameters);
        return $pageIndexUri;
    }
}
