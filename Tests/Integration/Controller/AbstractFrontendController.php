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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

abstract class AbstractFrontendController extends IntegrationTest
{
    /**
     * @throws NoSuchCacheException
     * @throws TestingFrameworkCoreException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->writeDefaultSolrTestSiteConfiguration();
    }

    /**
     * Executed after each test. Empties solr and checks if the index is empty
     */
    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        parent::tearDown();
    }

    /**
     * @throws SiteNotFoundException
     */
    protected function indexPages(array $importPageIds)
    {
        // Mark the pages as items to index
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        foreach ($importPageIds as $importPageId) {
            $site = $siteFinder->getSiteByPageId($importPageId);
            $queueItem = $this->addPageToIndex($importPageId, $site);
            $frontendUrl = $site->getRouter()->generateUri($importPageId);
            $this->executePageIndexer($frontendUrl, $queueItem);
        }
        $this->waitToBeVisibleInSolr();
    }

    /**
     * Adds a page to the queue (into DB table tx_solr_indexqueue_item) so it can
     * be fetched via a frontend subrequest
     */
    protected function addPageToIndex(int $pageId, Site $site): Item
    {
        $queueItem = [
            'root' => $site->getRootPageId(),
            'item_type' => 'pages',
            'item_uid' => $pageId,
            'indexing_configuration' => 'pages',
            'errors' => '',
        ];
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');
        $connection->insert('tx_solr_indexqueue_item', $queueItem);
        $queueItem['uid'] = (int)$connection->lastInsertId();
        return new Item($queueItem);
    }

    /**
     * Executes a Frontend request within the same PHP process to trigger the indexing of a page.
     */
    protected function executePageIndexer(string $url, Item $item): ResponseInterface
    {
        $request = new InternalRequest($url);

        // Now add the headers for item to the request
        $indexerRequest = GeneralUtility::makeInstance(PageIndexerRequest::class);
        $indexerRequest->setIndexQueueItem($item);
        $indexerRequest->setParameter('item', $item->getIndexQueueUid());
        $indexerRequest->addAction('indexPage');
        $headers = $indexerRequest->getHeaders();

        foreach ($headers as $header) {
            [$headerName, $headerValue] = GeneralUtility::trimExplode(':', $header, true, 2);
            $request = $request->withAddedHeader($headerName, $headerValue);
        }
        $response = $this->executeFrontendSubRequest($request);
        $response->getBody()->rewind();
        return $response;
    }
}
