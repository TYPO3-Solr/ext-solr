<?php
namespace ApacheSolrForTypo3\Solr\Command;

/**
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
use ApacheSolrForTypo3\Solr\ConnectionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Controller to run solr specific tasks via CLI
 * @todo @deprecated This class can be dropped when legacy support will be dropped
 * @extensionScannerIgnoreFile
 */
class SolrCommandController extends CommandController
{
    /**
     * Update EXT:solr connections
     *
     * @param int $rootPageId A site root page id
     */
    public function updateConnectionsCommand($rootPageId = null)
    {
        /* @var ConnectionManager $connectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        if ($rootPageId !== null) {
            $connectionManager->updateConnectionByRootPageId($rootPageId);
        } else {
            $connectionManager->updateConnections();
        }
        $this->outputLine('<info>EXT:solr connections are updated in the registry.</info>');
    }
}
