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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Routing;

use ApacheSolrForTypo3\Solr\Routing\RoutingService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Unit test to cover functions inside the routing service
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class RoutingServiceTest extends UnitTest
{
    /**
     * @var Site
     */
    protected $site;

    protected function setUp(): void
    {
        $this->site = new Site(
            'example',
            1,
            Yaml::parse($this->getFixtureContentByName('siteConfiguration.yaml'))
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::getDefaultMultiValueSeparator
     */
    public function defaultValueSeparatorIsAvailableTest()
    {
        $routingService = new RoutingService([]);

        self::assertEquals(
            ',',
            $routingService->getDefaultMultiValueSeparator()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::getDefaultMultiValueSeparator
     */
    public function canOverrideValueSeparatorTest()
    {
        $routingService = new RoutingService(
            [
                'multiValueSeparator' => '+',
            ]
        );

        self::assertEquals(
            '+',
            $routingService->getDefaultMultiValueSeparator()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::facetsToString
     */
    public function combinedFacetsAreInAlphabeticOrderTest()
    {
        $routingService = new RoutingService([]);

        self::assertEquals(
            'bar,buz,foo',
            $routingService->facetsToString(['foo', 'bar', 'buz'])
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::facetsToString
     */
    public function combiningFacetsUsingCustomSeparatorTest()
    {
        $routingService = new RoutingService(
            [
                'multiValueSeparator' => '+',
            ]
        );

        self::assertEquals(
            'bar+buz+foo',
            $routingService->facetsToString(['foo', 'bar', 'buz'])
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::convertStringIntoUri
     */
    public function canConvertStringToUriTest()
    {
        $routingService = new RoutingService();
        self::assertNotNull(
            $routingService->convertStringIntoUri('https://domain.example')
        );
        self::assertNotNull(
            $routingService->convertStringIntoUri('://domain.example')
        );
    }

    protected function getRoutingService(string $fixtureName = 'siteConfiguration.yaml'): RoutingService
    {
        $configuration = Yaml::parse($this->getFixtureContentByName($fixtureName));
        $routingService = new RoutingService(
            $configuration['routeEnhancers']['example']['solr'],
            (string)$configuration['routeEnhancers']['example']['extensionKey']
        );
        $routingService->setLogger(new NullLogger());
        return $routingService;
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::concatQueryParameter
     */
    public function testDeflateFilterQueryParameterTest()
    {
        $routingService = $this->getRoutingService();

        $queryParameters = [
            'tx_solr' => [
                'filter' => [
                    'color:yellow',
                    'taste:sour',
                    'product:sweets',
                    'color:green',
                    'taste:matcha',
                    'taste:sour,matcha',
                    'color:red',
                    'product:candy',
                ],
            ],
        ];
        /*
         * The order of the facet name based on their first appearance in the given filter array
         * The order of the values should be alphanumeric
         */
        $expectedResult = [
            'tx_solr' => [
                'filter' => [
                    'color:green,red,yellow',
                    'taste:matcha,sour,sour°matcha',
                    'product:candy,sweets',
                ],
            ],
        ];

        self::assertTrue($routingService->shouldConcatQueryParameters());
        self::assertEquals(
            $expectedResult,
            $routingService->concatQueryParameter($queryParameters)
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::inflateQueryParameter
     */
    public function testInflateFilterQueryParameterTest()
    {
        $routingService = $this->getRoutingService();

        $filter = [
            'tx_solr' => [
                'filter' => [
                    'color:green,red,yellow',
                    'product:candy,sweets',
                    'taste:matcha,sour,sour°matcha',
                ],
            ],
        ];

        /*
         * The order of the expected result based on the order of the filter!
         */
        $expectedResult = [
            'tx_solr' => [
                'filter' => [
                    'color:green',
                    'color:red',
                    'color:yellow',
                    'product:candy',
                    'product:sweets',
                    'taste:matcha',
                    'taste:sour',
                    'taste:sour,matcha',
                ],
            ],
        ];

        self::assertTrue($routingService->shouldConcatQueryParameters());
        self::assertEquals(
            $expectedResult,
            $routingService->inflateQueryParameter($filter)
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::maskQueryParameters
     */
    public function testIfFilterParametersCanBeMaskedTest()
    {
        $routingService = $this->getRoutingService();
        $queryParameters = [
            'tx_solr' => [
                'filter' => [
                    'color:yellow',
                    'taste:sour',
                    'product:sweets',
                    'color:green',
                    'taste:matcha',
                    'color:red',
                    'product:candy',
                ],
            ],
        ];
        /*
         * The order of the facet name based on their first appearance in the given filter array
         * The order of the values should be alphanumeric
         */
        $expectedResult = [
            'color' => 'green,red,yellow',
            'taste' => 'matcha,sour',
            'product' => 'candy,sweets',
        ];

        self::assertTrue($routingService->shouldMaskQueryParameter());
        $queryParameters = $routingService->concatQueryParameter($queryParameters);

        self::assertEquals(
            $expectedResult,
            $routingService->maskQueryParameters($queryParameters)
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::maskQueryParameters
     */
    public function testIfFilterParametersCanBeUnmaskedTest()
    {
        $routingService = $this->getRoutingService();
        $queryParameters = [
            'color' => 'green,red,yellow',
            'taste' => 'matcha,sour',
            'product' => 'candy,sweets',
        ];

        /*
         * The order of the facet name based on their first appearance in the given filter array
         * The order of the values should be alphanumeric
         */
        $expectedResult = [
            'tx_solr' => [
                'filter' => [
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
        self::assertTrue($routingService->shouldMaskQueryParameter());
        $queryParameters = $routingService->unmaskQueryParameters($queryParameters);

        self::assertEquals(
            $expectedResult,
            $routingService->inflateQueryParameter($queryParameters)
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::addPathArgumentsToQuery
     */
    public function testIfPathParametersMovedInfoQueryParameters()
    {
        $uri = new Uri('http://domain.example/');
        $request = new ServerRequest(
            $uri
        );
        $routingService = $this->getRoutingService();
        $newRequest = $routingService->addPathArgumentsToQuery(
            $request,
            [
                'color' => 'filter-colorType',
            ],
            [
                'color' => 'blue',
            ]
        );

        self::assertEquals(
            [
                'tx_solr' => [
                    'filter' => ['colorType:blue'],
                ],
            ],
            $newRequest->getQueryParams()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::addPathArgumentsToQuery
     */
    public function testIfMultiplePathParametersMovedInfoQueryParameters()
    {
        $uri = new Uri('http://domain.example/');
        $request = new ServerRequest(
            $uri
        );
        $routingService = $this->getRoutingService();
        $newRequest = $routingService->addPathArgumentsToQuery(
            $request,
            [
                'color' => 'filter-colorType',
            ],
            [
                'color' => 'green,blue',
            ]
        );

        self::assertEquals(
            [
                'tx_solr' => [
                    'filter' => ['colorType:blue', 'colorType:green'],
                ],
            ],
            $newRequest->getQueryParams()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::addPathArgumentsToQuery
     */
    public function testIfMultiplePathParametersAndMaskedParametersMovedInfoQueryParameters()
    {
        $uri = new Uri('http://domain.example/candy?taste=sweet,sour');
        $request = new ServerRequest(
            $uri
        );
        $request = $request->withQueryParams(['taste' => 'sweet,sour']);
        $routingService = $this->getRoutingService();

        $request = $routingService->addPathArgumentsToQuery(
            $request,
            [
                'color' => 'filter-colorType',
            ],
            [
                'color' => 'green,blue',
            ]
        );

        $uri = $request->getUri()->withPath(
            '/candy'
        );
        $request = $request->withUri($uri);
        $queryParams = $request->getQueryParams();
        $queryParams = $routingService->unmaskQueryParameters($queryParams);
        $queryParams = $routingService->inflateQueryParameter($queryParams);
        $request = $request->withQueryParams($queryParams);

        self::assertEquals(
            [
                'tx_solr' => [
                    'filter' => [
                        'taste:sweet',
                        'taste:sour',
                        'colorType:blue',
                        'colorType:green',
                    ],
                ],
            ],
            $request->getQueryParams()
        );
    }
}
