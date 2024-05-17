<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\FacetingComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Solarium\QueryType\Select\RequestBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query\Modifier\Faceting class
 */
class FacetingComponentTest extends SetUpUnitTestCase
{
    private function getQueryParametersFromExecutedFacetingModifier(
        TypoScriptConfiguration $fakeConfiguration,
        SearchRequest $fakeSearchRequest
    ): array {
        $facetRegistry = new FacetRegistry();

        /** @var SolrLogManager|MockObject $solrLogManagerMock */
        $solrLogManagerMock = $this->createMock(SolrLogManager::class);

        $queryBuilder = new QueryBuilder(
            $fakeConfiguration,
            $solrLogManagerMock,
            $this->createMock(SiteHashService::class)
        );
        $query = $queryBuilder->buildSearchQuery('test');

        GeneralUtility::addInstance(SiteHashService::class, $this->createMock(SiteHashService::class));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $fakeSearchRequest,
            $this->createMock(Search::class),
            $fakeConfiguration
        );
        $subject = new FacetingComponent($facetRegistry);
        $subject->__invoke($event);
        $query = $event->getQuery();
        $requestBuilder = new RequestBuilder();
        $request = $requestBuilder->build($query);
        return $request->getParams();
    }

    /**
     * Checks if the faceting modifier can add a simple facet on the field type.
     *
     *  facets {
     *     type {
     *        field = type
     *     }
     *  }
     */
    #[Test]
    public function testCanAddASimpleFacet(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $expectedJsonFacet = '{"type":{"type":"terms","field":"type","limit":100,"mincount":1}}';
        self::assertSame($expectedJsonFacet, $queryParameter['json.facet'], 'Query string did not contain expected snipped');
    }

    /**
     * Checks if the faceting modifier can add a simple facet with a sortBy property with the value index.
     *
     *  facets {
     *     type {
     *        field = type
     *        sortBy = index
     *     }
     *  }
     */
    #[Test]
    public function testCanAddSortByIndexArgument(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'sortBy' => 'index',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);

        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');
        self::assertStringContainsString('"sort":"index"', $queryParameter['json.facet'], 'Query string did not contain expected snipped');
    }

    /**
     * Checks if the faceting modifier can add a simple facet with a sortBy property with the value count.
     *
     *  facets {
     *     type {
     *        field = type
     *        sortBy = count
     *     }
     *  }
     */
    #[Test]
    public function testCanAddSortByCountArgument(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'sortBy' => 'count',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        self::assertStringContainsString('"sort":"count"', $queryParameter['json.facet'], 'Query string did not contain expected snipped');
    }

    /**
     * Checks when keepAllOptionsOnSelection is configured globally that {!ex=type,color} will be added
     * to the query.
     *
     * faceting {
     *    keepAllFacetsOnSelection = 1
     *    facets {
     *       type {
     *          field = type
     *       }
     *       color {
     *          field = color
     *       }
     *    }
     * }
     */
    #[Test]
    public function testCanHandleKeepAllFacetsOnSelectionOnAllFacetWhenGloballyConfigured(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = json_decode($queryParameter['json.facet']);
        self::assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('type,color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
    }

    /**
     * Whe nothing is set, no exclude tags should be set.
     *
     * faceting {
     *    facets {
     *       type {
     *          field = type
     *       }
     *       color {
     *          field = color
     *       }
     *    }
     * }
     */
    #[Test]
    public function testExcludeTagsAreEmptyWhenKeepAllFacetsOnSelectionIsNotSet(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = json_decode($queryParameter['json.facet']);
        self::assertEmpty(($jsonData->type->domain->excludeTags ?? ''), 'Query string did not contain expected snipped');
        self::assertEmpty(($jsonData->color->domain->excludeTags ?? ''), 'Query string did not contain expected snipped');
    }

    /**
     * Checks when keepAllOptionsOnSelection is configured globally that {!ex=type,color} will be added
     * to the query.
     *
     * faceting {
     *    facets {
     *       type {
     *          field = type
     *          keepAllOptionsOnSelection = 1
     *       }
     *       color {
     *          field = color
     *       }
     *    }
     * }
     */
    #[Test]
    public function testCanHandleKeepAllOptionsOnSelectionForASingleFacet(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'keepAllOptionsOnSelection' => 1,
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $jsonData = json_decode($queryParameter['json.facet']);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');
        self::assertEquals('type', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('color', $jsonData->color->field, 'Query string did not contain expected snipped');
    }

    #[Test]
    public function testCanHandleCombinationOfKeepAllFacetsOnSelectionAndKeepAllOptionsOnSelection(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'keepAllOptionsOnSelection' => 1,
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'), urlencode('type:product')]];

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = json_decode($queryParameter['json.facet']);

        self::assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('type', $jsonData->type->field, 'Did not build json field properly');

        self::assertEquals('type,color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('color', $jsonData->color->field, 'Did not build json field properly');
    }

    #[Test]
    public function testCanHandleCombinationOfKeepAllFacetsOnSelectionAndKeepAllOptionsOnSelectionAndCountAllFacetsForSelection(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['countAllFacetsForSelection'] = 1;

        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'keepAllOptionsOnSelection' => 1,
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'), urlencode('type:product')]];

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = json_decode($queryParameter['json.facet']);

        self::assertEquals('type', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('type', $jsonData->type->field, 'Did not build json field properly');

        self::assertEquals('color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('color', $jsonData->color->field, 'Did not build json field properly');
    }

    #[Test]
    public function testCanAddQueryFilters(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'), urlencode('type:product')]];

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        self::assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        self::assertEquals('(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    #[Test]
    public function testCanAddQueryFiltersWithKeepAllOptionsOnSelectionFacet(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'keepAllOptionsOnSelection' => 1,
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'), urlencode('type:product')]];

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        self::assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        self::assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    #[Test]
    public function testCanAddQueryFiltersWithGlobalKeepAllOptionsOnSelection(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'), urlencode('type:product')]];

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        self::assertEquals('{!tag=color}(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        self::assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    #[Test]
    public function testCanAddExcludeTagWithAdditionalExcludeTagConfiguration(): void
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
                'additionalExcludeTags' => 'type,color',
                'addFieldAsTag' => 1,
            ],
            'color.' => [
                'field' => 'color',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('type:product')]];

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = json_decode($queryParameter['json.facet']);
        self::assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'], 'Did not build filter query from color');
    }

    #[Test]
    public function testCanAddQueryFiltersContainingPlusSign(): void
    {
        $fakeArguments = [
            'filter' => [
                'something0%3AA+B',
                'something1%3AA%2BB',
                'something2%3AA%20B',
            ],
        ];

        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'something0.' => [
                'field' => 'something0',
            ],
            'something1.' => [
                'field' => 'something1',
            ],
            'something2.' => [
                'field' => 'something2',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /** @var SearchRequest|MockObject $fakeRequest */
        $fakeRequest = $this->createMock(SearchRequest::class);
        $fakeRequest->expects(self::exactly(2))->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);

        self::assertEquals('{!tag=something0}(something0:"A+B")', $queryParameter['fq'][0], 'Can handle plus as plus');
        self::assertEquals('{!tag=something1}(something1:"A+B")', $queryParameter['fq'][1], 'Can handle %2B as plus');
        self::assertEquals('{!tag=something2}(something2:"A B")', $queryParameter['fq'][2], 'Can handle %20 as space');
    }

    #[Test]
    public function getFiltersByFacetNameCanHandleAssocUrlParameterStyle(): void
    {
        $facetingModifierStub = new class ($this->createMock(FacetRegistry::class)) extends FacetingComponent {
            public function callGetFiltersByFacetName(SearchRequest $searchRequest, array $allFacets): array
            {
                return parent::getFiltersByFacetName($searchRequest, $allFacets);
            }
        };

        $typoScriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $typoScriptConfigurationMock->expects(self::once())
            ->method('getSearchFacetingUrlParameterStyle')
            ->willReturn(UrlFacetContainer::PARAMETER_STYLE_ASSOC);

        /** @var SearchRequest|MockObject $searchRequestMock */
        $searchRequestMock = $this->createMock(SearchRequest::class);
        $searchRequestMock->expects(self::once())->method('getArguments')->willReturn([
            'filter' => [
                'age:week' => '1',
                'type:tx_news_domain_model_news' => '1',
            ],
        ]);
        $searchRequestMock->expects(self::once())
            ->method('getContextTypoScriptConfiguration')
            ->willReturn($typoScriptConfigurationMock);

        self::assertEquals(
            [
                'age' => [0 => 'week'],
                'type' => [0 => 'tx_news_domain_model_news'],
            ],
            $facetingModifierStub->callGetFiltersByFacetName(
                $searchRequestMock,
                [
                    'type.' => [
                        'label' => 'Content Type',
                        'field' => 'type',
                    ],
                    'age.' => [
                        'label' => 'Age',
                        'field' => 'created',
                        'type' => 'queryGroup',
                        'queryGroup.' => [
                            'week.' => [
                                'query' => '[NOW/DAY-7DAYS TO *]',
                            ],
                        ],
                    ],
                ]
            ),
            'The assoc parameters/keys for parameters of selected facets are not as expected.' . PHP_EOL
            . 'Probably they are not delegated to Apache Solr query, which leads to a non functional faceting.'
        );
    }
}
