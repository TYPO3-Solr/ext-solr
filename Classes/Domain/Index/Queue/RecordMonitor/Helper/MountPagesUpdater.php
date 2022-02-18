<?php

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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Page;
use ApacheSolrForTypo3\Solr\System\Page\Rootline;
use ApacheSolrForTypo3\Solr\System\Records\Pages\PagesRepository;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

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
        } catch (RuntimeException $e) {
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
        $pageInitializer->setIndexingConfigurationName('pages');

        $pageInitializer->initializeMountedPage($mountProperties, $mountedPageId);
    }
}
