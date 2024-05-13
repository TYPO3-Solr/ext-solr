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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\SetUpFacetParser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class QueryGroupFacetParserTest
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @author Frans Saris <frans@beech.it>
 */
class QueryGroupFacetParserTest extends SetUpFacetParser
{
    protected function initializeSearchResultSetFromFakeResponse(string $fixtureFile, array $facetConfiguration, array $activeFilters = []): SearchResultSet
    {
        $fakeResponseJson = self::getFixtureContentByName($fixtureFile);
        $searchRequestMock = $this->createMock(SearchRequest::class);
        $fakeResponse = new ResponseAdapter($fakeResponseJson);

        $searchResultSet = new SearchResultSet();
        $searchResultSet->setUsedSearchRequest($searchRequestMock);
        $searchResultSet->setResponse($fakeResponse);

        $configuration = [];
        $configuration['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = $facetConfiguration;
        $typoScriptConfiguration = new TypoScriptConfiguration($configuration);
        $searchRequestMock->expects(self::any())
            ->method('getContextTypoScriptConfiguration')
            ->willReturn($typoScriptConfiguration);

        $activeUrlFacets = new UrlFacetContainer(
            new ArrayAccessor([ 'tx_solr' => ['filter' => $activeFilters] ])
        );

        $searchRequestMock->expects(self::any())
            ->method('getActiveFacetNames')
            ->willReturnCallback(function() use ($activeUrlFacets) {
                return $activeUrlFacets->getActiveFacetNames();
            });

        $searchRequestMock->expects(self::any())
            ->method('getHasFacetValue')
            ->willReturnCallback(function(string $facetName, $facetValue) use ($activeUrlFacets) {
                return $activeUrlFacets->hasFacetValue($facetName, $facetValue);
            });

        return $searchResultSet;
    }

    #[Test]
    public function facetIsCreated(): void
    {
        $facetConfiguration = [
            'age.' => [
                'type' => 'queryGroup',
                'label' => 'Age',
                'field' => 'created',
                'queryGroup.' => [
                    'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                    'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                    'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                    'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                    'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                ],
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_query_fields_facets_and_used_facet.json',
            $facetConfiguration
        );
        $parser = $this->getInitializedParser(QueryGroupFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'age', $facetConfiguration['age.']);

        self::assertInstanceOf(QueryGroupFacet::class, $facet);
    }

    #[Test]
    public function facetIsNotActive(): void
    {
        $facetConfiguration = [
            'age.' => [
                'type' => 'queryGroup',
                'label' => 'Age',
                'field' => 'created',
                'queryGroup.' => [
                    'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                    'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                    'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                    'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                    'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                ],
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_query_fields_facets_and_used_facet.json',
            $facetConfiguration
        );

        $parser = $this->getInitializedParser(QueryGroupFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'age', $facetConfiguration['age.']);

        self::assertFalse($facet->getIsUsed());
    }

    #[Test]
    public function facetIsActive(): void
    {
        $facetConfiguration = [
            'age.' => [
                'type' => 'queryGroup',
                'label' => 'Age',
                'field' => 'created',
                'queryGroup.' => [
                    'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                    'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                    'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                    'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                    'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                ],
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_query_fields_facets_and_used_facet.json',
            $facetConfiguration,
            ['age:week']
        );
        $parser = $this->getInitializedParser(QueryGroupFacetParser::class);
        $facet = $parser->parse($searchResultSet, 'age', $facetConfiguration['age.']);

        self::assertTrue($facet->getIsUsed());
    }

    #[Test]
    public function optionIsActive(): void
    {
        $facetConfiguration = [
            'age.' => [
                'type' => 'queryGroup',
                'label' => 'Age',
                'field' => 'created',
                'queryGroup.' => [
                    'week' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                    'month' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                    'halfYear' => ['query' => '[NOW/DAY-6MONTHS TO NOW/DAY-1MONTH]'],
                    'year' => ['query' => '[NOW/DAY-1YEAR TO NOW/DAY-6MONTHS]'],
                    'old' => ['query' => '[* TO NOW/DAY-1YEAR]'],
                ],
            ],
        ];

        $searchResultSet = $this->initializeSearchResultSetFromFakeResponse(
            'fake_solr_response_with_query_fields_facets_and_used_facet.json',
            $facetConfiguration,
            ['age:week']
        );
        $parser = $this->getInitializedParser(QueryGroupFacetParser::class);
        /** @var QueryGroupFacet $facet */
        $facet = $parser->parse($searchResultSet, 'age', $facetConfiguration['age.']);

        foreach ($facet->getOptions() as $option) {
            if ($option->getValue() === 'week') {
                self::assertTrue($option->getSelected(), 'Option ' . $option->getValue() . ' isn\'t active');
            } else {
                self::assertFalse((bool)$option->getSelected(), 'Option ' . $option->getValue() . ' is active but was not expected to be');
            }
        }
    }
}
