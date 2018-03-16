<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchRequestTest extends UnitTest
{

    /**
     * @var SearchRequest
     */
    protected $searchRequest;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->searchRequest = new SearchRequest();
    }

    /**
     * @test
     */
    public function testGetPageIsNullWhenNothingWasPassed()
    {
        $this->assertNull($this->searchRequest->getPage(), 'Page was expected to be null');
    }

    /**
     * @test
     */
    public function testCanMerge()
    {
        $this->searchRequest = new SearchRequest(['tx_solr' => ['page' => 2]]);
        $this->assertSame(2, $this->searchRequest->getPage(), 'Retrieved unexpected page');

        $this->searchRequest->mergeArguments(['tx_solr' => ['page' => 8]]);
        $this->assertSame(8, $this->searchRequest->getPage(), 'Page was not properly merged');
    }

    /**
     * @test
     */
    public function canGetActiveFilterNames()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertEquals(['type'], $request->getActiveFacetNames());
    }

    /**
     * @test
     */
    public function canGetRawQueryString()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertEquals('typo3', $request->getRawUserQuery());
    }

    /**
     * @test
     */
    public function canSetQueryString()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $data  = $request->setRawQueryString('foobar')->getAsArray();
        $this->assertEquals(['tx_solr' => ['q' => 'foobar']], $data, 'The argument container did not contain the expected argument');
    }

    /**
     * @test
     */
    public function canAddOneFacet()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->addFacetValue('foo', 'bar')->getAsArray();
        $expectedArguments = [];
        $expectedArguments['tx_solr']['filter'][0] = 'foo:bar';
        $this->assertSame($arguments, $expectedArguments, 'Adding a facet did not product the expected structure');
    }

    /**
     * @test
     */
    public function canAddManyFacets()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->addFacetValue('type', 'pages')->addFacetValue('type', 'tt_content')->getAsArray();
        $expectedArguments = [];
        $expectedArguments['tx_solr']['filter'][0] = 'type:pages';
        $expectedArguments['tx_solr']['filter'][1] = 'type:tt_content';

        $this->assertSame($arguments, $expectedArguments, 'Adding a facet did not product the expected structure');
    }

    /**
     * @test
     */
    public function canAddFacetsAndQuery()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->setRawQueryString('mysearch')->addFacetValue('type', 'tt_content')->getAsArray();

        $expectedArguments = [];
        $expectedArguments['tx_solr']['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        $this->assertSame($arguments, $expectedArguments, 'Could not set a query and add a facet at the same time');
    }

    /**
     * @test
     */
    public function canReset()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->setRawQueryString('mysearch')->addFacetValue('type', 'tt_content')->reset()->getAsArray();
        $expectedArguments = [];
        $this->assertSame($arguments, $expectedArguments, 'Could not reset arguments');
    }

    /**
     * @test
     */
    public function canGetCopyForSubRequest()
    {
        $request = $this->getSearchRequestFromQueryString('');

        // we persist before we reset the arguments therefore the arguments should be kept
        $arguments  = $request->setRawQueryString('mysearch')
                            ->addFacetValue('type', 'tt_content')
                            ->getCopyForSubRequest()
                            ->getAsArray();

        $expectedArguments = [];
        $expectedArguments['tx_solr']['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        $this->assertSame($arguments, $expectedArguments, 'Could not reset arguments');
    }

    /**
     * @test
     */
    public function nonPersistentArgumentsGetLostForSubRequest()
    {
        $request = $this->getSearchRequestFromQueryString('');

        // we persist before we reset the arguments therefore the arguments should be kept
        $arguments  = $request->setRawQueryString('mysearch')
                                ->addFacetValue('type', 'tt_content')
                                ->setPage(2)
                                ->getCopyForSubRequest()->getAsArray();

        $expectedArguments = [];
        $expectedArguments['tx_solr']['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        $this->assertSame($arguments, $expectedArguments);
    }

    /**
     * @test
     */
    public function canGetContextSystemLanguageUidPassedOnCreation()
    {
        $request = new SearchRequest([], 111, 4711);
        $this->assertSame($request->getContextSystemLanguageUid(), 4711, 'Can get initial passed sys_language_uid');
    }

    /**
     * @test
     */
    public function canGetContextPageUidPassedOnCreation()
    {
        $request = new SearchRequest([], 111, 4711);
        $this->assertSame($request->getContextPageUid(), 111, 'Can get initial passed page_uid');
    }

    /**
     * @test
     */
    public function canRemoveFacetValue()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);

        $this->assertTrue($request->getHasFacetValue('type', 'pages'), 'Facet was not present');
        $request->removeFacetValue('type', 'pages');
        $this->assertFalse($request->getHasFacetValue('type', 'pages'), 'Could not remove facet value');
    }

    /**
     * @test
     */
    public function canGetFacetValues()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages&tx_solr%5Bfilter%5D%5B1%5D=type%253Anews';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertEquals(['pages', 'news'], $request->getActiveFacetValuesByName('type'));
    }

    /**
     * @test
     */
    public function canRemoveAllFacets()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages&tx_solr%5Bfilter%5D%5B1%5D=type%253Aevents';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertSame(2, $request->getActiveFacetCount(), 'Expected to have two active facets');
        $request->removeAllFacets();
        $this->assertSame(0, $request->getActiveFacetCount(), 'Expected to have no active facets');
    }

    /**
     * @test
     */
    public function canRemoveFacetsByName()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages&tx_solr%5Bfilter%5D%5B1%5D=type%253Aevents&tx_solr%5Bfilter%5D%5B2%5D=created%253A1-4';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertSame(3, $request->getActiveFacetCount(), 'Expected to have two active facets');
        $request->removeAllFacetValuesByName('type');
        $this->assertSame(1, $request->getActiveFacetCount(), 'Only 1 facet should remain active');
    }

    /**
     * @test
     */
    public function canGetSortingField()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bsort%5D=title asc';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertTrue($request->getHasSorting(), 'Passed query has no sorting');
        $this->assertSame('title', $request->getSortingName(), 'Expected sorting name was title');
        $this->assertSame('asc', $request->getSortingDirection(), 'Expected sorting direction was asc');
    }

    /**
     * @test
     */
    public function canRemoveSorting()
    {
        $query = 'tx_solr%5Bq%5D=typo3&tx_solr%5Bsort%5D=title asc';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertTrue($request->getHasSorting(), 'Passed query has no sorting');
        $this->assertSame('title', $request->getSortingName(), 'Expected sorting name was title');
        $requestAsArray = $request->getAsArray();
        $this->assertTrue(isset($requestAsArray['tx_solr']['sort']), 'Sorting was not set but was expected to be set');

        $request->removeSorting();
        $this->assertFalse($request->getHasSorting(), 'Expected that sorting was removed, but is still present');

        $requestAsArray = $request->getAsArray();
        $this->assertFalse(isset($requestAsArray['tx_solr']['sort']), 'Sorting was set but was not expected to be set');
    }

    /**
     * @test
     */
    public function canSetSorting()
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertFalse($request->getHasSorting(), 'Passed query has no sorting');

        $request->setSorting('auther', 'desc');
        $this->assertTrue($request->getHasSorting(), 'Passed query has no sorting');
    }

    /**
     * @test
     */
    public function canGetHighestGroupItemPageWhenNoPageWasPassed()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $this->assertSame(1, $request->getHighestGroupPage(), 'Can not get highest group item page when no group page was passed');
    }

    /**
     * @test
     */
    public function canGetInitialGroupItemPage()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $this->assertSame(1, $request->getGroupItemPage('typeGroup', 'pages'), 'Can not get initial group item page');
    }

    /**
     * @test
     */
    public function canSetGroupItemPage()
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $request->setGroupItemPage('typeGroup', 'pages', 2);

        $this->assertSame(2, $request->getGroupItemPage('typeGroup', 'pages'), 'Can not set and get groupItemPage');
    }

    /**
     * @test
     */
    public function canSetGroupItemPageForQuery()
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $request->setGroupItemPage('pidGroup', 'pid:[0 to 5]', 3);

        $this->assertSame(3, $request->getGroupItemPage('pidGroup', 'pid:[0 to 5]'), 'Can not set and get groupItemPage for a query');
    }

    /**
     * @test
     */
    public function canResetAllGroupItemPages()
    {
        $query = 'tx_solr%5Bq%5D=typo3';
        $request = $this->getSearchRequestFromQueryString($query);
        $request->setGroupItemPage('typeGroup', 'pages', 2);
        $request->setGroupItemPage('colorGroup', 'colors', 4);

        $requestArguments = $request->getAsArray();
        $this->assertCount(2, $requestArguments['tx_solr']['groupPage'], 'Expected to have two group pages registered');

        $request->removeAllGroupItemPages();
        $requestArguments = $request->getAsArray();
        $this->assertNull($requestArguments['tx_solr']['groupPage'], 'Expected to have two group pages registered');
    }

    /**
     * @test
     */
    public function twoDifferentRequestsHaveADifferentId()
    {
        $newSearchRequest = new SearchRequest();
        $this->assertNotEquals($newSearchRequest->getId(), $this->searchRequest->getId(), 'Two different requests seem to have the same id');
    }

    /**
     * @test
     */
    public function setPerPageWillMarkedTheRequestAsChanged()
    {
        $this->assertFalse($this->searchRequest->getStateChanged());
        $this->searchRequest->setResultsPerPage(10);
        $this->assertTrue($this->searchRequest->getStateChanged());
    }

    /**
     * @param $query
     * @return SearchRequest
     */
    protected function getSearchRequestFromQueryString($query)
    {
        $FAKE_GET = [];
        parse_str(urldecode($query), $FAKE_GET);
        $request = new SearchRequest($FAKE_GET);
        return $request;
    }

    /**
     * @test
     */
    public function canGetContextTypoScriptConfigurationPassedOnCreation()
    {
        $typoScriptConfiguration = $this->getDumbMock(TypoScriptConfiguration::class);
        $request = new SearchRequest([], 111, 4711, $typoScriptConfiguration);

        $this->assertSame($request->getContextTypoScriptConfiguration(), $typoScriptConfiguration, 'Can get initial passed TypoScriptConfiguration');
    }
}
