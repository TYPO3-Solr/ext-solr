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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\System\Solr\Service;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\ExtractingQuery;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Psr18Adapter;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr write service is working as expected.
 *
 * @author Timo Hund
 * (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
 */
class SolrWriteServiceTest extends IntegrationTest
{

    /**
     * @var SolrWriteService
     */
    protected $solrWriteService;

    /**
     * @return void
     * @throws NoSuchCacheException
     */
    public function setUp()
    {
        parent::setUp();

        // @todo: Drop manual initialization of solr Connection and use provided EXT:Solr API.
        $psr7Client = GeneralUtility::getContainer()->get(ClientInterface::class);
        $requestFactory = GeneralUtility::getContainer()->get(RequestFactoryInterface::class);
        $streamFactory = GeneralUtility::getContainer()->get(StreamFactoryInterface::class);
        /* @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()->getMock();
        $adapter = new Psr18Adapter(
            $psr7Client,
            $requestFactory,
            $streamFactory
        );
        $client = new Client($adapter, $eventDispatcher);

        $client->clearEndpoints();
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        $client->createEndpoint(['host' => $solrConnectionInfo['host'], 'port' => $solrConnectionInfo['port'], 'path' => '/', 'core' => 'core_en', 'key' => 'admin'] , true);

        $this->solrWriteService = GeneralUtility::makeInstance(SolrWriteService::class, $client);
    }

    /**
     * @test
     */
    public function canExtractByQuery()
    {
        $testFilePath = $this->getFixturePathByName('testpdf.pdf');
            /** @var $extractQuery \ApacheSolrForTypo3\Solr\Domain\Search\Query\ExtractingQuery*/
        $extractQuery = GeneralUtility::makeInstance(ExtractingQuery::class, $testFilePath);
        $extractQuery->setExtractOnly(true);
        $response = $this->solrWriteService->extractByQuery($extractQuery);
        $this->assertContains('PDF Test', $response[0], 'Could not extract text');
    }
}
