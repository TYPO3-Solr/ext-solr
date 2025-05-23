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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Unit test to cover functions inside the routing service
 */
#[CoversClass(RoutingService::class)]
class RoutingServiceTest extends SetUpUnitTestCase
{
    protected Site $site;

    protected function setUp(): void
    {
        $this->site = new Site(
            'example',
            1,
            Yaml::parse(self::getFixtureContentByName('siteConfiguration.yaml')),
        );
        parent::setUp();
    }

    #[Test]
    public function defaultValueSeparatorIsAvailableTest(): void
    {
        $routingService = new RoutingService([]);

        self::assertEquals(
            ',',
            $routingService->getDefaultMultiValueSeparator(),
        );
    }

    #[Test]
    public function canOverrideValueSeparatorTest(): void
    {
        $routingService = new RoutingService(
            [
                'multiValueSeparator' => '+',
            ],
        );

        self::assertEquals(
            '+',
            $routingService->getDefaultMultiValueSeparator(),
        );
    }

    #[Test]
    public function combinedFacetsAreInAlphabeticOrderTest(): void
    {
        $routingService = new RoutingService([]);

        self::assertEquals(
            'bar,buz,foo',
            $routingService->facetsToString(['foo', 'bar', 'buz']),
        );
    }

    #[Test]
    public function combiningFacetsUsingCustomSeparatorTest(): void
    {
        $routingService = new RoutingService(
            [
                'multiValueSeparator' => '+',
            ],
        );

        self::assertEquals(
            'bar+buz+foo',
            $routingService->facetsToString(['foo', 'bar', 'buz']),
        );
    }

    #[Test]
    public function canConvertStringToUriTest(): void
    {
        $routingService = new RoutingService();
        self::assertNotNull(
            $routingService->convertStringIntoUri('https://domain.example'),
        );
        self::assertNotNull(
            $routingService->convertStringIntoUri('://domain.example'),
        );
    }

    protected function getRoutingService(string $fixtureName = 'siteConfiguration.yaml'): RoutingService
    {
        $configuration = Yaml::parse(self::getFixtureContentByName($fixtureName));
        $routingService = new RoutingService(
            $configuration['routeEnhancers']['example']['solr'],
            (string)$configuration['routeEnhancers']['example']['extensionKey'],
        );
        $routingService->setLogger(new NullLogger());
        return $routingService;
    }

    #[Test]
    public function testDeflateFilterQueryParameterTest(): void
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
            $routingService->concatQueryParameter($queryParameters),
        );
    }

    #[Test]
    public function testInflateFilterQueryParameterTest(): void
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
            $routingService->inflateQueryParameter($filter),
        );
    }

    #[Test]
    public function testIfFilterParametersCanBeMaskedTest(): void
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
            $routingService->maskQueryParameters($queryParameters),
        );
    }

    #[Test]
    public function testIfFilterParametersCanBeUnmaskedTest(): void
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
            $routingService->inflateQueryParameter($queryParameters),
        );
    }

    #[Test]
    public function testIfPathParametersMovedInfoQueryParameters(): void
    {
        $uri = new Uri('http://domain.example/');
        $request = new ServerRequest(
            $uri,
        );
        $routingService = $this->getRoutingService();
        $newRequest = $routingService->addPathArgumentsToQuery(
            $request,
            [
                'color' => 'filter-colorType',
            ],
            [
                'color' => 'blue',
            ],
        );

        self::assertEquals(
            [
                'tx_solr' => [
                    'filter' => ['colorType:blue'],
                ],
            ],
            $newRequest->getQueryParams(),
        );
    }

    #[Test]
    public function testIfMultiplePathParametersMovedInfoQueryParameters(): void
    {
        $uri = new Uri('http://domain.example/');
        $request = new ServerRequest(
            $uri,
        );
        $routingService = $this->getRoutingService();
        $newRequest = $routingService->addPathArgumentsToQuery(
            $request,
            [
                'color' => 'filter-colorType',
            ],
            [
                'color' => 'green,blue',
            ],
        );

        self::assertEquals(
            [
                'tx_solr' => [
                    'filter' => ['colorType:blue', 'colorType:green'],
                ],
            ],
            $newRequest->getQueryParams(),
        );
    }

    #[Test]
    public function testIfMultiplePathParametersAndMaskedParametersMovedInfoQueryParameters(): void
    {
        $uri = new Uri('http://domain.example/candy?taste=sweet,sour');
        $request = new ServerRequest(
            $uri,
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
            ],
        );

        $uri = $request->getUri()->withPath(
            '/candy',
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
            $request->getQueryParams(),
        );
    }
}
