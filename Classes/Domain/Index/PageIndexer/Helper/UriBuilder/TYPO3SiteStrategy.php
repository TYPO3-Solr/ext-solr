<?php

declare(strict_types = 1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder;

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
