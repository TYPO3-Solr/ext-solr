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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchResultSetServiceTest extends UnitTest
{
    /**
     * @var SearchResultSetService
     */
    protected $searchResultSetService;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var Search
     */
    protected $searchMock;

    /**
     * @var SolrLogManager
     */
    protected $logManagerMock;

    /**
     * @var SearchResultBuilder
     */
    protected $searchResultBuilderMock;

    /**
     * @var ObjectManager
     */
    protected $objectManagerMock;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilderMock;

    protected function setUp(): void
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->logManagerMock = $this->getDumbMock(SolrLogManager::class);
        $this->searchMock = $this->getDumbMock(Search::class);
        $this->searchResultBuilderMock = $this->getDumbMock(SearchResultBuilder::class);
        $this->queryBuilderMock = $this->getDumbMock(QueryBuilder::class);
        $this->searchResultSetService = new SearchResultSetService($this->configurationMock, $this->searchMock, $this->logManagerMock, $this->searchResultBuilderMock, $this->queryBuilderMock);
        $this->objectManagerMock = $this->createMock(ObjectManager::class);
        $this->searchResultSetService->injectObjectManager($this->objectManagerMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function searchIsNotTriggeredWhenEmptySearchDisabledAndEmptyQueryWasPassed()
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setRawQueryString(null);
        $this->assertAllInitialSearchesAreDisabled();
        $this->objectManagerMock->expects(self::once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($searchRequest);
        self::assertFalse($resultSet->getHasSearched(), 'Search should not be executed when empty query string was passed');
    }

    /**
     * @test
     */
    public function searchIsNotTriggeredWhenEmptyQueryWasPassedAndEmptySearchWasDisabled()
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setRawQueryString('');
        $this->configurationMock->expects(self::once())->method('getSearchQueryAllowEmptyQuery')->willReturn(false);
        $this->objectManagerMock->expects(self::once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($searchRequest);
        self::assertFalse($resultSet->getHasSearched(), 'Search should not be executed when empty query string was passed');
    }

    protected function assertAllInitialSearchesAreDisabled()
    {
        $this->configurationMock->expects(self::any())->method('getSearchInitializeWithEmptyQuery')->willReturn(false);
        $this->configurationMock->expects(self::any())->method('getSearchShowResultsOfInitialEmptyQuery')->willReturn(false);
        $this->configurationMock->expects(self::any())->method('getSearchInitializeWithQuery')->willReturn('');
        $this->configurationMock->expects(self::any())->method('getSearchShowResultsOfInitialQuery')->willReturn(false);
    }
}
