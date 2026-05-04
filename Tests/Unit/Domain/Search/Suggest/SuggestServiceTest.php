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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SuggestServiceTest extends SetUpUnitTestCase
{
    protected SuggestService|MockObject $suggestService;
    protected SearchResultSetService|MockObject $searchResultSetServiceMock;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected QueryBuilder|MockObject $queryBuilderMock;
    protected SuggestQuery|MockObject $suggestQueryMock;

    protected function setUp(): void
    {
        $this->searchResultSetServiceMock = $this->createMock(SearchResultSetService::class);
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);

        $this->suggestQueryMock = $this->createMock(SuggestQuery::class);
        $this->queryBuilderMock->expects(self::once())->method('buildSuggestQuery')->willReturn($this->suggestQueryMock);

        $this->suggestService = $this->getMockBuilder(SuggestService::class)
            ->onlyMethods(['getSolrSuggestions'])
            ->setConstructorArgs([$this->searchResultSetServiceMock, $this->configurationMock, $this->queryBuilderMock])
            ->getMock();

        $container = new Container();
        $container->set(SiteFinder::class, $this->createMock(SiteFinder::class));
        GeneralUtility::setContainer($container);
        parent::setUp();
    }

    protected function assertSuggestQueryWithQueryStringCreated(string $queryString): void
    {
        $this->suggestQueryMock->expects(self::any())->method('getQuery')->willReturn($queryString);
    }

    #[Test]
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

        $typo3Request = $this->getDefaultTypo3FrontendRequest();
        $suggestions = $this->suggestService->getSuggestions($typo3Request, $fakeRequest);

        $expectedSuggestions = [
            'suggestions' => ['type', 'typo'],
            'suggestion' => 'ty',
            'documents' => [],
            'didSecondSearch' => false,
        ];

        self::assertSame($expectedSuggestions, $suggestions, 'Suggest response did not contain expected content');
    }

    #[Test]
    public function canHandleInvalidSyntaxInAdditionalFilters(): void
    {
        $this->assertNoSearchWillBeTriggered();
        $fakeRequest = $this->getFakedSearchRequest('some');

        $solrConnectionMock = $this->createMock(SolrConnection::class);
        $connectionManagerMock = $this->getAccessibleMock(ConnectionManager::class, ['getConnectionByPageId'], [], '', false);
        $connectionManagerMock->expects(self::any())->method('getConnectionByPageId')->willReturn($solrConnectionMock);
        GeneralUtility::setSingletonInstance(ConnectionManager::class, $connectionManagerMock);

        GeneralUtility::setSingletonInstance(EventDispatcherInterface::class, new EventDispatcher($this->createMock(ListenerProviderInterface::class)));

        $searchStub = new class ($this->createMock(SolrConnection::class)) extends Search implements SingletonInterface {
            public static SuggestServiceTest $suggestServiceTest;
            public function search(Query $query, $offset = 0, $limit = 10): ?ResponseAdapter
            {
                /** @var ResponseAdapter|MockBuilder $mockObject */
                $mockObject = self::$suggestServiceTest->provideMockBuilderInObjectsScope(ResponseAdapter::class)
                    ->onlyMethods([])->disableOriginalConstructor()->getMock();
                return $mockObject;
            }
        };
        $searchStub::$suggestServiceTest = $this;
        GeneralUtility::setSingletonInstance(Search::class, $searchStub);

        $suggestService = new SuggestService(
            $this->searchResultSetServiceMock,
            $this->configurationMock,
            $this->queryBuilderMock,
        );

        $typo3Request = $this->getDefaultTypo3FrontendRequest();
        $suggestions = $suggestService->getSuggestions($typo3Request, $fakeRequest);

        $expectedSuggestions = ['status' => false];
        self::assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    public function provideMockBuilderInObjectsScope(string $className): ResponseAdapter|MockBuilder
    {
        return parent::getMockBuilder($className);
    }

    #[Test]
    public function emptyJsonIsReturnedWhenSolrHasNoSuggestions(): void
    {
        $this->configurationMock->expects(self::never())->method('getSuggestShowTopResults');
        $this->assertNoSearchWillBeTriggered();

        $fakeRequest = $this->getFakedSearchRequest('ty');

        $this->suggestService->expects(self::once())->method('getSolrSuggestions')->willReturn([]);
        $typo3Request = $this->getDefaultTypo3FrontendRequest();
        $suggestions = $this->suggestService->getSuggestions($typo3Request, $fakeRequest);

        $expectedSuggestions = ['status' => false];
        self::assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    #[Test]
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
            ],
        );

        $fakeTopResults->expects(self::once())->method('getSearchResults')->willReturn($fakeResultDocuments);
        $this->searchResultSetServiceMock->expects(self::once())->method('search')->willReturn($fakeTopResults);

        $typo3Request = $this->getDefaultTypo3FrontendRequest();
        $suggestions = $this->suggestService->getSuggestions($typo3Request, $fakeRequest);

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

    protected function getDefaultTypo3FrontendRequest(): ServerRequestInterface
    {
        return (new ServerRequest('https://typo3-solr.com/', 'GET'))
            ->withAttribute('routing', new PageArguments(7411, '0', []))
            ->withAttribute('language', new SiteLanguage(0, 'en-US', new Uri('https://typo3-solr.com/'), []));
    }
}
