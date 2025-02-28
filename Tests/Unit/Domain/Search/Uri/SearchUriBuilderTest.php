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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Uri;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Search\Uri\SearchUriBuilder;
use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * Unit test case for the ObjectReconstitutionProcessor.
 */
class SearchUriBuilderTest extends SetUpUnitTestCase
{
    protected SearchUriBuilder|MockObject $searchUrlBuilder;
    protected UriBuilder|MockObject $extBaseUriBuilderMock;
    protected RoutingService|MockObject $routingServiceMock;

    protected function setUp(): void
    {
        $this->extBaseUriBuilderMock = $this->createMock(UriBuilder::class);
        $this->routingServiceMock = $this->createMock(RoutingService::class);
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(self::any())->method('dispatch')->willReturnArgument(0);
        $this->searchUrlBuilder = new SearchUriBuilder();
        $this->searchUrlBuilder->injectUriBuilder($this->extBaseUriBuilderMock);
        $this->searchUrlBuilder->injectRoutingService($this->routingServiceMock);
        $this->searchUrlBuilder->injectEventDispatcher($eventDispatcherMock);
        $this->searchUrlBuilder->flushInMemoryCache();
        parent::setUp();
    }

    #[Test]
    public function addFacetLinkIsCalledWithSubstitutedArguments(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->willReturn([]);
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(1);
        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0:foo###']]];

        $this->extBaseUriBuilderMock->expects(self::once())->method('setArguments')->with($expectedArguments)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'foo', 'bar');
    }

    #[Test]
    public function addFacetLinkWillAddAdditionalConfiguredArguments(): void
    {
        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0:option###']], 'foo' => '###foo###'];
        $linkBuilderResult = '/index.php?id=1&filter=' . urlencode('###tx_solr:filter:0:option###') . '&foo=' . urlencode('###foo###');

        $this->extBaseUriBuilderMock->expects(self::once())
            ->method('setArguments')
            ->with($expectedArguments)
            ->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())
            ->method('reset')
            ->with()
            ->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())
            ->method('build')
            ->with()
            ->willReturn($linkBuilderResult);
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())
            ->method('getSearchPluginNamespace')
            ->willReturn('tx_solr');
        $configurationMock->expects(self::once())
            ->method('getSearchFacetingFacetLinkUrlParametersAsArray')
            ->willReturn(['foo' => 'bar']);
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
        $this->routingServiceMock->expects(self::any())
            ->method('reviseFilterVariables')
            ->willReturn(['###tx_solr:filter:0:option###' => 'option%3Avalue', '###foo###' => 'bar']);
        $configurationMock->expects(self::once())
            ->method('getSearchTargetPage')
            ->willReturn(1);

        $previousRequest =  new SearchRequest([], 1, 0, $configurationMock);

        $linkBuilderResult = $this->searchUrlBuilder
            ->getAddFacetValueUri($previousRequest, 'option', 'value');

        self::assertEquals('/index.php?id=1&filter=option%3Avalue&foo=bar', $linkBuilderResult);
    }

    #[Test]
    public function setArgumentsIsOnlyCalledOnceEvenWhenMultipleFacetsGetRendered(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetLinkUrlParametersAsArray')->willReturn([]);
        $configurationMock->expects(self::any())->method('getSearchTargetPage')->willReturn(1);

        $expectedArguments = ['tx_solr' => ['filter' => ['###tx_solr:filter:0:color###']]];
        $this->extBaseUriBuilderMock->expects(self::once())->method('setArguments')->with($expectedArguments)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('build')->willReturn(urlencode('/index.php?id=1&tx_solr[filter][0]=###tx_solr:filter:0###'));

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);
        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'green');

        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'blue');

        $previousRequest->removeAllFacets();
        $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'color', 'red');
    }

    #[Test]
    public function targetPageUidIsPassedWhenSortingIsAdded(): void
    {
        $expectedArguments = ['tx_solr' => ['sort' => '###tx_solr:sort###']];
        $linkBuilderResult = '/index.php?id=1&' . urlencode('tx_solr[sort]') . '=' . urlencode('###tx_solr:sort###');

        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(4711);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(1);

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
        $this->routingServiceMock->expects(self::any())
            ->method('reviseFilterVariables')
            ->willReturn(['###tx_solr:sort###' => 'title+desc']);

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

        // we expect that the page uid from the configruation will be used to build the url with the uri builder
        $this->extBaseUriBuilderMock->expects(self::once())->method('setTargetPageUid')->with(4711)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('setArguments')->with($expectedArguments)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('build')->willReturn($linkBuilderResult);
        $result = $this->searchUrlBuilder->getSetSortingUri($previousRequest, 'title', 'desc');
        self::assertEquals('/index.php?id=1&' . urlencode('tx_solr[sort]') . '=' . urlencode('title desc'), $result);
    }

    #[Test]
    public function canGetRemoveFacetOptionUri(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(1);

        $previousRequest =  new SearchRequest(
            [
                'tx_solr' => [
                    'filter' => [
                        'type:pages',
                    ],
                ],
            ],
            0,
            0,
            $configurationMock
        );

        // we expect that the filters are empty after remove
        $expectedArguments = ['tx_solr' => ['filter' => []]];
        $this->extBaseUriBuilderMock->expects(self::once())->method('setArguments')->with($expectedArguments)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->searchUrlBuilder->getRemoveFacetValueUri($previousRequest, 'type', 'pages');
    }

    #[Test]
    public function canGetRemoveFacetUri(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(1);

        $previousRequest =  new SearchRequest(
            [
                'tx_solr' => [
                    'filter' => [
                        'type:pages',
                        'type:tt_news',
                    ],
                ],
            ],
            0,
            0,
            $configurationMock
        );

        // we expect that the filters are empty after remove
        //@todo we need to refactor the request in ext:solr to cleanup empty arguments completely to assert  $expectedArguments = []
        $expectedArguments = ['tx_solr' => ['filter' => []]];
        $this->extBaseUriBuilderMock->expects(self::once())->method('setArguments')->with($expectedArguments)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->searchUrlBuilder->getRemoveFacetUri($previousRequest, 'type');
    }

    /**
     * When a page for a group was set, this should be resetted when a facet is selected.
     */
    #[Test]
    public function addFacetUriRemovesPreviousGroupPage(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())
            ->method('getSearchPluginNamespace')
            ->willReturn('tx_solr');
        $configurationMock->expects(self::once())
            ->method('getSearchTargetPage')
            ->willReturn(1);
        $configurationMock->expects(self::any())
            ->method('getSearchFacetingFacetLinkUrlParametersAsArray')
            ->willReturn([]);

        $previousRequest =  new SearchRequest(
            [
                'tx_solr' => [
                    'groupPage' => [
                        'typeGroup' => [
                            'pages' => 4,
                        ],
                    ],
                ],
            ],
            0,
            0,
            $configurationMock
        );

        $this->extBaseUriBuilderMock->expects(self::any())->method('setArguments')->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::any())->method('build')->willReturn('/index.php?id=1&tx_solr[filter][0]=type:pages');

        $uri = $this->searchUrlBuilder->getAddFacetValueUri($previousRequest, 'type', 'pages');
        self::assertSame('/index.php?id=1&tx_solr[filter][0]=type:pages', urldecode($uri), 'Unexpected uri generated');
    }

    #[Test]
    public function canSetGroupPageForQueryGroup(): void
    {
        $expectedArguments = [
            'tx_solr' => [
                'groupPage' => [
                    'smallPidRange' => [
                        'pid0to5' => '###tx_solr:groupPage:smallPidRange:pid0to5###',
                    ],
                ],
            ],
        ];
        $givenTemplate = [
            'id' => 1,
            'tx_solr' => [
                'groupPage' => [
                    'smallPidRange' => [
                        'pid0to5' => '###tx_solr:groupPage:smallPidRange:pid0to5###',
                    ],
                ],
            ],
        ];
        $linkBuilderResult = '/index.php?' . http_build_query($givenTemplate);

        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(1);

        $previousRequest =  new SearchRequest([], 0, 0, $configurationMock);

        $group = new Group('smallPidRange', 5);
        $groupItem = new GroupItem(
            $group,
            'pid:[0 to 5]',
            12,
            0,
            32,
            $previousRequest
        );

        $this->extBaseUriBuilderMock->expects(self::once())->method('setArguments')->with($expectedArguments)->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('build')->willReturn($linkBuilderResult);

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
        $this->routingServiceMock->expects(self::any())
            ->method('reviseFilterVariables')
            ->willReturn(['###tx_solr:groupPage:smallPidRange:pid0to5###' => '5']);
        $uri = $this->searchUrlBuilder->getResultGroupItemPageUri($previousRequest, $groupItem, 5);
        self::assertStringContainsString(urlencode('tx_solr[groupPage][smallPidRange][pid0to5]') . '=5', $uri, 'Uri did not contain link segment for query group');
    }

    /*
     * Unit tests for router behaviour
     */
    #[Test]
    public function siteConfigurationModifyUriTest(): void
    {
        $configuration = Yaml::parse(self::getFixtureContentByName('siteConfiguration.yaml'));
        $routingServiceMock = $this->createMock(RoutingService::class);
        $routingServiceMock->expects(self::any())
            ->method('fetchEnhancerByPageUid')
            ->willReturn($configuration['routeEnhancers']['example']);
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
                ],
            ],
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
                ],
            ],
        ];
        $linkBuilderResult = '/index.php?id=42&color=' . urlencode('green,red,yellow') .
            '&taste=' . urlencode('matcha,sour') .
            '&product=' . urlencode('candy,sweets');
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(42);

        $previousRequest =  new SearchRequest($queryParameters, 42, 0, $configurationMock);
        $this->extBaseUriBuilderMock->expects(self::any())->method('setArguments')->with($expectedQueryParameters)
            ->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('build')->willReturn($linkBuilderResult);
        $this->searchUrlBuilder->injectRoutingService($routingServiceMock);
        $uri = $this->searchUrlBuilder->getResultPageUri($previousRequest, 0);
        self::assertEquals($linkBuilderResult, $uri);
    }

    #[Test]
    public function siteConfigurationModifyUriKeepUnmappedFilterTest(): void
    {
        $configuration = Yaml::parse(self::getFixtureContentByName('siteConfiguration.yaml'));
        $routingServiceMock = $this->createMock(RoutingService::class);
        $routingServiceMock->expects(self::any())
            ->method('fetchEnhancerByPageUid')
            ->willReturn($configuration['routeEnhancers']['example']);
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
                    'quantity:20',
                ],
            ],
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
                ],
            ],
        ];
        $linkBuilderResult = '/index.php?id=42&color=' . urlencode('green,red,yellow') .
            '&taste=' . urlencode('matcha,sour') .
            '&product=' . urlencode('candy,sweets') .
            '&' . urlencode('tx_solr[filter][0]') . '=' . urlencode('quantity:20');
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(42);

        $previousRequest =  new SearchRequest($queryParameters, 42, 0, $configurationMock);
        $this->extBaseUriBuilderMock->expects(self::any())->method('setArguments')->with($expectedQueryParameters)
            ->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $this->extBaseUriBuilderMock->expects(self::once())->method('build')->willReturn($linkBuilderResult);
        $this->searchUrlBuilder->injectRoutingService($routingServiceMock);
        $uri = $this->searchUrlBuilder->getResultPageUri($previousRequest, 0);
        self::assertEquals($linkBuilderResult, $uri);
    }

    #[Test]
    public function uriErrorsResultInNonMappedProcessing(): void
    {
        $configuration = Yaml::parse(self::getFixtureContentByName('siteConfiguration.yaml'));
        $routingServiceMock = $this->createMock(RoutingService::class);
        $routingServiceMock->expects(self::any())
            ->method('fetchEnhancerByPageUid')
            ->willReturn($configuration['routeEnhancers']['example']);
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
                    'quantity:20',
                ],
            ],
        ];
        $subsitutedQueryParameters = [
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
                ],
            ],
        ];
        $linkBuilderResult = '/index.php?id=42&color=' . urlencode('green,red,yellow') .
            '&taste=' . urlencode('matcha,sour') .
            '&product=' . urlencode('candy,sweets') .
            '&' . urlencode('tx_solr[filter][0]') . '=' . urlencode('quantity:20');
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchPluginNamespace')->willReturn('tx_solr');
        $configurationMock->expects(self::once())->method('getSearchTargetPage')->willReturn(42);

        $matcher = self::exactly(2);
        $previousRequest =  new SearchRequest($queryParameters, 42, 0, $configurationMock);
        $this->extBaseUriBuilderMock
            ->expects($matcher)->method('setArguments')
            ->willReturnCallback(function (array $arguments) use ($subsitutedQueryParameters, $queryParameters, $matcher) {
                match ($matcher->numberOfInvocations()) {
                    1 => self::assertEquals($subsitutedQueryParameters, $arguments),
                    2 => self::assertEquals($queryParameters, $arguments),
                    default => self::fail('Unexpected number of invocations: ' . $matcher->numberOfInvocations())
                };
                return $this->extBaseUriBuilderMock;
            });
        $this->extBaseUriBuilderMock->expects(self::once())->method('reset')->with()->willReturn($this->extBaseUriBuilderMock);
        $buildCounter = 0;
        $this->extBaseUriBuilderMock->expects(self::exactly(2))->method('build')
            ->willReturnCallback(function () use ($linkBuilderResult, &$buildCounter) {
                if (++$buildCounter === 1) {
                    throw new InvalidParameterException(
                        'First call fails, should reprocess with regular arguments',
                        5962606500,
                    );
                }
                return $linkBuilderResult;
            });
        $this->searchUrlBuilder->injectRoutingService($routingServiceMock);
        $uri = $this->searchUrlBuilder->getResultPageUri($previousRequest, 0);
        self::assertEquals($linkBuilderResult, $uri);
    }
}
