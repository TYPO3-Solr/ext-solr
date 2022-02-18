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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Query\Modifier;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\UrlFacetContainer;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Query\Modifier\Faceting;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Solarium\QueryType\Select\RequestBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Tests the ApacheSolrForTypo3\Solr\Query\Modifier\Faceting class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class FacetingTest extends UnitTest
{
    /**
     * @param TypoScriptConfiguration $fakeConfiguration
     * @param SearchRequest $fakeSearchRequest
     * @return array
     */
    private function getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, SearchRequest $fakeSearchRequest)
    {
        $fakeObjectManager = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
        $fakeObjectManager->expects(self::any())->method('get')->willReturnCallback(function ($className) {
            return new $className();
        });

        $facetRegistry = new FacetRegistry();
        // @extensionScannerIgnoreLine
        $facetRegistry->injectObjectManager($fakeObjectManager);

        $solrLogManagerMock = $this->getDumbMock(SolrLogManager::class);

        /** @var $query \ApacheSolrForTypo3\Solr\Domain\Search\Query\Query */
        $queryBuilder = new QueryBuilder($fakeConfiguration, $solrLogManagerMock);
        $query = $queryBuilder->buildSearchQuery('test');

        /** @var $facetModifier \ApacheSolrForTypo3\Solr\Query\Modifier\Faceting */
        $facetModifier = GeneralUtility::makeInstance(Faceting::class, $facetRegistry);
        $facetModifier->setSearchRequest($fakeSearchRequest);
        $facetModifier->modifyQuery($query);

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
     *
     * @test
     */
    public function testCanAddASimpleFacet()
    {
        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'type.' => [
                'field' => 'type',
            ],
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
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
     *
     * @test
     */
    public function testCanAddSortByIndexArgument()
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
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
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
     *
     * @test
     */
    public function testCanAddSortByCountArgument()
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
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
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
     *
     * @test
     */
    public function testCanHandleKeepAllFacetsOnSelectionOnAllFacetWhenGloballyConfigured()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);
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
     *
     * @test
     */
    public function testExcludeTagsAreEmptyWhenKeepAllFacetsOnSelectionIsNotSet()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);
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
     *
     * @test
     */
    public function testCanHandleKeepAllOptionsOnSelectionForASingleFacet()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn([]);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $jsonData = \json_decode($queryParameter['json.facet']);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');
        self::assertEquals('type', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('color', $jsonData->color->field, 'Query string did not contain expected snipped');
    }

    /**
     * @test
     */
    public function testCanHandleCombinationOfKeepAllFacetsOnSelectionAndKeepAllOptionsOnSelection()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);

        self::assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('type', $jsonData->type->field, 'Did not build json field properly');

        self::assertEquals('type,color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('color', $jsonData->color->field, 'Did not build json field properly');
    }

    /**
     * @test
     */
    public function testCanHandleCombinationOfKeepAllFacetsOnSelectionAndKeepAllOptionsOnSelectionAndCountAllFacetsForSelection()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);

        self::assertEquals('type', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('type', $jsonData->type->field, 'Did not build json field properly');

        self::assertEquals('color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('color', $jsonData->color->field, 'Did not build json field properly');
    }

    /**
     * @test
     */
    public function testCanAddQueryFilters()
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
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        self::assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        self::assertEquals('(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    /**
     * @test
     */
    public function testCanAddQueryFiltersWithKeepAllOptionsOnSelectionFacet()
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
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        self::assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        self::assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    /**
     * @test
     */
    public function testCanAddQueryFiltersWithGlobalKeepAllOptionsOnSelection()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        self::assertEquals('{!tag=color}(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        self::assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
    }

    /**
     * @test
     */
    public function testCanAddExcludeTagWithAdditionalExcludeTagConfiguration()
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

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        self::assertStringContainsString('true', $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);
        self::assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        self::assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'], 'Did not build filter query from color');
    }

    /**
     * @test
     */
    public function testCanAddQueryFiltersContainingPlusSign()
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

        /* @var SearchRequest $fakeRequest */
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects(self::once())->method('getArguments')->willReturn($fakeArguments);
        $fakeRequest->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($fakeConfiguration);

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);

        self::assertEquals('{!tag=something0}(something0:"A+B")', $queryParameter['fq'][0], 'Can handle plus as plus');
        self::assertEquals('{!tag=something1}(something1:"A+B")', $queryParameter['fq'][1], 'Can handle %2B as plus');
        self::assertEquals('{!tag=something2}(something2:"A B")', $queryParameter['fq'][2], 'Can handle %20 as space');
    }

    /**
     * @test
     */
    public function getFiltersByFacetNameCanHandleAssocUrlParameterStyle()
    {
        $facetingModifierStub = new class($this->getDumbMock(FacetRegistry::class)) extends Faceting {
            public function callGetFiltersByFacetName(array $resultParameters, array $allFacets): array
            {
                return parent::getFiltersByFacetName($resultParameters, $allFacets);
            }
        };

        $typoScriptConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $typoScriptConfigurationMock->expects(self::once())
            ->method('getSearchFacetingUrlParameterStyle')
            ->willReturn(UrlFacetContainer::PARAMETER_STYLE_ASSOC);
        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects(self::once())
            ->method('getContextTypoScriptConfiguration')
            ->willReturn($typoScriptConfigurationMock);

        $facetingModifierStub->setSearchRequest($searchRequestMock);

        self::assertEquals(
            [
                'age' => [0 => 'week'],
                'type' => [0 => 'tx_news_domain_model_news'],
            ],
            $facetingModifierStub->callGetFiltersByFacetName(
                [
                    'filter' => [
                        'age:week' => '1',
                        'type:tx_news_domain_model_news' => '1',
                    ],
                ],
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
