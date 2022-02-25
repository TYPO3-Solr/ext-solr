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

namespace ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper;

use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\AbstractUriStrategy;
use ApacheSolrForTypo3\Solr\Domain\Index\PageIndexer\Helper\UriBuilder\TYPO3SiteStrategy;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is responsible to retrieve an "UriStrategy" the can build uri's for the site where the
 * passed page belongs to.
 *
 * This can be:
 * * A TYPO3 site managed with site management
 * * A TYPO3 site without site management where the url is build by EXT:solr with L and id param and information from the domain
 * record or solr specific configuration.
 */
class UriStrategyFactory
{
    /**
     * @param int $pageId
     * @oaram array $overrideConfiguration
     * @return AbstractUriStrategy
     * @throws Exception
     */
    public function getForPageId(int $pageId): AbstractUriStrategy
    {
        if (!SiteUtility::getIsSiteManagedSite($pageId)) {
            throw new Exception('Site of page with uid ' . $pageId . ' is not a TYPO3 managed site');
        }

        return GeneralUtility::makeInstance(TYPO3SiteStrategy::class);
    }
}
