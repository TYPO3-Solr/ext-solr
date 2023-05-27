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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchResultSetServiceTest extends SetUpUnitTestCase
{
    protected SearchResultSetService $searchResultSetService;
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected Search|MockObject $searchMock;
    protected SolrLogManager|MockObject $logManagerMock;
    protected SearchResultBuilder|MockObject $searchResultBuilderMock;
    protected QueryBuilder|MockObject $queryBuilderMock;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->logManagerMock = $this->createMock(SolrLogManager::class);
        $this->searchMock = $this->createMock(Search::class);
        $this->searchResultBuilderMock = $this->createMock(SearchResultBuilder::class);
        $this->queryBuilderMock = $this->createMock(QueryBuilder::class);
        $this->searchResultSetService = new SearchResultSetService($this->configurationMock, $this->searchMock, $this->logManagerMock, $this->searchResultBuilderMock, $this->queryBuilderMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function searchIsNotTriggeredWhenEmptySearchDisabledAndEmptyQueryWasPassed(): void
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setRawQueryString('');
        $this->assertAllInitialSearchesAreDisabled();
        $resultSet = $this->searchResultSetService->search($searchRequest);
        self::assertFalse($resultSet->getHasSearched(), 'Search should not be executed when empty query string was passed');
    }

    /**
     * @test
     */
    public function searchIsNotTriggeredWhenEmptyQueryWasPassedAndEmptySearchWasDisabled(): void
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setRawQueryString('');
        $this->configurationMock->expects(self::once())->method('getSearchQueryAllowEmptyQuery')->willReturn(false);
        $resultSet = $this->searchResultSetService->search($searchRequest);
        self::assertFalse($resultSet->getHasSearched(), 'Search should not be executed when empty query string was passed');
    }

    protected function assertAllInitialSearchesAreDisabled(): void
    {
        $this->configurationMock->expects(self::any())->method('getSearchInitializeWithEmptyQuery')->willReturn(false);
        $this->configurationMock->expects(self::any())->method('getSearchShowResultsOfInitialEmptyQuery')->willReturn(false);
        $this->configurationMock->expects(self::any())->method('getSearchInitializeWithQuery')->willReturn('');
        $this->configurationMock->expects(self::any())->method('getSearchShowResultsOfInitialQuery')->willReturn(false);
    }
}
