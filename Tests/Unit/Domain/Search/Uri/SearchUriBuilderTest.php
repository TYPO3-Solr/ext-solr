<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\Uri;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\Util;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchUriBuilderTest extends UnitTest
{
    /**
     * @var SearchUriBuilder
     */
    protected $searchUrlBuilder;

    /**
     * @var UriBuilder
     */
    protected $extBaseUriBuilderMock;

    /**
     * @var RoutingService
     */
    protected $routingServiceMock;

    /**
     * @return void
     */
    protected function setUp()
    {
        $this->extBaseUriBuilderMock = $this->getDumbMock(UriBuilder::class);
        $this->routingServiceMock = $this->getDumbMock(RoutingService::class);
        $eventDispatcherMock = $this->getDumbMock(EventDispatcher::class);
        $eventDispatcherMock->expects($this->any())->method('dispatch')->willReturnArgument(0);
        $this->searchUrlBuilder = new SearchUriBuilder();
        $this->searchUrlBuilder->injectUriBuilder($this->extBaseUriBuilderMock);
        $this->searchUrlBuilder->injectRoutingService($this->routingServiceMock);
        $this->searchUrlBuilder->injectEventDispatcher($eventDispatcherMock);
        $this->searchUrlBuilder->flushInMemoryCache();
    }

    /**
     * @test
     */
    public function addFacetLinkIsCalledWithSubstitutedArguments()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue([]));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(1));
        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0:foo###']]];

        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'foo', 'bar');
    }

    /**
     * @test
     */
    public function addFacetLinkWillAddAdditionalConfiguredArguments()
    {
        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0:option###']], 'foo' => '###foo###'];
        $linkBuilderResult = '/index.php?id=1&filter='.urlencode('###tx_solr:filter:0:option###').'&foo='.urlencode('###foo###');

        $this->extBaseUriBuilderMock->expects($this->once())
            ->method('setArguments')
            ->with($expectedArguments)
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())
            ->method('setUseCacheHash')
            ->with(false)
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())
            ->method('reset')
            ->with()
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())
            ->method('build')
            ->with()
            ->will($this->returnValue($linkBuilderResult));
        /* @var $configurationMock TypoScriptConfiguration */
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())
            ->method('getSearchPluginNamespace')
            ->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())
            ->method('getSearchFacetingFacetLinkUrlParametersAsArray')
            ->will($this->returnValue(['foo' => 'bar']));
        /*
         * Method 'reviseFilterVariables()' of class RoutingService used to remove the facet prefix within
         * the path segment
         * Since the whole class is a mock, the variables need to return by the mock.
         *
         * Note:
         * This unit test just test the arguments not the path information, the result of the logic within
         * method 'reviseFilterVariables()' will not modify this array!
         *
         * @see \ApacheSolrForTypo3\Solr\Routing\RoutingService::reviseFilterVariables
         */
        $this->routingServiceMock->expects($this->any())
            ->method('reviseFilterVariables')
            ->will($this->returnValue(['###tx_solr:filter:0:option###' => 'option%3Avalue', '###foo###' => 'bar']));
        $configurationMock->expects($this->once())
            ->method('getSearchTargetPage')
            ->will($this->returnValue(1));

        $previousRequest =  new SearchRequest([], 1, 0, $configurationMock);

        $linkBuilderResult = $this->searchUrlBuilder
            ->getAddFacetValueUri($previousRequest, 'option', 'value');

        $this->assertEquals('/index.php?id=1&filter=option%3Avalue&foo=bar', $linkBuilderResult);
    }

    /**
     * @test
     */
    public function setArgumentsIsOnlyCalledOnceEvenWhenMultipleFacetsGetRendered()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->will($this->returnValue([]));
        $configurationMock->expects($this->any())->method('getSearchTargetPage')->will($this->returnValue(1));

        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0:color###']]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue(urlencode('/index.php?id=1&tx_solr[filter][0]=###tx_solr:filter:0###')));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'green');

        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'blue');

        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'red');
    }

    /**
     * @test
     */
    public function targetPageUidIsPassedWhenSortingIsAdded()
    {
        $expectedArguments = ['tx_solr' => ['sort' => '###tx_solr:sort###']];
        $linkBuilderResult = '/index.php?id=1&' . urlencode('tx_solr[sort]') . '='.urlencode('###tx_solr:sort###');

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(4711));
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(1));

        /*
         * Method 'reviseFilterVariables()' of class RoutingService used to remove the facet prefix within
         * the path segment
         * Since the whole class is a mock, the variables need to return by the mock.
         *
         * Note:
         * This unit test just test the arguments not the path information, the result of the logic within
         * method 'reviseFilterVariables()' will not modify this array!
         *
         * @see \ApacheSolrForTypo3\Solr\Routing\RoutingService::reviseFilterVariables
         */
        $this->routingServiceMock->expects($this->any())
            ->method('reviseFilterVariables')
            ->will($this->returnValue(['###tx_solr:sort###' => 'title+desc']));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

            // we expect that the page uid from the configruation will be used to build the url with the uri builder
        $this->extBaseUriBuilderMock->expects($this->once())->method('setTargetPageUid')->with(4711)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue($linkBuilderResult));
        $result = $this->searchUrlBuilder->getSetSortingUri($previousRequest, 'title', 'desc');
        $this->assertEquals('/index.php?id=1&'  . urlencode('tx_solr[sort]') . '=' . urlencode('title desc'), $result);
    }

   /**
     * @test
     */
    public function canGetRemoveFacetOptionUri()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(1));

        $previousRequest =  new SearchRequest([
                    'tx_solr' => [
                        'filter' => [
                            'type:pages'
                        ]
                    ]
                ],
                0,
                0,
                $configurationMock
        );

        // we expect that the filters are empty after remove
        $expectedArguments = ['tx_solr' => ['filter' => []]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->searchUrlBuilder->getRemoveFacetValueUri($previousRequest, 'type', 'pages');
    }

    /**
     * @test
     */
    public function canGetRemoveFacetUri()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(1));

        $previousRequest =  new SearchRequest([
                    'tx_solr' => [
                        'filter' => [
                            'type:pages',
                            'type:tt_news',
                        ]
                    ]
                ],
                0,
                0,
                $configurationMock);

        // we expect that the filters are empty after remove
        //@todo we need to refactor the request in ext:solr to cleanup empty arguments completely to assert  $expectedArguments = []
        $expectedArguments = ['tx_solr' => ['filter' => []]];
        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->searchUrlBuilder->getRemoveFacetUri($previousRequest, 'type');
    }

    /**
     * When a page for a group was set, this should be resetted when a facet is selected.
     *
     * @test
     */
    public function addFacetUriRemovesPreviousGroupPage()
    {
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(1));

        $previousRequest =  new SearchRequest([
                'tx_solr' => [
                    'groupPage' => [
                        'typeGroup' => [
                            'pages' => 4
                        ]
                    ]
                ]
            ],
            0, 0, $configurationMock);

        $this->extBaseUriBuilderMock->expects($this->any())->method('setArguments')->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->any())->method('setUseCacheHash')->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->any())->method('build')->will($this->returnValue('/index.php?id=1&tx_solr[filter][0]=type:pages'));

        $uri = $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'type', 'pages');
        $this->assertSame('/index.php?id=1&tx_solr[filter][0]=type:pages', urldecode($uri), 'Unexpected uri generated');
    }

    /**
     * @test
     */
    public function canSetGroupPageForQueryGroup()
    {
        $expectedArguments = [
            'tx_solr' => [
                'groupPage' => [
                    'smallPidRange' => [
                        'pid0to5' => '###tx_solr:groupPage:smallPidRange:pid0to5###'
                    ]
                ]
            ]
        ];
        $givenTemplate = [
            'id' => 1,
            'tx_solr' => [
                'groupPage' => [
                    'smallPidRange' => [
                        'pid0to5' => '###tx_solr:groupPage:smallPidRange:pid0to5###'
                    ]
                ],
            ]
        ];
        $linkBuilderResult = '/index.php?' . http_build_query($givenTemplate);


        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(1));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

        $group = new Group('smallPidRange', 5);
        $groupItem = new GroupItem($group, 'pid:[0 to 5]', 12, 0, 32);

        $this->extBaseUriBuilderMock->expects($this->once())->method('setArguments')->with($expectedArguments)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue($linkBuilderResult));

        /*
         * Method 'reviseFilterVariables()' of class RoutingService used to remove the facet prefix within
         * the path segment
         * Since the whole class is a mock, the variables need to return by the mock.
         *
         * Note:
         * This unit test just test the arguments not the path information, the result of the logic within
         * method 'reviseFilterVariables()' will not modify this array!
         *
         * @see \ApacheSolrForTypo3\Solr\Routing\RoutingService::reviseFilterVariables
         */
        $this->routingServiceMock->expects($this->any())
            ->method('reviseFilterVariables')
            ->will($this->returnValue(['###tx_solr:groupPage:smallPidRange:pid0to5###' => '5']));
        $uri = $this->searchUrlBuilder->getResultGroupItemPageUri($previousRequest, $groupItem, 5);
        $this->assertContains(urlencode('tx_solr[groupPage][smallPidRange][pid0to5]') . '=5', $uri, 'Uri did not contain link segment for query group');
    }

    /*
     * Unit tests for router behaviour
     */

    /**
     * @test
     */
    public function siteConfigurationModifyUriTest()
    {
        $configuration = Yaml::parse($this->getFixtureContentByName('siteConfiguration.yaml'));
        $routingServiceMock = $this->getDumbMock(RoutingService::class);
        $routingServiceMock->expects($this->any())
            ->method('fetchEnhancerByPageUid')
            ->will($this->returnValue($configuration['routeEnhancers']['example']));
        $queryParameters = [
            'tx_solr' => [
                'filter' => [
                    'type:pages',
                    'color:green',
                    'color:red',
                    'color:yellow',
                    'taste:matcha',
                    'taste:sour',
                    'product:candy',
                    'product:sweets',
                ]
            ]
        ];
        $expectedQueryParameters = [
            'tx_solr' => [
                'filter' => [
                    '###tx_solr:filter:0:type###',
                    '###tx_solr:filter:1:color###',
                    '###tx_solr:filter:2:color###',
                    '###tx_solr:filter:3:color###',
                    '###tx_solr:filter:4:taste###',
                    '###tx_solr:filter:5:taste###',
                    '###tx_solr:filter:6:product###',
                    '###tx_solr:filter:7:product###',
                ]
            ]
        ];
        $linkBuilderResult = '/index.php?id=42&color=' . urlencode('green,red,yellow') .
            '&taste=' . urlencode('matcha,sour') .
            '&product=' . urlencode('candy,sweets');
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(42));

        $previousRequest =  new SearchRequest($queryParameters, 42, 0, $configurationMock);
        $this->extBaseUriBuilderMock->expects($this->any())->method('setArguments')->with($expectedQueryParameters)
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue($linkBuilderResult));
        $this->searchUrlBuilder->injectRoutingService($routingServiceMock);
        $uri = $this->searchUrlBuilder->getResultPageUri($previousRequest, 0);
        $this->assertEquals($linkBuilderResult, $uri);
    }

    /**
     * @test
     */
    public function siteConfigurationModifyUriKeepUnmappedFilterTest()
    {
        $configuration = Yaml::parse($this->getFixtureContentByName('siteConfiguration.yaml'));
        $routingServiceMock = $this->getDumbMock(RoutingService::class);
        $routingServiceMock->expects($this->any())
            ->method('fetchEnhancerByPageUid')
            ->will($this->returnValue($configuration['routeEnhancers']['example']));
        $queryParameters = [
            'tx_solr' => [
                'filter' => [
                    'type:pages',
                    'color:green',
                    'color:red',
                    'color:yellow',
                    'taste:matcha',
                    'taste:sour',
                    'product:candy',
                    'product:sweets',
                    'quantity:20'
                ]
            ]
        ];
        $expectedQueryParameters = [
            'tx_solr' => [
                'filter' => [
                    '###tx_solr:filter:0:type###',
                    '###tx_solr:filter:1:color###',
                    '###tx_solr:filter:2:color###',
                    '###tx_solr:filter:3:color###',
                    '###tx_solr:filter:4:taste###',
                    '###tx_solr:filter:5:taste###',
                    '###tx_solr:filter:6:product###',
                    '###tx_solr:filter:7:product###',
                    '###tx_solr:filter:8:quantity###',
                ]
            ]
        ];
        $linkBuilderResult = '/index.php?id=42&color=' . urlencode('green,red,yellow') .
            '&taste=' . urlencode('matcha,sour') .
            '&product=' . urlencode('candy,sweets') .
            '&' . urlencode('tx_solr[filter][0]') .'=' . urlencode('quantity:20');
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->any())->method('getSearchPluginNamespace')->will($this->returnValue('tx_solr'));
        $configurationMock->expects($this->once())->method('getSearchTargetPage')->will($this->returnValue(42));

        $previousRequest =  new SearchRequest($queryParameters, 42, 0, $configurationMock);
        $this->extBaseUriBuilderMock->expects($this->any())->method('setArguments')->with($expectedQueryParameters)
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('setUseCacheHash')->with(false)
            ->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('reset')->with()->will($this->returnValue($this->extBaseUriBuilderMock));
        $this->extBaseUriBuilderMock->expects($this->once())->method('build')->will($this->returnValue($linkBuilderResult));
        $this->searchUrlBuilder->injectRoutingService($routingServiceMock);
        $uri = $this->searchUrlBuilder->getResultPageUri($previousRequest, 0);
        $this->assertEquals($linkBuilderResult, $uri);
    }
}
