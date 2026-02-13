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

use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\System\Solr\Service\SolrAdminService;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use Solarium\Client;
use Solarium\Core\Client\Adapter\Curl;
use Traversable;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase to check if the solr admin service is working as expected.
 */
class SolrAdminServiceTest extends IntegrationTestBase
{
    protected bool $initializeDatabase = false;

    protected SolrAdminService $solrAdminService;

    /**
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $adapter = new Curl();
        $client = new Client(
            $adapter,
            $eventDispatcher,
        );
        $client->clearEndpoints();
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        $client->createEndpoint(['host' => $solrConnectionInfo['host'], 'port' => $solrConnectionInfo['port'], 'path' => '/', 'core' => 'core_en', 'key' => 'admin'], true);

        $this->solrAdminService = GeneralUtility::makeInstance(SolrAdminService::class, $client);
    }

    public static function synonymDataProvider(): Traversable
    {
        yield 'normal' => ['baseWord' => 'homepage', 'synonyms' => ['website']];
        yield 'umlaut' => ['baseWord' => 'früher', 'synonyms' => ['vergangenheit']];
        yield '"' => ['baseWord' => '"', 'synonyms' => ['quote mark']];
        yield '%' => ['baseWord' => '%', 'synonyms' => ['percent']];
        yield '#' => ['baseWord' => '#', 'synonyms' => ['hashtag']];
        yield ':' => ['baseWord' => ':', 'synonyms' => ['colon']];
        yield ';' => ['baseWord' => ';', 'synonyms' => ['semicolon']];

        // '/' still persists in https://issues.apache.org/jira/browse/SOLR-6853
        //yield '/' => ['baseWord' => '/', 'synonyms' => ['slash']]
    }

    #[DataProvider('synonymDataProvider')]
    #[Test]
    public function canAddAndDeleteSynonym(string $baseWord, array $synonyms = []): void
    {
        $this->solrAdminService->deleteSynonym($baseWord);
        $this->solrAdminService->reloadCore();

        $synonymsBeforeAdd = $this->solrAdminService->getSynonyms($baseWord);
        self::assertEquals([], $synonymsBeforeAdd, 'Synonyms was not empty');

        $this->solrAdminService->addSynonym($baseWord, $synonyms);
        $this->solrAdminService->reloadCore();

        $synonymsAfterAdd = $this->solrAdminService->getSynonyms($baseWord);

        self::assertEquals($synonyms, $synonymsAfterAdd, 'Could not retrieve synonym after adding');

        $this->solrAdminService->deleteSynonym($baseWord);
        $this->solrAdminService->reloadCore();

        $synonymsAfterRemove = $this->solrAdminService->getSynonyms($baseWord);
        self::assertEquals([], $synonymsAfterRemove, 'Synonym was not removed');
    }

    public static function stopWordDataProvider(): Traversable
    {
        yield 'normal' => ['stopWord' => 'badword'];
        yield 'umlaut' => ['stopWord' => 'frühaufsteher'];
    }

    /**
     * @throws InvalidArgumentException
     */
    #[DataProvider('stopWordDataProvider')]
    #[Test]
    public function canAddStopWord(string $stopWord): void
    {
        $stopWords = $this->solrAdminService->getStopWords();

        self::assertNotContains($stopWord, $stopWords, 'Stopwords are not empty after initializing');

        $this->solrAdminService->addStopWords($stopWord);
        $this->solrAdminService->reloadCore();

        $stopWordsAfterAdd = $this->solrAdminService->getStopWords();

        self::assertContains($stopWord, $stopWordsAfterAdd, 'Stopword was not added');

        $this->solrAdminService->deleteStopWord($stopWord);
        $this->solrAdminService->reloadCore();

        $stopWordsAfterDelete = $this->solrAdminService->getStopWords();
        self::assertNotContains($stopWord, $stopWordsAfterDelete, 'Stopwords are not empty after removing');
    }

    /**
     * Check if the default stopswords are stored in the solr server.
     */
    #[Test]
    public function containsDefaultStopWord(): void
    {
        $stopWordsInSolr = $this->solrAdminService->getStopWords();
        self::assertContains('and', $stopWordsInSolr, 'Default stopword and was not present');
    }

    #[Test]
    public function canGetSystemInformation(): void
    {
        $informationResponse = $this->solrAdminService->getSystemInformation();
        self::assertSame(200, $informationResponse->getHttpStatus(), 'Could not get information response from solr server');
    }

    /**
     * @throws PingFailedException
     */
    #[Test]
    public function canGetPingRoundtrimRunTime(): void
    {
        $pingRuntime = $this->solrAdminService->getPingRoundTripRuntime();
        self::assertGreaterThan(0, $pingRuntime, 'Ping runtime should be larger then 0');
        self::assertTrue(is_float($pingRuntime), 'Ping runtime should be an integer');
    }

    #[Test]
    public function canGetSolrServiceVersion(): void
    {
        $solrServerVersion = $this->solrAdminService->getSolrServerVersion();
        $isVersionHigherSix = version_compare('6.0.0', $solrServerVersion, '<');
        self::assertTrue($isVersionHigherSix, 'Expecting to run on version larger then 6.0.0');
    }

    #[Test]
    public function canReloadCore(): void
    {
        $result = $this->solrAdminService->reloadCore();
        self::assertSame(200, $result->getHttpStatus(), 'Reload core did not responde with a 200 ok status');
    }

    #[Test]
    public function canGetPluginsInformation(): void
    {
        $result = $this->solrAdminService->getPluginsInformation();
        self::assertSame(0, $result->responseHeader->status);
        self::assertSame(2, count($result));
    }

    /**
     * @throws MockObjectException
     */
    #[Test]
    public function canParseLanguageFromSchema(): void
    {
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $adapter = new Curl();
        $client = new Client(
            $adapter,
            $eventDispatcher,
        );
        $client->clearEndpoints();
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        $client->createEndpoint(['host' => $solrConnectionInfo['host'], 'port' => $solrConnectionInfo['port'], 'path' => '/', 'core' => 'core_de', 'key' => 'admin'], true);

        $this->solrAdminService = GeneralUtility::makeInstance(SolrAdminService::class, $client);
        self::assertSame('core_de', $this->solrAdminService->getSchema()->getManagedResourceId(), 'Could not get the id of managed resources from core.');
    }
}
