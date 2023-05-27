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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Suggest;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SuggestQuery;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Suggest\SuggestService;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SuggestServiceTest extends SetUpUnitTestCase
{
    protected SuggestService|MockObject $suggestService;
    protected TypoScriptFrontendController|MockObject $tsfeMock;
    protected SearchResultSetService|MockObject $searchResultSetServiceMock;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected QueryBuilder|MockObject $queryBuilderMock;
    protected SuggestQuery|MockObject $suggestQueryMock;

    protected function setUp(): void
    {
        $this->tsfeMock = $this->createMock(TypoScriptFrontendController::class);
        $this->searchResultSetServiceMock = $this->createMock(SearchResultSetService::class);
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);

        $this->suggestQueryMock = $this->createMock(SuggestQuery::class);
        $this->queryBuilderMock->expects(self::once())->method('buildSuggestQuery')->willReturn($this->suggestQueryMock);

        $this->suggestService = $this->getMockBuilder(SuggestService::class)
            ->onlyMethods(['getSolrSuggestions'])
            ->setConstructorArgs([$this->tsfeMock, $this->searchResultSetServiceMock, $this->configurationMock, $this->queryBuilderMock])
            ->getMock();
        parent::setUp();
    }

    protected function assertSuggestQueryWithQueryStringCreated(string $queryString): void
    {
        $this->suggestQueryMock->expects(self::any())->method('getQuery')->willReturn($queryString);
    }

    /**
     * @test
     */
    public function canGetSuggestionsWithoutTopResults(): void
    {
        // the query string is used as prefix but no real query string is passed.
        $this->assertSuggestQueryWithQueryStringCreated('');
        $fakeRequest = $this->getFakedSearchRequest('ty');

        $this->configurationMock->expects(self::once())->method('getSuggestShowTopResults')->willReturn(false);

        $this->assertNoSearchWillBeTriggered();

        $this->suggestService->expects(self::once())->method('getSolrSuggestions')->willReturn([
            'type',
            'typo',
        ]);

        $suggestions = $this->suggestService->getSuggestions($fakeRequest, []);

        $expectedSuggestions = [
            'suggestions' => ['type', 'typo'],
            'suggestion' => 'ty',
            'documents' => [],
            'didSecondSearch' => false,
        ];

        self::assertSame($expectedSuggestions, $suggestions, 'Suggest response did not contain expected content');
    }

    /**
     * @test
     */
    public function canHandleInvalidSyntaxInAdditionalFilters(): void
    {
        $this->assertNoSearchWillBeTriggered();
        $fakeRequest = $this->getFakedSearchRequest('some');

        $solrConnectionMock = $this->createMock(SolrConnection::class);
        $connectionManagerMock = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $connectionManagerMock->expects(self::any())->method('getConnectionByPageId')->willReturn($solrConnectionMock);
        GeneralUtility::setSingletonInstance(ConnectionManager::class, $connectionManagerMock);

        $searchStub = new class ($this->createMock(SolrConnection::class)) extends Search implements SingletonInterface {
            public static SuggestServiceTest $suggestServiceTest;
            public function search(Query $query, $offset = 0, $limit = 10): ?ResponseAdapter
            {
                return self::$suggestServiceTest->getMockBuilder(ResponseAdapter::class)
                    ->onlyMethods([])->disableOriginalConstructor()->getMock();
            }
        };
        $searchStub::$suggestServiceTest = $this;
        GeneralUtility::setSingletonInstance(Search::class, $searchStub);

        $this->tsfeMock->expects(self::any())->method('getRequestedId')->willReturn(7411);
        $suggestService = new SuggestService(
            $this->tsfeMock,
            $this->searchResultSetServiceMock,
            $this->configurationMock,
            $this->queryBuilderMock
        );

        try {
            $suggestions = $suggestService->getSuggestions($fakeRequest);
        } catch (\Error $error) {
            self::fail(
                'The method \ApacheSolrForTypo3\Solr\Domain\Search\Suggest\SuggestService::getSolrSuggestions() ' .
                'can not handle Apache Solr syntax errors. The method is failing with exception from below:' . PHP_EOL . PHP_EOL .
                $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine()
            );
        }

        $expectedSuggestions = ['status' => false];
        self::assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    /**
     * @test
     */
    public function emptyJsonIsReturnedWhenSolrHasNoSuggestions(): void
    {
        $this->configurationMock->expects(self::never())->method('getSuggestShowTopResults');
        $this->assertNoSearchWillBeTriggered();

        $fakeRequest = $this->getFakedSearchRequest('ty');

        $this->suggestService->expects(self::once())->method('getSolrSuggestions')->willReturn([]);
        $suggestions = $this->suggestService->getSuggestions($fakeRequest, []);

        $expectedSuggestions = ['status' => false];
        self::assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    /**
     * @test
     */
    public function canGetSuggestionsWithTopResults(): void
    {
        $this->configurationMock->expects(self::once())->method('getSuggestShowTopResults')->willReturn(true);
        $this->configurationMock->expects(self::once())->method('getSuggestNumberOfTopResults')->willReturn(2);
        $this->configurationMock->expects(self::once())->method('getSuggestAdditionalTopResultsFields')->willReturn([]);

        $this->assertSuggestQueryWithQueryStringCreated('');
        $fakeRequest = $this->getFakedSearchRequest('type');
        $fakeRequest->expects(self::any())->method('getCopyForSubRequest')->willReturn($fakeRequest);

        $this->suggestService->expects(self::once())->method('getSolrSuggestions')->willReturn([
            'type' => 25,
            'typo' => 5,
        ]);

        $fakeTopResults = $this->createMock(SearchResultSet::class);
        $fakeResultDocuments = new SearchResultCollection(
            [
                $this->getFakedSearchResult('http://www.typo3-solr.com/a', 'pages', 'hello solr', 'my suggestions'),
                $this->getFakedSearchResult('http://www.typo3-solr.com/b', 'news', 'what new in solr', 'new autosuggest'),
            ]
        );

        $fakeTopResults->expects(self::once())->method('getSearchResults')->willReturn($fakeResultDocuments);
        $this->searchResultSetServiceMock->expects(self::once())->method('search')->willReturn($fakeTopResults);

        $suggestions = $this->suggestService->getSuggestions($fakeRequest, []);

        self::assertCount(2, $suggestions['documents'], 'Expected to have two top results');
        self::assertSame('pages', $suggestions['documents'][0]['type'], 'The first top result has an unexpected type');
        self::assertSame('news', $suggestions['documents'][1]['type'], 'The second top result has an unexpected type');
    }

    /**
     * Builds a faked SearchResult object.
     */
    protected function getFakedSearchResult(string $url, string $type, string $title, string $content): SearchResult|MockObject
    {
        $result = $this->createMock(SearchResult::class);
        $result->expects(self::once())->method('getUrl')->willReturn($url);
        $result->expects(self::once())->method('getType')->willReturn($type);
        $result->expects(self::once())->method('getTitle')->willReturn($title);
        $result->expects(self::once())->method('getContent')->willReturn($content);

        return $result;
    }

    protected function assertNoSearchWillBeTriggered(): void
    {
        $this->searchResultSetServiceMock->expects(self::never())->method('search');
    }

    protected function getFakedSearchRequest(string $queryString): SearchRequest|MockObject
    {
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::atLeastOnce())->method('getRawUserQuery')->willReturn($queryString);
        return $fakeRequest;
    }
}
