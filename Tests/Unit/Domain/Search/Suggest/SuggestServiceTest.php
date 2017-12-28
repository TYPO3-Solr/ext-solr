<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 * 
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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Suggest\SuggestService;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SuggestServiceTest extends UnitTest
{
    /**
     * @var SuggestService
     */
    protected $suggestService;

    /**
     * @var TypoScriptFrontendController
     */
    protected $tsfeMock;

    /**
     * @var SearchResultSetService
     */
    protected $searchResultSetServiceMock;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilderMock;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->tsfeMock = $this->getDumbMock(TypoScriptFrontendController::class);
        $this->searchResultSetServiceMock = $this->getDumbMock(SearchResultSetService::class);
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->queryBuilderMock = $this->getDumbMock(QueryBuilder::class);

        $this->suggestService = $this->getMockBuilder(SuggestService::class)
            ->setMethods(['getSolrSuggestions'])
            ->setConstructorArgs([$this->tsfeMock, $this->searchResultSetServiceMock, $this->configurationMock, $this->queryBuilderMock])
            ->getMock();
    }

    /**
     * @test
     */
    public function canGetSuggestionsWithoutTopResults()
    {
        $this->configurationMock->expects($this->once())->method('getSuggestShowTopResults')->will($this->returnValue(false));

        $this->assertNoSearchWillBeTriggered();
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->atLeastOnce())->method('getRawUserQuery')->will($this->returnValue('ty'));

        $this->suggestService->expects($this->once())->method('getSolrSuggestions')->will($this->returnValue([
            'type',
            'typo'
        ]));

        $suggestions = $this->suggestService->getSuggestions($fakeRequest, '');

        $expectedSuggestions = [
            'suggestions' => ['type', 'typo'],
            'suggestion' => 'ty',
            'documents' => [],
            'didSecondSearch' => false
        ];

        $this->assertSame($expectedSuggestions, $suggestions, 'Suggest response did not contain expected content');
    }

    /**
     * @test
     */
    public function emptyJsonIsReturnedWhenSolrHasNoSuggestions()
    {
        $this->configurationMock->expects($this->never())->method('getSuggestShowTopResults');
        $this->assertNoSearchWillBeTriggered();

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->atLeastOnce())->method('getRawUserQuery')->will($this->returnValue('ty'));

        $this->suggestService->expects($this->once())->method('getSolrSuggestions')->will($this->returnValue([]));
        $suggestions = $this->suggestService->getSuggestions($fakeRequest, '');

        $expectedSuggestions = ['status' => false];
        $this->assertSame($expectedSuggestions, $suggestions, 'Suggest did not return status false');
    }

    /**
     * @test
     */
    public function canGetSuggestionsWithTopResults()
    {
        $this->configurationMock->expects($this->once())->method('getSuggestShowTopResults')->will($this->returnValue(true));
        $this->configurationMock->expects($this->once())->method('getSuggestNumberOfTopResults')->will($this->returnValue(2));

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->atLeastOnce())->method('getRawUserQuery')->will($this->returnValue('type'));
        $fakeRequest->expects($this->any())->method('getCopyForSubRequest')->will($this->returnValue($fakeRequest));

        $this->suggestService->expects($this->once())->method('getSolrSuggestions')->will($this->returnValue([
            'type',
            'typo'
        ]));

        $fakeTopResults = $this->getDumbMock(SearchResultSet::class);
        $fakeResultDocuments = new SearchResultCollection(
            [
                $this->getFakedSearchResult('http://www.typo3-solr.com/a','pages','hello solr','my suggestions'),
                $this->getFakedSearchResult('http://www.typo3-solr.com/b','news','what new in solr','new autosuggest'),
            ]
        );

        $fakeTopResults->expects($this->once())->method('getSearchResults')->will($this->returnValue($fakeResultDocuments));
        $this->searchResultSetServiceMock->expects($this->once())->method('search')->will($this->returnValue($fakeTopResults));

        $suggestions = $this->suggestService->getSuggestions($fakeRequest, '');

        $this->assertCount(2, $suggestions['documents'], 'Expected to have two top results');
        $this->assertSame('pages', $suggestions['documents'][0]['type'],'The first top result has an unexpected type');
        $this->assertSame('news', $suggestions['documents'][1]['type'],'The second top result has an unexpected type');
    }

    /**
     * Builds a faked SearchResult object.
     *
     * @param string $url
     * @param string $type
     * @param string $title
     * @param string $content
     * @return SearchResult
     */
    protected function getFakedSearchResult($url, $type, $title, $content)
    {
        $result = $this->getDumbMock(SearchResult::class);
        $result->expects($this->once())->method('getUrl')->will($this->returnValue($url));
        $result->expects($this->once())->method('getType')->will($this->returnValue($type));
        $result->expects($this->once())->method('getTitle')->will($this->returnValue($title));
        $result->expects($this->once())->method('getContent')->will($this->returnValue($content));

        return $result;
    }

    /**
     * @return void
     */
    protected function assertNoSearchWillBeTriggered()
    {
        $this->searchResultSetServiceMock->expects($this->never())->method('search');
    }
}