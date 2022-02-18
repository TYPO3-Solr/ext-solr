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

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ReflectionException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function vsprintf;

/**
 * This testcase can be used to check if the ConnectionManager can be used
 * as expected.
 *
 * @author Timo Hund
 */
class ConnectionManagerTest extends IntegrationTest
{

    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * @return array
     */
    public function canFindSolrConnectionsByRootPageIdDataProvider()
    {
        return [
            ['rootPageId' => 1, 'siteName' => 'integration_tree_one', 'expectedSolrHost' => 'solr.testone.endpoint'],
            ['rootPageId' => 111, 'siteName' => 'integration_tree_two', 'expectedSolrHost' => 'solr.testtwo.endpoint'],
        ];
    }

    /**
     * ConnectionManager can find connection by root page ID (1 and then 111).
     * There is following scenario:
     *
     * [0]
     *  |
     *  ——[ 1] First site
     *  |
     *  ——[111] Second site
     *
     * @test
     * @dataProvider canFindSolrConnectionsByRootPageIdDataProvider
     *
     * @param int $rootPageId
     * @param string $siteName
     * @param string $expectedSolrHost
     * @throws NoSolrConnectionFoundException
     * @throws ReflectionException
     */
    public function canFindSolrConnectionsByRootPageId(int $rootPageId, string $siteName, string $expectedSolrHost)
    {
        $this->mergeSiteConfiguration($siteName, ['solr_host_read' => $expectedSolrHost]);
        $this->importDataSetFromFixture('ConnectionManagerTest_basic_connections.xml');

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        foreach ([0, 1, 2] as $languageID) {
            $solrService = $connectionManager->getConnectionByRootPageId($rootPageId, $languageID);
            self::assertInstanceOf(SolrConnection::class, $solrService, vsprintf('Should find solr connection for root page "%s" and language "%s"', [$rootPageId, $languageID]));
            self::assertEquals($expectedSolrHost, $solrService->getNode('read')->getHost(), vsprintf('Apache Solr host must be the same as configured.' .
                ' Wrong connection is used. I expected "%s" as Host for "%s" Site with Root-Page ID "%s".', [$expectedSolrHost, $siteName, $rootPageId]));
        }
    }

    /**
     * @return array
     */
    public function canFindSolrConnectionsByPageIdDataProvider()
    {
        return [
            ['pageId' => 11, 'siteName' => 'integration_tree_one', 'expectedSolrHost' => 'solr.testone.endpoint'],
            ['ageId' => 21, 'siteName' => 'integration_tree_two', 'expectedSolrHost' => 'solr.testtwo.endpoint'],
        ];
    }

    /**
     * The connection manager must find the connections for Root Pages 1 and 111,
     * by some of page IDs in desired tree(connection for 1 by 11, for 111 by 21).
     * There is following scenario:
     *
     * [0]
     *  |
     *  ——[ 1] First site
     *  |   |
     *  |   ——[11] Subpage of first site
     *  |
     *  ——[111] Second site
     *  |  |
     *  |  ——[21] Subpage of second site
     *  |
     *  ——[ 3] Detached and non Root Page-Tree
     *      |
     *      —— [31] Subpage 1 of Detached
     *      |
     *      —— [32] Subpage 2 of Detached
     *
     * @test
     * @dataProvider canFindSolrConnectionsByPageIdDataProvider
     *
     * @param int $rootPageId
     * @param string $siteName
     * @param string $expectedSolrHost
     * @throws NoSolrConnectionFoundException
     * @throws ReflectionException
     */
    public function canFindSolrConnectionsByPageId(int $pageId, string $siteName, string $expectedSolrHost)
    {
        $this->mergeSiteConfiguration($siteName, ['solr_host_read' => $expectedSolrHost]);
        $this->importDataSetFromFixture('ConnectionManagerTest_basic_connections.xml');

        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);

        foreach ([0, 1, 2] as $languageID) {
            $solrService = $connectionManager->getConnectionByPageId($pageId, $languageID);
            self::assertInstanceOf(SolrConnection::class, $solrService, vsprintf('Should find solr connection for page id "%s" and language "%s"', [$pageId, $languageID]));
            self::assertEquals($expectedSolrHost, $solrService->getNode('read')->getHost(), vsprintf('Apache Solr host must be the same as configured.' .
                ' Wrong connection is used. I expected "%s" as Host for "%s" Site with Root-Page ID "%s".', [$expectedSolrHost, $siteName, $pageId]));
        }
    }

    /**
     * There is following scenario for setup, the remaining stuff happens in test methods.:
     *
     * [0]
     *  |
     *  ——[ 3] Detached and non Root Page-Tree
     *      |
     *      —— [31] Subpage 1 of Detached
     *      |
     *      —— [32] Subpage 2 of Detached
     */
    protected function setupNotFullyConfiguredSite()
    {
        $defaultLanguage = $this->buildDefaultLanguageConfiguration('EN', '/en/');
        $german = $this->buildLanguageConfiguration('DE', '/de/');
        $danish = $this->buildLanguageConfiguration('DA', '/da/');
        $this->writeSiteConfiguration(
            'integration_tree_three',
            $this->buildSiteConfiguration(3, 'http://testthree.site/'),
            [
                $defaultLanguage, $german, $danish,
            ],
            [
                $this->buildErrorHandlingConfiguration('Fluid', [404]),
            ]
        );
    }

    /**
     * The connection manager must throw an exception on configured site without solr connection information by trying to get connection by root page id.
     * There is following scenario:
     *
     * [0]
     *  |
     *  ——[ 3] Detached and non Root Page-Tree
     *
     * @test
     */
    public function exceptionIsThrownForUnAvailableSolrConnectionOnGetConnectionByRootPageId()
    {
        $this->setupNotFullyConfiguredSite();

        $this->expectException(NoSolrConnectionFoundException::class);
        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $connectionManager->getConnectionByRootPageId(3);

        $this->expectException(NoSolrConnectionFoundException::class);
        $connectionManager->getConnectionByPageId(31);
    }

    /**
     * The connection manager must throw an exception on configured site without solr connection information by trying to get connection by page id.
     * There is following scenario:
     *
     * [0]
     *  |
     *   ——[ 3] Detached and non Root Page-Tree
     *       |
     *       —— [31] Subpage 1 of Detached
     *       |
     *       —— [32] Subpage 2 of Detached
     *
     * @test
     */
    public function exceptionIsThrownForUnAvailableSolrConnectionOnGetConnectionByPageId()
    {
        $this->setupNotFullyConfiguredSite();

        $this->expectException(NoSolrConnectionFoundException::class);
        /** @var $connectionManager ConnectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $connectionManager->getConnectionByPageId(31);
    }

    /**
     * ConnectionManager must use the connection for site(tree), where mount Point is defined.
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
        self::assertInstanceOf(SolrConnection::class, $solrService, 'Should find solr connection for level 0 of mounted page.');

        $solrService1 = $connectionManager->getConnectionByPageId(25, 0, '24-14');
        self::assertInstanceOf(SolrConnection::class, $solrService1, 'Should find solr connection for level 1 of mounted page.');
    }
}
