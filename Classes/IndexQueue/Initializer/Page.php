<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\Initializer;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Utility\DatabaseUtility;
use ApacheSolrForTypo3\Solr\Site;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue initializer for pages which also covers resolution of mount
 * pages.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Page extends AbstractInitializer
{

    /**
     * Constructor, sets type and indexingConfigurationName to "pages".
     *
     */
    public function __construct()
    {
        $this->type = 'pages';
        $this->indexingConfigurationName = 'pages';
    }

    /**
     * Overrides the general setType() implementation, forcing type to "pages".
     *
     * @param string $type Type to initialize (ignored).
     */
    public function setType($type)
    {
        $this->type = 'pages';
    }

    /**
     * Overrides the general setIndexingConfigurationName() implementation,
     * forcing indexingConfigurationName to "pages".
     *
     * @param string $indexingConfigurationName Indexing configuration name (ignored)
     */
    public function setIndexingConfigurationName($indexingConfigurationName)
    {
        $this->indexingConfigurationName = 'pages';
    }

    /**
     * Initializes Index Queue page items for a site. Includes regular pages
     * and mounted pages - no nested mount page structures though.
     *
     * @return boolean TRUE if initialization was successful, FALSE on error.
     */
    public function initialize()
    {
        $pagesInitialized = parent::initialize();
        $mountPagesInitialized = $this->initializeMountPages();

        return ($pagesInitialized && $mountPagesInitialized);
    }

    /**
     * Initialize a single page that is part of a mounted tree.
     *
     * @param array $mountProperties Array of mount point properties mountPageSource, mountPageDestination, and mountPageOverlayed
     * @param integer $mountPageId The ID of the mounted page
     */
    public function initializeMountedPage(array $mountProperties, $mountPageId)
    {
        $mountedPages = array($mountPageId);

        $this->addMountedPagesToIndexQueue($mountedPages, $mountProperties);
        $this->addIndexQueueItemIndexingProperties($mountProperties,
            $mountedPages);
    }

    /**
     * Initializes Mount Pages to be indexed through the Index Queue. The Mount
     * Pages are searched and their mounted virtual sub-trees are then resolved
     * and added to the Index Queue as if they were actually present below the
     * Mount Page.
     *
     * @return boolean TRUE if initialization of the Mount Pages was successful, FALSE otherwise
     */
    protected function initializeMountPages()
    {
        $mountPagesInitialized = false;
        $mountPages = $this->findMountPages();

        if (empty($mountPages)) {
            $mountPagesInitialized = true;
            return $mountPagesInitialized;
        }

        foreach ($mountPages as $mountPage) {
            if (!$this->validateMountPage($mountPage)) {
                continue;
            }

            $mountedPages = $this->resolveMountPageTree($mountPage['mountPageSource']);

            // handling mount_pid_ol behavior
            if ($mountPage['mountPageOverlayed']) {
                // the page shows the mounted page's content
                $mountedPages[] = $mountPage['mountPageSource'];
            } else {
                // Add page like a regular page, as only the sub tree is
                // mounted. The page itself has its own content.
                GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Queue')->updateItem(
                    $this->type,
                    $mountPage['uid'],
                    $this->indexingConfigurationName
                );
            }

            // This can happen when the mount point does not show the content of the
            // mounted page and the mounted page does not have any subpages.
            if (empty($mountedPages)) {
                continue;
            }

            DatabaseUtility::transactionStart();
            try {
                $this->addMountedPagesToIndexQueue($mountedPages, $mountPage);
                $this->addIndexQueueItemIndexingProperties($mountPage,
                    $mountedPages);

                DatabaseUtility::transactionCommit();
                $mountPagesInitialized = true;
            } catch (\Exception $e) {
                DatabaseUtility::transactionRollback();

                GeneralUtility::devLog(
                    'Index Queue initialization failed for mount pages',
                    'solr',
                    3,
                    array($e->__toString())
                );
                break;
            }
        }

        return $mountPagesInitialized;
    }

    /**
     * Checks whether a Mount Page is properly configured.
     *
     * @param array $mountPage A mount page
     * @return boolean TRUE if the Mount Page is OK, FALSE otherwise
     */
    protected function validateMountPage(array $mountPage)
    {
        $isValidMountPage = true;

        if (empty($mountPage['mountPageSource'])) {
            $isValidMountPage = false;

            $flashMessage = GeneralUtility::makeInstance(
                'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                'Property "Mounted page" must not be empty. Invalid Mount Page configuration for page ID ' . $mountPage['uid'] . '.',
                'Failed to initialize Mount Page tree. ',
                FlashMessage::ERROR
            );
            FlashMessageQueue::addMessage($flashMessage);
        }

        if (!$this->mountedPageExists($mountPage['mountPageSource'])) {
            $isValidMountPage = false;

            $flashMessage = GeneralUtility::makeInstance(
                'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                'The mounted page must be accessible in the frontend. '
                . 'Invalid Mount Page configuration for page ID '
                . $mountPage['uid'] . ', the mounted page with ID '
                . $mountPage['mountPageSource']
                . ' is not accessible in the frontend.',
                'Failed to initialize Mount Page tree. ',
                FlashMessage::ERROR
            );
            FlashMessageQueue::addMessage($flashMessage);
        }

        return $isValidMountPage;
    }

    /**
     * Checks whether the mounted page (mount page source) exists. That is,
     * whether it accessible in the frontend. So the record must exist
     * (deleted = 0) and must not be hidden (hidden = 0).
     *
     * @param integer $mountedPageId Mounted page ID
     * @return boolean TRUE if the page is accessible in the frontend, FALSE otherwise.
     */
    protected function mountedPageExists($mountedPageId)
    {
        $mountedPageExists = false;

        $mountedPage = BackendUtility::getRecord('pages', $mountedPageId, '*',
            ' AND hidden = 0');
        if (!empty($mountedPage)) {
            $mountedPageExists = true;
        }

        return $mountedPageExists;
    }

    /**
     * Adds the virtual / mounted pages to the Index Queue as if they would
     * belong to the same site where they are mounted.
     *
     * @param array $mountedPages An array of mounted page IDs
     * @param array $mountProperties Array with mount point properties (mountPageSource, mountPageDestination, mountPageOverlayed)
     */
    protected function addMountedPagesToIndexQueue(
        array $mountedPages,
        array $mountProperties
    ) {
        $mountIdentifier = $this->getMountPointIdentifier($mountProperties);
        $initializationQuery = 'INSERT INTO tx_solr_indexqueue_item (root, item_type, item_uid, indexing_configuration, indexing_priority, changed, has_indexing_properties, pages_mountidentifier, errors) '
            . $this->buildSelectStatement() . ', 1, ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mountIdentifier,
                'tx_solr_indexqueue_item') . ',""'
            . 'FROM pages '
            . 'WHERE '
            . 'uid IN(' . implode(',', $mountedPages) . ') '
            . $this->buildTcaWhereClause()
            . $this->buildUserWhereClause();

        $GLOBALS['TYPO3_DB']->sql_query($initializationQuery);

        $this->logInitialization($initializationQuery);
    }

    /**
     * Adds Index Queue item indexing properties for mounted pages. The page
     * indexer later needs to know that he's dealing with a mounted page, the
     * indexing properties will let make it possible for the indexer to
     * distinguish the mounted pages.
     *
     * @param array $mountPage An array with information about the root/destination Mount Page
     * @param array $mountedPages An array of mounted page IDs
     */
    protected function addIndexQueueItemIndexingProperties(
        array $mountPage,
        array $mountedPages
    ) {
        $mountIdentifier = $this->getMountPointIdentifier($mountPage);
        $mountPageItems = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '*',
            'tx_solr_indexqueue_item',
            'root = ' . intval($this->site->getRootPageId()) . ' '
            . 'AND item_type = \'pages\' '
            . 'AND item_uid IN(' . implode(',', $mountedPages) . ') '
            . 'AND has_indexing_properties = 1 '
            . 'AND pages_mountidentifier=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mountIdentifier,
                'tx_solr_indexqueue_item')
        );

        if (!is_array($mountPageItems)) {
            return;
        }

        foreach ($mountPageItems as $mountPageItemRecord) {
            $mountPageItem = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\IndexQueue\\Item',
                $mountPageItemRecord);

            $mountPageItem->setIndexingProperty('mountPageSource',
                $mountPage['mountPageSource']);
            $mountPageItem->setIndexingProperty('mountPageDestination',
                $mountPage['mountPageDestination']);
            $mountPageItem->setIndexingProperty('isMountedPage', '1');

            $mountPageItem->storeIndexingProperties();
        }
    }

    /**
     * Builds an identifier of the given mount point properties.
     *
     * @param array $mountProperties Array with mount point properties (mountPageSource, mountPageDestination, mountPageOverlayed)
     * @return string String consisting of mountPageDestination-mountPageSource-mountPageOverlayed
     */
    protected function getMountPointIdentifier(array $mountProperties)
    {
        return $mountProperties['mountPageDestination']
        . '-' . $mountProperties['mountPageSource']
        . '-' . $mountProperties['mountPageOverlayed'];
    }


    // Mount Page resolution


    /**
     * Finds the mount pages in the current site.
     *
     * @return array An array of mount pages
     */
    protected function findMountPages()
    {
        return $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid,
				\'1\'        as isMountPage,
				mount_pid    as mountPageSource,
				uid          as mountPageDestination,
				mount_pid_ol as mountPageOverlayed',
            'pages',
            $this->buildPagesClause()
            . $this->buildTcaWhereClause()
            . ' AND doktype = 7 AND no_search = 0'
        );
    }

    /**
     * Gets all the pages from a mounted page tree.
     *
     * @param integer $mountPageSourceId
     * @return array An array of page IDs in the mounted page tree
     */
    protected function resolveMountPageTree($mountPageSourceId)
    {
        $mountedSite = Site::getSiteByPageId($mountPageSourceId);

        return $mountedSite->getPages($mountPageSourceId);
    }
}
