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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DefaultResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\Component\Grouping;

/**
 * Unit test case for the SearchResult.
 */
class DefaultParserTest extends SetUpUnitTestCase
{
    protected TypoScriptConfiguration|MockObject $configurationMock;
    protected DefaultResultParser $parser;

    protected function setUp(): void
    {
        $this->configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $this->parser = new DefaultResultParser();
        parent::setUp();
    }

    /**
     * @test
     */
    public function parseWillCreateResultCollectionFromSolrResponse(): void
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
    public function returnsResultSetWithResultCount(): void
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
    public function parseWillSetMaximumScore(): void
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
    public function canParseReturnsFalseWhenGroupingIsEnabled(): void
    {
        $requestMock = $this->createMock(SearchRequest::class);
        $requestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($this->configurationMock);
        $groupingMock = $this->createMock(Grouping::class);
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects(self::any())->method('getComponent')->willReturn($groupingMock);
        $fakeResultSet = $this->createMock(SearchResultSet::class);
        $fakeResultSet->expects(self::any())->method('getUsedSearchRequest')->willReturn($requestMock);
        $fakeResultSet->expects(self::any())->method('getUsedQuery')->willReturn($queryMock);

        $this->configurationMock->expects(self::once())->method('getIsSearchGroupingEnabled')->willReturn(true);
        self::assertFalse($this->parser->canParse($fakeResultSet));
    }

    /**
     * @test
     */
    public function canParseReturnsTrueWhenGroupingIsDisabled(): void
    {
        $requestMock = $this->createMock(SearchRequest::class);
        $requestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($this->configurationMock);
        $queryMock = $this->createMock(Query::class);
        $queryMock->expects(self::any())->method('getComponent')->willReturn(null);
        $fakeResultSet = $this->createMock(SearchResultSet::class);
        $fakeResultSet->expects(self::any())->method('getUsedSearchRequest')->willReturn($requestMock);
        $fakeResultSet->expects(self::any())->method('getUsedQuery')->willReturn($queryMock);

        self::assertTrue($this->parser->canParse($fakeResultSet));
    }
}
