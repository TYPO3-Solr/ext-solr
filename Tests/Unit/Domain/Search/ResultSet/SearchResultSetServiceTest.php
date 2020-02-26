<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
    protected $objectManagerMock = null;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilderMock;

    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->logManagerMock = $this->getDumbMock(SolrLogManager::class);
        $this->searchMock = $this->getDumbMock(Search::class);
        $this->searchResultBuilderMock = $this->getDumbMock(SearchResultBuilder::class);
        $this->queryBuilderMock = $this->getDumbMock(QueryBuilder::class);
        $this->searchResultSetService = new SearchResultSetService($this->configurationMock, $this->searchMock, $this->logManagerMock, $this->searchResultBuilderMock, $this->queryBuilderMock);
        $this->objectManagerMock = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->getMock();
        $this->searchResultSetService->injectObjectManager($this->objectManagerMock);
    }

    /**
     * @test
     */
    public function searchIsNotTriggeredWhenEmptySearchDisabledAndEmptyQueryWasPassed()
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setRawQueryString(null);
        $this->assertAllInitialSearchesAreDisabled();
        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($searchRequest);
        $this->assertFalse($resultSet->getHasSearched(), 'Search should not be executed when empty query string was passed');
    }

    /**
     * @test
     */
    public function searchIsNotTriggeredWhenEmptyQueryWasPassedAndEmptySearchWasDisabled()
    {
        $searchRequest = new SearchRequest();
        $searchRequest->setRawQueryString("");
        $this->configurationMock->expects($this->once())->method('getSearchQueryAllowEmptyQuery')->willReturn(false);
        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($searchRequest);
        $this->assertFalse($resultSet->getHasSearched(), 'Search should not be executed when empty query string was passed');
    }

    /**
     * @return void
     */
    protected function assertAllInitialSearchesAreDisabled()
    {
        $this->configurationMock->expects($this->any())->method('getSearchInitializeWithEmptyQuery')->willReturn(false);
        $this->configurationMock->expects($this->any())->method('getSearchShowResultsOfInitialEmptyQuery')->willReturn(false);
        $this->configurationMock->expects($this->any())->method('getSearchInitializeWithQuery')->willReturn(false);
        $this->configurationMock->expects($this->any())->method('getSearchShowResultsOfInitialQuery')->willReturn(false);
    }
}
