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

namespace ApacheSolrForTypo3\Solr\FieldProcessor;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use RuntimeException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * This Processor takes a PID, and resolves its rootline in solr notation.
 *
 * Format of this field corresponds to http://wiki.apache.org/solr/HierarchicalFaceting
 *
 * Let's say we have a record indexed on page 111 which is a sub-page like shown in this page tree:
 *
 * 1
 * |-10
 *   |-100
 *     |-111
 *
 * then we get a rootline 1/10/100/111
 *
 * In Solr hierarchy notation, we get
 *
 * 0-1/
 * 1-1/10/
 * 2-1/10/100/
 * 3-1/10/100/11/
 *
 * which is finally saved in a multi-value field.
 *
 * @author Michael Knoll <knoll@punkt.de>
 */
class PageUidToHierarchy extends AbstractHierarchyProcessor implements FieldProcessor
{
    /**
     * Expects a page ID of a page. Returns a Solr hierarchy notation for the
     * rootline of the page ID.
     *
     * @param array $values Array of values, an array because of multivalued fields
     * @return array Modified array of values
     */
    public function process(array $values): array
    {
        $results = [];

        foreach ($values as $value) {
            $rootPageUidAndMountPoint = GeneralUtility::trimExplode(',', $value, true, 2);
            $results[] = $this->getSolrRootlineForPageId(
                (int)$rootPageUidAndMountPoint[0],
                $rootPageUidAndMountPoint[1] ?? ''
            );
        }

        return $results;
    }

    /**
     * Returns a Solr hierarchy notation string for rootline of given PID.
     *
     * @param int $pageId Page ID to get a rootline as Solr hierarchy for
     * @param string $mountPoint The mount point parameter that will be used for building the rootline.
     * @return array Rootline as Solr hierarchy array
     */
    protected function getSolrRootlineForPageId(int $pageId, string $mountPoint = ''): array
    {
        $pageIdRootline = $this->buildPageIdRootline($pageId, $mountPoint);
        return $this->buildSolrHierarchyFromIdRootline($pageIdRootline);
    }

    /**
     * Builds a page's rootline of parent page Ids
     *
     * @param int $pageId The page Id to build the rootline for
     * @param string $mountPoint The mount point parameter that will be passed to getRootline().
     * @return array Page Id rootline as array
     */
    protected function buildPageIdRootline(int $pageId, string $mountPoint = ''): array
    {
        $rootlinePageIds = [];

        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId, $mountPoint);
        try {
            $rootline = $rootlineUtility->get();
        } catch (RuntimeException $e) {
            $rootline = [];
        }

        foreach ($rootline as $page) {
            if (Site::isRootPage($page)) {
                break;
            }

            array_unshift($rootlinePageIds, $page['pid']);
        }

        $rootlinePageIds[] = $pageId;

        return $rootlinePageIds;
    }
}
