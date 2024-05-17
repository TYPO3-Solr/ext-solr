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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchHasBeenExecutedEvent;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Tests\Unit\Fixtures\EventDispatcher\MockEventDispatcher;

class SearchResultSetTest extends SetUpUnitTestCase
{
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected Search|MockObject $searchMock;
    protected SearchResultSetService $searchResultSetService;
    protected SolrLogManager|MockObject $solrLogManagerMock;
    protected Query|MockObject $queryMock;
    protected EscapeService|MockObject $escapeServiceMock;
    protected MockEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->searchMock = $this->createMock(Search::class);
        $this->solrLogManagerMock = $this->createMock(SolrLogManager::class);

        $this->escapeServiceMock = $this->createMock(EscapeService::class);
        $this->escapeServiceMock->expects(self::any())->method('escape')->willReturnArgument(0);

        $queryBuilder = new QueryBuilder(
            $this->configurationMock,
            $this->solrLogManagerMock,
            $this->createMock(SiteHashService::class)
        );

        $this->eventDispatcher = new MockEventDispatcher();

        $this->searchResultSetService = new SearchResultSetService(
            $this->configurationMock,
            $this->searchMock,
            $this->solrLogManagerMock,
            null,
            $queryBuilder,
            $this->eventDispatcher
        );
        parent::setUp();
    }

    #[Test]
    public function testSearchIfFiredWithInitializedQuery(): void
    {
        // we expect the ->search method on the Search object will be called once
        // and we pass the response that should be returned when it was call to compare
        // later if we retrieve the expected result
        $fakeResponse = $this->createMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my search', 0, $fakeResponse);
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my search']]);
        $fakeRequest->setResultsPerPage(10);

        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    #[Test]
    public function testOffsetIsPassedAsExpectedWhenSearchWasPaginated(): void
    {
        $fakeResponse = $this->createMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 2. search', 50, $fakeResponse);
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 2. search', 'page' => 3]]);
        $fakeRequest->setResultsPerPage(25);

        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    #[Test]
    public function testComponentAsEventListenerGetsInitialized(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchConfiguration')->willReturn([]);
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $this->eventDispatcher->addListener(function (object $event) {
            if ($event instanceof AfterSearchQueryHasBeenPreparedEvent) {
                $event->getTypoScriptConfiguration()->getSearchConfiguration();
            }
        });
        $fakeResponse = $this->createMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 3. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 3. search']]);
        $fakeRequest->setResultsPerPage(10);

        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    #[Test]
    public function canRegisterSearchResultSetProcessor(): void
    {
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $this->eventDispatcher->addListener(function (object $event) {
            if ($event instanceof AfterSearchHasBeenExecutedEvent) {
                foreach ($event->getSearchResultSet()->getSearchResults() as $result) {
                    $result->type = strtoupper($result->type);
                }
            }
        });

        $fakedSolrResponse = self::getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 4. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 4. search']]);
        $fakeRequest->setResultsPerPage(10);
        $resultSet  = $this->searchResultSetService->search($fakeRequest);

        $documents  = $resultSet->getSearchResults();

        self::assertSame(3, count($documents), 'Did not get 3 documents from fake response');
        $firstResult = $documents[0];
        self::assertSame('PAGES', $firstResult->getType(), 'Could not get modified type from result');
    }

    #[Test]
    public function testAdditionalFiltersGetPassedToTheQuery(): void
    {
        $fakeResponse = $this->createMock(ResponseAdapter::class);

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);

        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);
        $this->configurationMock->expects(self::any())->method('getSearchQueryFilterConfiguration')->willReturn(
            ['type:pages']
        );
        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'test']]);
        $fakeRequest->setResultsPerPage(10);

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);
        $resultSet = $this->searchResultSetService->search($fakeRequest);

        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
        self::assertSame(count($resultSet->getUsedQuery()->getFilterQueries()), 1, 'There should be one registered filter in the query');
    }

    #[Test]
    public function testExpandedDocumentsGetAddedWhenVariantsAreConfigured(): void
    {
        // we fake that collapsing is enabled
        $this->configurationMock->expects(self::atLeastOnce())->method('getSearchVariants')->willReturn(true);

        // in this case we collapse on the type field
        $this->configurationMock->expects(self::atLeastOnce())->method('getSearchVariantsField')->willReturn('type');

        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $fakedSolrResponse = self::getFixtureContentByName('fakeCollapsedResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('variantsSearch', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'variantsSearch']]);
        $fakeRequest->setResultsPerPage(10);
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame(1, count($resultSet->getSearchResults()), 'Unexpected amount of document');

        /** @var SearchResult $fistResult */
        $fistResult = $resultSet->getSearchResults()[0];
        self::assertSame(5, count($fistResult->getVariants()), 'Unexpected amount of expanded result');
    }

    public function assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse(string $expextedQueryString, int $expectedOffset, ResponseAdapter $fakeResponse): void
    {
        $this->searchMock->expects(self::once())->method('search')->willReturnCallback(
            function (Query $query, $offset) use ($expextedQueryString, $expectedOffset, $fakeResponse) {
                $this->assertSame($expextedQueryString, $query->getQuery(), 'Search was not triggered with an expected queryString');
                $this->assertSame($expectedOffset, $offset);
                return $fakeResponse;
            }
        );
    }
}
