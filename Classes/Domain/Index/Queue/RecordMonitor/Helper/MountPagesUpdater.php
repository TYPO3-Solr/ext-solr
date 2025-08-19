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
use Doctrine\DBAL\Exception as DBALException;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Extracted logic from the RecordMonitor to trigger mount page updates.
 */
class MountPagesUpdater
{
    protected PagesRepository $pagesRepository;

    public function __construct(?PagesRepository $pagesRepository = null)
    {
        $this->pagesRepository = $pagesRepository ?? GeneralUtility::makeInstance(PagesRepository::class);
    }

    /**
     * Handles updates of the Index Queue in case a newly created or changed
     * page is part of a tree that is mounted into another site.
     *
     * @throws DBALException
     */
    public function update(int $pageId): void
    {
        // get the root line of the page, every parent page could be a Mount Page source
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId);
        try {
            $rootLineArray = $rootlineUtility->get();
        } catch (RuntimeException) {
            $rootLineArray = [];
        }

        if (empty($rootLineArray)) {
            return;
        }

        $currentPage = array_shift($rootLineArray);
        $currentPageUid = (int)$currentPage['uid'];

        if (empty($rootLineArray) && $currentPageUid === 0) {
            return;
        }

        /** @var Rootline $rootLine */
        $rootLine = GeneralUtility::makeInstance(Rootline::class, $rootLineArray);
        $rootLineParentPageIds = array_map('intval', $rootLine->getParentPageIds());
        $destinationMountProperties = $this->pagesRepository->findMountPointPropertiesByPageIdOrByRootLineParentPageIds($currentPageUid, $rootLineParentPageIds);

        if (empty($destinationMountProperties)) {
            return;
        }

        foreach ($destinationMountProperties as $destinationMount) {
            $this->addPageToMountingSiteIndexQueue($pageId, $destinationMount);
        }
    }

    public function updateMountPoint(int $mountPointId): void
    {
        $mountingSite = $this->getSiteRepository()->getSiteByPageId($mountPointId);
        $pageInitializer = $this->getPageInitializer();
        $pageInitializer->setSite($mountingSite);
        $pageInitializer->setIndexingConfigurationName('pages');
        $pageInitializer->initializeMountPoint($mountPointId);
    }

    /**
     * Adds a page to the Index Queue of a site mounting the page.
     *
     * @param int $mountedPageId ID (uid) of the mounted page.
     * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
     *
     * @throws DBALException
     */
    protected function addPageToMountingSiteIndexQueue(int $mountedPageId, array $mountProperties): void
    {
        $siteRepository = $this->getSiteRepository();
        $mountingSite = $siteRepository->getSiteByPageId($mountProperties['mountPageDestination']);

        $pageInitializer = $this->getPageInitializer();
        $pageInitializer->setSite($mountingSite);
        $pageInitializer->setIndexingConfigurationName('pages');

        $pageInitializer->initializeMountedPage($mountProperties, $mountedPageId);
    }

    protected function getPageInitializer(): Page
    {
        return GeneralUtility::makeInstance(Page::class);
    }

    protected function getSiteRepository(): SiteRepository
    {
        return GeneralUtility::makeInstance(SiteRepository::class);
    }
}
