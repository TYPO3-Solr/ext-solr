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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result\Parser;

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

    protected function setUp(): void
    {
        $this->configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->parser = new DefaultResultParser();
        parent::setUp();
    }

    /**
     * @test
     */
    public function parseWillCreateResultCollectionFromSolrResponse()
    {
        $fakeResultSet = $this->getMockBuilder(SearchResultSet::class)->onlyMethods(['getResponse'])->getMock();

        $fakedSolrResponse = $this->getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);

        $fakeResultSet->expects(self::once())->method('getResponse')->willReturn($fakeResponse);
        $parsedResultSet = $this->parser->parse($fakeResultSet, true);
        self::assertCount(3, $parsedResultSet->getSearchResults());
    }

    /**
     * @test
     */
    public function returnsResultSetWithResultCount()
    {
        $fakeResultSet = $this->getMockBuilder(SearchResultSet::class)->onlyMethods(['getResponse'])->getMock();

        $fakedSolrResponse = $this->getFixtureContentByName('fake_solr_response_with_query_fields_facets.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);

        $fakeResultSet->expects(self::once())->method('getResponse')->willReturn($fakeResponse);
        $parsedResultSet = $this->parser->parse($fakeResultSet, true);
        self::assertSame(10, $parsedResultSet->getAllResultCount());
    }

    /**
     * @test
     */
    public function parseWillSetMaximumScore()
    {
        $fakeResultSet = $this->getMockBuilder(SearchResultSet::class)->onlyMethods(['getResponse'])->getMock();

        $fakedSolrResponse = $this->getFixtureContentByName('fakeResponse.json');
        $fakeResponse = new ResponseAdapter($fakedSolrResponse);

        $fakeResultSet->expects(self::once())->method('getResponse')->willReturn($fakeResponse);
        $parsedResultSet = $this->parser->parse($fakeResultSet, true);
        self::assertSame(3.1, $parsedResultSet->getMaximumScore());
    }

    /**
     * @test
     */
    public function canParseReturnsFalseWhenGroupingIsEnabled()
    {
        $requestMock = $this->getDumbMock(SearchRequest::class);
        $requestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($this->configurationMock);
        $fakeResultSet = $this->getDumbMock(SearchResultSet::class);
        $fakeResultSet->expects(self::any())->method('getUsedSearchRequest')->willReturn($requestMock);

        $this->configurationMock->expects(self::once())->method('getSearchGrouping')->willReturn(true);
        self::assertFalse($this->parser->canParse($fakeResultSet));
    }

    /**
     * @test
     */
    public function canParseReturnsTrueWhenGroupingIsDisabled()
    {
        $requestMock = $this->getDumbMock(SearchRequest::class);
        $requestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($this->configurationMock);
        $fakeResultSet = $this->getDumbMock(SearchResultSet::class);
        $fakeResultSet->expects(self::any())->method('getUsedSearchRequest')->willReturn($requestMock);

        $this->configurationMock->expects(self::once())->method('getSearchGrouping')->willReturn(false);
        self::assertTrue($this->parser->canParse($fakeResultSet));
    }
}
