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
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\SolrService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

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
     * @var AbstractPlugin
     */
    protected $pluginMock;

    /**
     * @var SearchResultSetService
     */
    protected $searchResultSetService;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock('ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration');
        $this->searchMock = $this->getDumbMock('ApacheSolrForTypo3\Solr\Search');
        $this->pluginMock = $this->getDumbMock('TYPO3\CMS\Frontend\Plugin\AbstractPlugin');

        $this->searchResultSetService = $this->getMock('ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService',
            array('setPerPageInSession', 'getPerPageFromSession', 'getRegisteredSearchComponents'),
            array(
                $this->configurationMock,
                $this->searchMock,
                $this->pluginMock)
        );
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
        $this->fakeRegisteredSearchComponents(array());

            // we expect the the ->search method on the Search object will be called once
            // and we pass the response that should be returned when it was call to compare
            // later if we retrieve the expected result
        $fakeResponse = $this->getDumbMock('\Apache_Solr_Response');
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(array('q' => 'my search'));

        $this->assertPerPageInSessionWillNotBeChanged();
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

     /**
     * @test
     */
    public function testOffsetIsPassedAsExpectedWhenSearchWasPaginated()
    {
        $this->fakeRegisteredSearchComponents(array());

        $fakeResponse = $this->getDumbMock('\Apache_Solr_Response');
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 2. search', 50, $fakeResponse);
        $this->configurationMock->expects($this->once())->method('getSearchResultsPerPage')->will($this->returnValue(25));

        $fakeRequest = new SearchRequest(array('q' => 'my 2. search', 'page' => 2));

        $this->assertPerPageInSessionWillNotBeChanged();
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function testQueryAwareComponentGetsInitialized()
    {
        $this->configurationMock->expects($this->once())->method('getSearchConfiguration')->will($this->returnValue(array()));

            // we expect that the initialize method of our component will be called
        $fakeQueryAwareSpellChecker = $this->getDumbMock('\ApacheSolrForTypo3\Solr\Search\SpellcheckingComponent');
        $fakeQueryAwareSpellChecker->expects($this->once())->method('initializeSearchComponent');
        $fakeQueryAwareSpellChecker->expects($this->once())->method('setQuery');


        $this->fakeRegisteredSearchComponents(array($fakeQueryAwareSpellChecker));
        $fakeResponse = $this->getDumbMock('\Apache_Solr_Response');
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 3. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(array('q' => 'my 3. search'));

        $this->assertPerPageInSessionWillNotBeChanged();
        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function canRegisterSearchResponseProcessor()
    {
        $processSearchResponseBackup = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'];

        $testProcessor = 'ApacheSolrForTypo3\\Solr\\Tests\\Unit\\Domain\\Search\\ResultSet\\TestSearchResponseProcessor';
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse']['testProcessor'] = $testProcessor;
        $this->fakeRegisteredSearchComponents(array());

        $fakedSolrResponse = $this->getFixtureContent("fakeResponse.json");
        $fakeHttpResponse = $this->getDumbMock('\Apache_Solr_HttpTransport_Response');
        $fakeHttpResponse->expects($this->once())->method('getBody')->will($this->returnValue($fakedSolrResponse));

        $fakeResponse =new \Apache_Solr_Response($fakeHttpResponse);
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('my 4. search', 0, $fakeResponse);

        $fakeRequest = new SearchRequest(array('q' => 'my 4. search'));
        $this->assertPerPageInSessionWillNotBeChanged();
        $resultSet  = $this->searchResultSetService->search($fakeRequest);

        $response   = $resultSet->getResponse();
        $documents  = $response->response->docs;

        $this->assertSame(3, count($documents), 'Did not get 3 documents from fake response');
        $firstResult = $documents[0];
        $this->assertSame("PAGES", $firstResult->type, 'Could not get modified type from result');

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'] = $processSearchResponseBackup;
    }


    /**
     * @test
     */
    public function testGoingToFirstPageWhenResultPerPageWasChanged()
    {
        $this->fakeRegisteredSearchComponents(array());
        $this->configurationMock->expects($this->once())->method('getSearchResultsPerPageSwitchOptionsAsArray')
                                ->will($this->returnValue(array(10, 25)));

        $fakeRequest = new SearchRequest(array('q' => 'test', 'page' => 5, 'resultsPerPage' => 25));

            // we expect that still an offset of 0 is passed because page was 5 AND perPageWas passed which means
            // that the perPage value has changed.
        $fakeResponse = $this->getDumbMock('\Apache_Solr_Response');
        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);
        $this->assertPerPageInSessionWillBeChanged();

        $resultSet = $this->searchResultSetService->search($fakeRequest);
        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
    }

    /**
     * @test
     */
    public function testAdditionalFiltersGetPassedToTheQuery()
    {
        $this->fakeRegisteredSearchComponents(array());
        $fakeResponse = $this->getDumbMock('\Apache_Solr_Response');

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);

        $this->configurationMock->expects($this->any())->method('getSearchQueryFilterConfiguration')->will(
            $this->returnValue(array('type:pages'))
        );
        $fakeRequest = new SearchRequest(array('q' => 'test'));

        $this->assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse('test', 0, $fakeResponse);
        $this->assertPerPageInSessionWillNotBeChanged();

        $resultSet = $this->searchResultSetService->search($fakeRequest);

        $this->assertSame($resultSet->getResponse(), $fakeResponse, 'Did not get the expected fakeResponse');
        $this->assertSame(count($resultSet->getUsedQuery()->getFilters()), 1, 'There should be one registered filter in the query');
    }

    /**
     * @param string $expextedQueryString
     * @param integer $expectedOffset
     * @param \Apache_Solr_Response $fakeResponse
     */
    public function assertOneSearchWillBeTriggeredWithQueryAndShouldReturnFakeResponse($expextedQueryString, $expectedOffset, \Apache_Solr_Response $fakeResponse)
    {
        $this->searchMock->expects($this->once())->method('search')->with($expextedQueryString, $expectedOffset, null)->will(
            $this->returnValue($fakeResponse)
        );
    }

    /**
     * @return void
     */
    private function assertPerPageInSessionWillBeChanged()
    {
        $this->searchResultSetService->expects($this->once())->method('setPerPageInSession');
    }

    /**
     * @return void
     */
    private function assertPerPageInSessionWillNotBeChanged()
    {
        $this->searchResultSetService->expects($this->never())->method('setPerPageInSession');
    }
}
