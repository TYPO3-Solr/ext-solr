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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrWriteService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Psr18Adapter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr write service is working as expected.
 */
class SolrWriteServiceTest extends IntegrationTestBase
{
    protected bool $initializeDatabase = false;

    protected SolrWriteService|MockObject $solrWriteService;

    protected function setUp(): void
    {
        parent::setUp();

        // @todo: Drop manual initialization of solr Connection and use provided EXT:Solr API.
        $psr7Client = $this->get(ClientInterface::class);
        $requestFactory = $this->get(RequestFactoryInterface::class);
        $streamFactory = $this->get(StreamFactoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $adapter = new Psr18Adapter(
            $psr7Client,
            $requestFactory,
            $streamFactory,
        );
        $client = new Client($adapter, $eventDispatcher);

        $client->clearEndpoints();
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        $client->createEndpoint(['host' => $solrConnectionInfo['host'], 'port' => $solrConnectionInfo['port'], 'path' => '/', 'core' => 'core_en', 'key' => 'admin'], true);

        $this->solrWriteService = GeneralUtility::makeInstance(SolrWriteService::class, $client, new TypoScriptConfiguration([]));
    }

    #[Test]
    public function canExtractByQuery(): void
    {
        $testFilePath = __DIR__ . '/Fixtures/testpdf.pdf';
        $extractQuery = GeneralUtility::makeInstance(ExtractingQuery::class, $testFilePath);
        $extractQuery->setExtractOnly(true);
        $response = $this->solrWriteService->extractByQuery($extractQuery);
        self::assertStringContainsString('PDF Test', $response[0], 'Could not extract text');
    }
}
