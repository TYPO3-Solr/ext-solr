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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\SpellcheckingComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResultSetTest extends UnitTest
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var Search
     */
    protected $searchMock;

    /**
     * @var SearchResultSetService
     */
    protected $searchResultSetService;

    /**
     * @var SolrLogManager
     */
    protected $solrLogManagerMock;

    /**
     * @var Query
     */
    protected $queryMock;

    /**
     * @var SiteHashService
     */
    protected $siteHashServiceMock;

    /**
     * @var EscapeService
     */
    protected $escapeServiceMock;

    /**
     * @var ObjectManager
     */
    protected $objectManagerMock;

    protected function setUp(): void
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->searchMock = $this->getDumbMock(Search::class);
        $this->solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);

        $this->siteHashServiceMock = $this->getDumbMock(SiteHashService::class);
        $this->escapeServiceMock = $this->getDumbMock(EscapeService::class);
        $this->escapeServiceMock->expects(self::any())->method('escape')->willReturnArgument(0);

        $this->searchResultSetService = $this->getMockBuilder(SearchResultSetService::class)
            ->onlyMethods(['getRegisteredSearchComponents'])
            ->setConstructorArgs([$this->configurationMock, $this->searchMock, $this->solrLogManagerMock])
            ->getMock();
        $this->objectManagerMock = $this->createMock(ObjectManager::class);
        $this->searchResultSetService->injectObjectManager($this->objectManagerMock);
        parent::setUp();
    }

    /**
     * @param $fakedRegisteredComponents
     */
    protected function fakeRegisteredSearchComponents(array $fakedRegisteredComponents)
    {
        $this->searchResultSetService->expects(self::once())->method('getRegisteredSearchComponents')->willReturn(
            $fakedRegisteredComponents
        );
    }

    /**
     * @test
     */
    public function testSearchIfFiredWithInitializedQuery()
    {
        $this->fakeRegisteredSearchComponents([]);

        // we expect the the ->search method on the Search object will be called once
        // and we pass the response that should be returned when it was call to compare
        // later if we retrieve the expected result
        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my search', 0, $fakeResponse);
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my search']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock
            ->expects(self::once())->method('get')->with(SearchResultSet::class)
            ->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
    * @test
    */
    public function testOffsetIsPassedAsExpectedWhenSearchWasPaginated()
    {
        $this->fakeRegisteredSearchComponents([]);

        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 2. search', 50, $fakeResponse);
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 2. search', 'page' => 3]]);
        $fakeRequest->setResultsPerPage(25);

        $this->objectManagerMock->expects(self::once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function testQueryAwareComponentGetsInitialized()
    {
        $this->configurationMock->expects(self::once())->method('getSearchConfiguration')->willReturn([]);
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        // we expect that the initialize method of our component will be called
        $fakeQueryAwareSpellChecker = $this->getDumbMock(SpellcheckingComponent::class);
        $fakeQueryAwareSpellChecker->expects(self::once())->method('initializeSearchComponent');
        $fakeQueryAwareSpellChecker->expects(self::once())->method('setQuery');

        $this->fakeRegisteredSearchComponents([$fakeQueryAwareSpellChecker]);
        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 3. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 3. search']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock->expects(self::once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function canRegisterSearchResultSetProcessor()
    {
        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $processSearchResponseBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'] ?? null;

        $testProcessor = TestSearchResultSetProcessor::class;
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['testProcessor'] = $testProcessor;
        $this->fakeRegisteredSearchComponents([]);

        $fakedSolrResponse = $this->getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 4. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 4. search']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock
            ->expects(self::exactly(2))
            ->method('get')
            ->withConsecutive(
                [SearchResultSet::class],
                [$testProcessor]
            )->will(self::onConsecutiveCalls(
                new SearchResultSet(),
                new TestSearchResultSetProcessor()
            ));

        $resultSet  = $this->searchResultSetService->search($fakeRequest);

        $documents  = $resultSet->getSearchResults();

        self::assertSame(3, count($documents), 'Did not get 3 documents from fake response');
        $firstResult = $documents[0];
        self::assertSame('PAGES', $firstResult->getType(), 'Could not get modified type from result');

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'] = $processSearchResponseBackup;
    }

    /**
     * @test
     */
    public function testAdditionalFiltersGetPassedToTheQuery()
    {
        $this->fakeRegisteredSearchComponents([]);
        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);

        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);
        $this->configurationMock->expects(self::any())->method('getSearchQueryFilterConfiguration')->willReturn(
            ['type:pages']
        );
        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'test']]);
        $fakeRequest->setResultsPerPage(10);

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);

        $this->objectManagerMock->expects(self::once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);

        self::assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
        self::assertSame(count($resultSet->getUsedQuery()->getFilterQueries()), 1, 'There should be one registered filter in the query');
    }

    /**
     * @test
     */
    public function testExpandedDocumentsGetAddedWhenVariantsAreConfigured()
    {
        // we fake that collapsing is enabled
        $this->configurationMock->expects(self::atLeastOnce())->method('getSearchVariants')->willReturn(true);

        // in this case we collapse on the type field
        $this->configurationMock->expects(self::atLeastOnce())->method('getSearchVariantsField')->willReturn('type');

        $this->configurationMock->expects(self::once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $this->fakeRegisteredSearchComponents([]);
        $fakedSolrResponse = $this->getFixtureContentByName('fakeCollapsedResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('variantsSearch', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'variantsSearch']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock->expects(self::once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        self::assertSame(1, count($resultSet->getSearchResults()), 'Unexpected amount of document');

        /** @var  $fistResult SearchResult */
        $fistResult = $resultSet->getSearchResults()[0];
        self::assertSame(5, count($fistResult->getVariants()), 'Unexpected amount of expanded result');
    }

    /**
     * @param string $expextedQueryString
     * @param int $expectedOffset
     * @param ResponseAdapter $fakeResponse
     */
    public function assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse($expextedQueryString, $expectedOffset, ResponseAdapter $fakeResponse)
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
