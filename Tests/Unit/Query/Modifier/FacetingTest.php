<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Query\Modifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Timo Schmidt <timo.schmidt@dkd.de>
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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetRegistry;
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
 * @author Timo Schmidt <timo.schmidt@dkd.de>
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

        $fakeObjectManager = $this->getMockBuilder(ObjectManager::class)->disableOriginalConstructor()->setMethods(['get'])->getMock();
        $fakeObjectManager->expects($this->any())->method('get')->will($this->returnCallback(function($className) {
            return new $className();
        }));

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
                'field' => 'type'
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue([]));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $expectedJsonFacet = '{"type":{"type":"terms","field":"type","limit":100,"mincount":1}}';
        $this->assertSame($expectedJsonFacet,  $queryParameter['json.facet'], 'Query string did not contain expected snipped');
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
                'sortBy' => 'index'
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue([]));
        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);

        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');
        $this->assertContains('"sort":"index"',  $queryParameter['json.facet'], 'Query string did not contain expected snipped');
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
                'sortBy' => 'count'
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue([]));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $this->assertContains('"sort":"count"',  $queryParameter['json.facet'], 'Query string did not contain expected snipped');
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
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue([]));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);
        $this->assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('type,color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
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
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue([]));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);
        $this->assertEmpty($jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEmpty($jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
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
                'keepAllOptionsOnSelection' => 1
            ],
            'color.' => [
                'field' => 'color',
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue([]));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $jsonData = \json_decode($queryParameter['json.facet']);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');
        $this->assertEquals('type',  $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('color',  $jsonData->color->field, 'Query string did not contain expected snipped');
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
                'keepAllOptionsOnSelection' => 1
            ],
            'color.' => [
                'field' => 'color',
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'),urlencode('type:product')]];

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);

        $this->assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('type', $jsonData->type->field, 'Did not build json field properly');

        $this->assertEquals('type,color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('color', $jsonData->color->field, 'Did not build json field properly');
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
                'keepAllOptionsOnSelection' => 1
            ],
            'color.' => [
                'field' => 'color',
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'),urlencode('type:product')]];

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);

        $this->assertEquals('type', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('type', $jsonData->type->field, 'Did not build json field properly');

        $this->assertEquals('color', $jsonData->color->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('color', $jsonData->color->field, 'Did not build json field properly');
    }

    /**
     *
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
            ]
        ];

        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'),urlencode('type:product')]];
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        $this->assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        $this->assertEquals('(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
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
                'keepAllOptionsOnSelection' => 1
            ],
            'color.' => [
                'field' => 'color',
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'),urlencode('type:product')]];
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        $this->assertEquals('(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        $this->assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
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
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('color:red'),urlencode('type:product')]];

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        //do we have a filter query for both present?
        $this->assertEquals('{!tag=color}(color:"red")', $queryParameter['fq'][0], 'Did not build filter query from color');
        $this->assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'][1], 'Did not build filter query from type');
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
                'addFieldAsTag' => 1
            ],
            'color.' => [
                'field' => 'color',
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        $fakeArguments = ['filter' => [urlencode('type:product')]];

        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);
        $this->assertContains('true',  $queryParameter['facet'], 'Query string did not contain expected snipped');

        $jsonData = \json_decode($queryParameter['json.facet']);
        $this->assertEquals('type,color', $jsonData->type->domain->excludeTags, 'Query string did not contain expected snipped');
        $this->assertEquals('{!tag=type}(type:"product")', $queryParameter['fq'], 'Did not build filter query from color');
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
                'something2%3AA%20B'
            ]
        ];

        $fakeConfigurationArray = [];
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['keepAllFacetsOnSelection'] = 1;
        $fakeConfigurationArray['plugin.']['tx_solr.']['search.']['faceting.']['facets.'] = [
            'something0.' => [
                'field' => 'something0'
            ],
            'something1.' => [
                'field' => 'something1'
            ],
            'something2.' => [
                'field' => 'something2'
            ]
        ];
        $fakeConfiguration = new TypoScriptConfiguration($fakeConfigurationArray);

        /* @var SearchRequest $fakeRequest */
        $fakeRequest = $this->getDumbMock(SearchRequest::class);
        $fakeRequest->expects($this->once())->method('getArguments')->will($this->returnValue($fakeArguments));
        $fakeRequest->expects($this->any())->method('getContextTypoScriptConfiguration')->will($this->returnValue($fakeConfiguration));

        $queryParameter = $this->getQueryParametersFromExecutedFacetingModifier($fakeConfiguration, $fakeRequest);

        $this->assertEquals('{!tag=something0}(something0:"A+B")', $queryParameter['fq'][0], 'Can handle plus as plus');
        $this->assertEquals('{!tag=something1}(something1:"A+B")', $queryParameter['fq'][1], 'Can handle %2B as plus');
        $this->assertEquals('{!tag=something2}(something2:"A B")', $queryParameter['fq'][2], 'Can handle %20 as space');
    }
}
