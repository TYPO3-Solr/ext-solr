<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Schmidt
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper\EscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;


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
    protected $objectManagerMock = null;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->searchMock = $this->getDumbMock(Search::class);
        $this->solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);

        $this->siteHashServiceMock = $this->getDumbMock(SiteHashService::class);
        $this->escapeServiceMock = $this->getDumbMock(EscapeService::class);
        $this->escapeServiceMock->expects($this->any())->method('escape')->will($this->returnArgument(0));

        $this->searchResultSetService = $this->getMockBuilder(SearchResultSetService::class)
            ->setMethods(['getRegisteredSearchComponents'])
            ->setConstructorArgs([$this->configurationMock, $this->searchMock, $this->solrLogManagerMock])
            ->getMock();
        $this->objectManagerMock = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->getMock();
        $this->searchResultSetService->injectObjectManager($this->objectManagerMock);
    }

    /**
     * @param $fakedRegisteredComponents
     */
    protected function fakeRegisteredSearchComponents(array $fakedRegisteredComponents)
    {
        $this->searchResultSetService->expects($this->once())->method('getRegisteredSearchComponents')->will(
            $this->returnValue($fakedRegisteredComponents)
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
        $this->configurationMock->expects($this->once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);


        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my search']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

     /**
     * @test
     */
    public function testOffsetIsPassedAsExpectedWhenSearchWasPaginated()
    {
        $this->fakeRegisteredSearchComponents([]);

        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 2. search', 50, $fakeResponse);
        $this->configurationMock->expects($this->once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 2. search','page' => 3]]);
        $fakeRequest->setResultsPerPage(25);

        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function testQueryAwareComponentGetsInitialized()
    {
        $this->configurationMock->expects($this->once())->method('getSearchConfiguration')->will($this->returnValue([]));
        $this->configurationMock->expects($this->once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

            // we expect that the initialize method of our component will be called
        $fakeQueryAwareSpellChecker = $this->getDumbMock(SpellcheckingComponent::class);
        $fakeQueryAwareSpellChecker->expects($this->once())->method('initializeSearchComponent');
        $fakeQueryAwareSpellChecker->expects($this->once())->method('setQuery');

        $this->fakeRegisteredSearchComponents([$fakeQueryAwareSpellChecker]);
        $fakeResponse = $this->getDumbMock(ResponseAdapter::class);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 3. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 3. search']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function canRegisterSearchResultSetProcessor()
    {
        $this->configurationMock->expects($this->once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $processSearchResponseBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'];

        $testProcessor = TestSearchResultSetProcessor::class;
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']['testProcessor'] = $testProcessor;
        $this->fakeRegisteredSearchComponents([]);

        $fakedSolrResponse = $this->getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 4. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'my 4. search']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock->expects($this->at(0))->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $this->objectManagerMock->expects($this->at(1))->method('get')->with($testProcessor)->willReturn(new TestSearchResultSetProcessor());

        $resultSet  = $this->searchResultSetService->search($fakeRequest);

        $documents  = $resultSet->getSearchResults();

        $this->assertSame(3, count($documents), 'Did not get 3 documents from fake response');
        $firstResult = $documents[0];
        $this->assertSame('PAGES', $firstResult->getType(), 'Could not get modified type from result');

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

        $this->configurationMock->expects($this->once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);
        $this->configurationMock->expects($this->any())->method('getSearchQueryFilterConfiguration')->will(
            $this->returnValue(['type:pages'])
        );
        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'test']]);
        $fakeRequest->setResultsPerPage(10);

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);

        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);

        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
        $this->assertSame(count($resultSet->getUsedQuery()->getFilterQueries()), 1, 'There should be one registered filter in the query');
    }

    /**
     * @test
     */
    public function testExpandedDocumentsGetAddedWhenVariantsAreConfigured()
    {
        // we fake that collapsing is enabled
        $this->configurationMock->expects($this->atLeastOnce())->method('getSearchVariants')->will($this->returnValue(true));

            // in this case we collapse on the type field
        $this->configurationMock->expects($this->atLeastOnce())->method('getSearchVariantsField')->will($this->returnValue('type'));

        $this->configurationMock->expects($this->once())->method('getSearchQueryReturnFieldsAsArray')->willReturn(['*']);

        $this->fakeRegisteredSearchComponents([]);
        $fakedSolrResponse = $this->getFixtureContentByName('fakeCollapsedResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('variantsSearch', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(['tx_solr' => ['q' => 'variantsSearch']]);
        $fakeRequest->setResultsPerPage(10);

        $this->objectManagerMock->expects($this->once())->method('get')->with(SearchResultSet::class)->willReturn(new SearchResultSet());
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame(1, count($resultSet->getSearchResults()), 'Unexpected amount of document');

            /** @var  $fistResult SearchResult */
        $fistResult = $resultSet->getSearchResults()[0];
        $this->assertSame(5, count($fistResult->getVariants()), 'Unexpected amount of expanded result');
    }

    /**
     * @param string $expextedQueryString
     * @param int $expectedOffset
     * @param ResponseAdapter $fakeResponse
     */
    public function assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse($expextedQueryString, $expectedOffset, ResponseAdapter $fakeResponse)
    {
        $this->searchMock->expects($this->once())->method('search')->will(
            $this->returnCallback(
                function(Query $query, $offset) use($expextedQueryString, $expectedOffset, $fakeResponse) {

                    $this->assertSame($expextedQueryString, $query->getQuery() , "Search was not triggered with an expected queryString");
                    $this->assertSame($expectedOffset, $offset);
                    return $fakeResponse;
                }

            )
        );
    }
}
