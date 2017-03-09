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
 *  the Free Software Foundation; either version 2 of the License, or
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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Extracted logic from the RecordMonitor to trigger mount page updates.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class MountPagesUpdater
{

    /**
     * Handles updates of the Index Queue in case a newly created or changed
     * page is part of a tree that is mounted into a another site.
     *
     * @param int $pageId Page Id (uid).
     */
    public function update($pageId)
    {
        // get the root line of the page, every parent page could be a Mount Page source
        /** @var $pageSelect PageRepository */
        $pageSelect = GeneralUtility::makeInstance(PageRepository::class);
        $rootLine = $pageSelect->getRootLine($pageId);

        $destinationMountProperties = $this->getDestinationMountPropertiesByRootLine($rootLine);

        if (empty($destinationMountProperties)) {
            return;
        }

        foreach ($destinationMountProperties as $destinationMount) {
            $this->addPageToMountingSiteIndexQueue($pageId, $destinationMount);
        }
    }

    /**
     * Finds Mount Pages that mount pages in a given root line.
     *
     * @param array $rootLineArray Root line of pages to check for usage as mount source
     * @return array Array of pages found to be mounting pages from the root line.
     */
    protected function getDestinationMountPropertiesByRootLine(array $rootLineArray)
    {
        $mountPages = [];

        $currentPage = array_shift($rootLineArray);
        $currentPageUid = (int)$currentPage['uid'];

        if (empty($rootLineArray) && $currentPageUid === 0) {
            return $mountPages;
        }

        /** @var $rootLine Rootline */
        $rootLine = GeneralUtility::makeInstance(Rootline::class, $rootLineArray);
        $rootLineParentPageIds = $rootLine->getParentPageIds();

        $pageQueryConditions = [];
        if (!empty($rootLineParentPageIds)) {
            $pageQueryConditions[] = '(mount_pid IN(' . implode(',', $rootLineParentPageIds) . '))';
        }

        if ($currentPageUid !== 0) {
            $pageQueryConditions[] = '(mount_pid=' . $currentPageUid . ' AND mount_pid_ol=1)';
        }
        $pageQueryCondition = implode(' OR ', $pageQueryConditions);

        $mountPages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid, uid AS mountPageDestination, mount_pid AS mountPageSource, mount_pid_ol AS mountPageOverlayed',
            'pages',
            'doktype = 7 AND no_search = 0 '
            . BackendUtility::deleteClause('pages') . ' AND (' . $pageQueryCondition . ') '
        );

        return $mountPages;
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
