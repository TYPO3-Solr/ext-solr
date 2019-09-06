<?php

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use ApacheSolrForTypo3\Solr\System\Page\Rootline;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Extracted logic from the RecordMonitor to trigger mount page updates.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class MountPagesUpdater
{

    /**
     * @var PagesRepository
     */
    protected $pagesRepository;

    /**
     * MountPagesUpdater constructor.
     *
     * @param PagesRepository|null $pagesRepository
     */
    public function __construct(PagesRepository $pagesRepository = null) {
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
    }

    /**
     * Handles updates of the Index Queue in case a newly created or changed
     * page is part of a tree that is mounted into a another site.
     *
     * @param int $pageId Page Id (uid).
     */
    public function update($pageId)
    {
        // get the root line of the page, every parent page could be a Mount Page source
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId);
        try {
            $rootLineArray = $rootlineUtility->get();
        } catch (\RuntimeException $e) {
            $rootLineArray = [];
        }

        $currentPage = array_shift($rootLineArray);
        $currentPageUid = (int)$currentPage['uid'];

        if (empty($rootLineArray) && $currentPageUid === 0) {
            return;
        }

        /** @var $rootLine Rootline */
        $rootLine = GeneralUtility::makeInstance(Rootline::class, /** @scrutinizer ignore-type */ $rootLineArray);
        $rootLineParentPageIds = array_map('intval', $rootLine->getParentPageIds());
        $destinationMountProperties = $this->pagesRepository->findMountPointPropertiesByPageIdOrByRootLineParentPageIds($currentPageUid, $rootLineParentPageIds);

        if (empty($destinationMountProperties)) {
            return;
        }

        foreach ($destinationMountProperties as $destinationMount) {
            $this->addPageToMountingSiteIndexQueue($pageId, $destinationMount);
        }
    }

    /**
     * Adds a page to the Index Queue of a site mounting the page.
     *
     * @param int $mountedPageId ID (uid) of the mounted page.
     * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
     */
    protected function addPageToMountingSiteIndexQueue($mountedPageId, array $mountProperties)
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $mountingSite = $siteRepository->getSiteByPageId($mountProperties['mountPageDestination']);

        /** @var $pageInitializer Page */
        $pageInitializer = GeneralUtility::makeInstance(Page::class);
        $pageInitializer->setSite($mountingSite);

        $pageInitializer->initializeMountedPage($mountProperties, $mountedPageId);
    }
}
