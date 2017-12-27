<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This testcase can be used to check if the ConnectionManager can be used
 * as expected.
 *
 * @author Timo Hund
 */
class ConnectionManagerTest extends IntegrationTest
{

    /**
     * @test
     */
    public function canUpdateConnections()
    {
        //we start with an empty registry and initialize the connections from the database
        //with a configured solr site
        $this->importDataSetFromFixture('can_initialize_connections.xml');

            /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $connections = $connectionManager->getAllConnections();
        $this->assertEquals(0, count($connections), 'There should not be any connection present');

        $connectionManager->updateConnections();
        $connections = $connectionManager->getAllConnections();
        $this->assertEquals(1, count($connections), 'There should be one connection present');
    }

    /**
     * @test
     */
    public function twoConnectionsGetUpdatedOnUpdateConnections()
    {
        //we start with an empty registry and initialize the connections from the database
        //with a configured solr site
        $this->importDataSetFromFixture('can_initialize_two_connections.xml');

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $connections = $connectionManager->getAllConnections();
        $this->assertEquals(0, count($connections), 'There should not be any connection present');

        $connectionManager->updateConnections();
        $connections = $connectionManager->getAllConnections();
        $this->assertEquals(2, count($connections), 'There should be one connection present');
    }

    /**
     * @test
     */
    public function oneConnectionsGetUpdatedOnUpdateConnectionByRootPageId()
    {
        //we start with an empty registry and initialize the connections from the database
        //with a configured solr site
        $this->importDataSetFromFixture('can_initialize_two_connections.xml');

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $connections = $connectionManager->getAllConnections();
        $this->assertEquals(0, count($connections), 'There should not be any connection present');

        $connectionManager->updateConnectionByRootPageId(2);
        $connections = $connectionManager->getAllConnections();
        $this->assertEquals(1, count($connections), 'There should be one connection present');
    }

    /**
     * ConnectionManager must use the connection for site(tree), where mount Point is defined.
     *
     * There is following scenario:
     *
     *     [0]
     *     |
     *     ——[20] Shared-Pages (Folder)
     *     |   |
     *     |   ——[24] FirstShared
     *     |       |
     *     |       ——[25] first sub page from FirstShared
     *     |       |
     *     |       ——[26] second sub page from FirstShared
     *     |
     *     ——[ 1] Page (Root)
     *         |
     *         ——[14] Mount Point 1 (to [24] to show contents from)
     *
     * @test
     */
    public function canFindSolrConnectionForMountedPageIfMountPointIsGiven()
    {
        $this->importDataSetFromFixture('can_find_connection_for_mouted_page.xml');

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        $solrService = $connectionManager->getConnectionByPageId(24, 0, '24-14');
        $this->assertInstanceOf(SolrConnection::class, $solrService, 'Should find solr connection for level 0 of mounted page.');

        $solrService1 = $connectionManager->getConnectionByPageId(25, 0, '24-14');
        $this->assertInstanceOf(SolrConnection::class, $solrService1, 'Should find solr connection for level 1 of mounted page.');
    }

    /**
     * @test
     */
    public function exceptionIsThrownForUnAvailableSolrConnectionOnGetConfigurationByRootPageId() {
        $this->expectException(NoSolrConnectionFoundException::class);

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $connectionManager->getConfigurationByRootPageId(4711);
    }

    /**
     * @test
     */
    public function exceptionIsThrownForUnAvailableSolrConnectionOnGetConnectionByPageId() {
        $this->expectException(NoSolrConnectionFoundException::class);

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $connectionManager->getConnectionByPageId(888);
    }
}