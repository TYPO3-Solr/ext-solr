<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Result\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DefaultResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test case for the SearchResult.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DefaultParserTest extends UnitTest
{
    /**
     * @var TypoScriptConfiguration
     */
    protected $configurationMock;

    /**
     * @var DefaultResultParser
     */
    protected $parser;

    public function setUp()
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->parser = new DefaultResultParser();
    }

    /**
     * @test
     */
    public function parseWillCreateResultCollectionFromSolrResponse()
    {
        $fakeResultSet = $this->getMockBuilder(SearchResultSet::class)->setMethods(['getResponse'])->getMock();

        $fakedSolrResponse = $this->getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);

        $fakeResultSet->expects($this->once())->method('getResponse')->will($this->returnValue($fakeResponse));
        $parsedResultSet = $this->parser->parse($fakeResultSet, true);
        $this->assertCount(3, $parsedResultSet->getSearchResults());
    }

    /**
     * @test
     */
    public function returnsResultSetWithResultCount()
    {
        $fakeResultSet = $this->getMockBuilder(SearchResultSet::class)->setMethods(['getResponse'])->getMock();

        $fakedSolrResponse = $this->getFixtureContentByName('fake_solr_response_with_query_fields_facets.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);

        $fakeResultSet->expects($this->once())->method('getResponse')->will($this->returnValue($fakeResponse));
        $parsedResultSet = $this->parser->parse($fakeResultSet, true);
        $this->assertSame(10, $parsedResultSet->getAllResultCount());
    }

    /**
     * @test
     */
    public function parseWillSetMaximumScore()
    {
        $fakeResultSet = $this->getMockBuilder(SearchResultSet::class)->setMethods(['getResponse'])->getMock();

        $fakedSolrResponse = $this->getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);

        $fakeResultSet->expects($this->once())->method('getResponse')->will($this->returnValue($fakeResponse));
        $parsedResultSet = $this->parser->parse($fakeResultSet, true);
        $this->assertSame(3.1, $parsedResultSet->getMaximumScore());
    }

    /**
     * @test
     */
    public function canParseReturnsFalseWhenGroupingIsEnabled()
    {
        $requestMock = $this->getDumbMock(SearchRequest::class);
        $requestMock->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($this->configurationMock));
        $fakeResultSet = $this->getDumbMock(SearchResultSet::class);
        $fakeResultSet->expects($this->any())->method('getUsedSearchRequest')->will($this->returnValue($requestMock));

        $this->configurationMock->expects($this->once())->method('getSearchGrouping')->will($this->returnValue(true));
        $this->assertFalse($this->parser->canParse($fakeResultSet));
    }

    /**
     * @test
     */
    public function canParseReturnsTrueWhenGroupingIsDisabled()
    {
        $requestMock = $this->getDumbMock(SearchRequest::class);
        $requestMock->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($this->configurationMock));
        $fakeResultSet = $this->getDumbMock(SearchResultSet::class);
        $fakeResultSet->expects($this->any())->method('getUsedSearchRequest')->will($this->returnValue($requestMock));

        $this->configurationMock->expects($this->once())->method('getSearchGrouping')->will($this->returnValue(false));
        $this->assertTrue($this->parser->canParse($fakeResultSet));
    }


}