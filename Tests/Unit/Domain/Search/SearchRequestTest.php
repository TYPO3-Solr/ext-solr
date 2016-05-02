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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

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
        $this->searchRequest = new SearchRequest(array('tx_solr' => array('page' => 2)));
        $this->assertSame(2, $this->searchRequest->getPage(), 'Retrieved unexpected page');

        $this->searchRequest->mergeArguments(array('tx_solr' => array('page' => 8)));
        $this->assertSame(8, $this->searchRequest->getPage(), 'Page was not properly merged');
    }

    /**
     * @test
     */
    public function canGetActiveFilterNames()
    {
        $query = 'q=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
        $request = $this->getSearchRequestFromQueryString($query);
        $this->assertEquals(array('type'), $request->getActiveFacetNames());
    }

    /**
     * @test
     */
    public function canGetRawQueryString()
    {
        $query = 'q=typo3&tx_solr%5Bfilter%5D%5B0%5D=type%253Apages';
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
        $this->assertEquals(array('q' => 'foobar'), $data, 'The argument container did not contain the expected argument');
    }

    /**
     * @test
     */
    public function canAddOneFacet()
    {
        $request = $this->getSearchRequestFromQueryString('');
        $arguments  = $request->addFacetValue('foo', 'bar')->getAsArray();
        $expectedArguments = array();
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
        $expectedArguments = array();
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

        $expectedArguments = array();
        $expectedArguments['q'] = 'mysearch';
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
        $expectedArguments = array();
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

        $expectedArguments = array();
        $expectedArguments['q'] = 'mysearch';
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

        $expectedArguments = array();
        $expectedArguments['q'] = 'mysearch';
        $expectedArguments['tx_solr']['filter'][0] = 'type:tt_content';

        $this->assertSame($arguments, $expectedArguments);
    }

    /**
     * @test
     */
    public function canGetContextSystemLanguageUidPassedOnCreation()
    {
        $request = new SearchRequest(array(), 111, 4711);
        $this->assertSame($request->getContextSystemLanguageUid(), 4711, 'Can get initial passed sys_language_uid');
    }

    /**
     * @test
     */
    public function canGetContextPageUidPassedOnCreation()
    {
        $request = new SearchRequest(array(), 111, 4711);
        $this->assertSame($request->getContextPageUid(), 111, 'Can get initial passed page_uid');
    }

    /**
     * @param $query
     * @return SearchRequest
     */
    protected function getSearchRequestFromQueryString($query)
    {
        $FAKE_GET = array();
        parse_str(urldecode($query), $FAKE_GET);
        $request = new SearchRequest($FAKE_GET);
        return $request;
    }
}
