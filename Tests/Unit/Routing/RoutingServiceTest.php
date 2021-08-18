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

    public function setUp()
    {
        $this->site = new Site(
            'example',
            1,
            Yaml::parse($this->getFixtureContentByName('siteConfiguration.yaml'))
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::getFacetValueSeparator
     */
    public function defaultValueSeparatorIsAvailableTest()
    {
        $routingService = new RoutingService([]);

        $this->assertEquals(
            ',',
            $routingService->getFacetValueSeparator()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::getFacetValueSeparator
     */
    public function canOverrideValueSeparatorTest()
    {
        $routingService = new RoutingService(
            [
                'multiValueSeparator' => '+'
            ]
        );

        $this->assertEquals(
            '+',
            $routingService->getFacetValueSeparator()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::facetsToString
     */
    public function combinedFacetsAreInAlphabeticOrderTest()
    {
        $routingService = new RoutingService([]);

        $this->assertEquals(
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
                'multiValueSeparator' => '+'
            ]
        );

        $this->assertEquals(
            'bar+buz+foo',
            $routingService->facetsToString(['foo', 'bar', 'buz'])
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::facetStringToArray
     */
    public function canExplodeStringToArrayTest()
    {
        $routingService = new RoutingService([]);

        $this->assertEquals(
            ['bar', 'buz', 'foo'],
            $routingService->facetStringToArray('bar,buz,foo')
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::facetStringToArray
     */
    public function canExplodeStringToArrayWithCustomSeparatorTest()
    {
        $routingService = new RoutingService(
            [
                'multiValueSeparator' => '+'
            ]
        );

        $this->assertEquals(
            ['bar', 'buz', 'foo'],
            $routingService->facetStringToArray('bar+buz+foo')
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::convertStringIntoUri
     */
    public function canConvertStringToUriTest()
    {
        $routingService = new RoutingService();
        $this->assertNotNull(
            $routingService->convertStringIntoUri('https://domain.example')
        );
        $this->assertNotNull(
            $routingService->convertStringIntoUri('://domain.example')
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::determineSiteLanguage
     */
    public function determineSiteDefaultLanguageTest()
    {
        $uri = new Uri('http://domain.example');
        $routingService = new RoutingService();

        $language = $routingService->determineSiteLanguage($this->site, $uri);
        $this->assertEquals(
            'default',
            $language->getTypo3Language()
        );
        $this->assertTrue(
            $language->isEnabled()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::determineSiteLanguage
     */
    public function determineSiteLanguageTest()
    {
        $uri = new Uri('http://domain.example/da/');
        $routingService = new RoutingService();

        $language = $routingService->determineSiteLanguage($this->site, $uri);
        $this->assertEquals(
            'da',
            $language->getTwoLetterIsoCode()
        );
        $this->assertTrue(
            $language->isEnabled()
        );
    }

    /**
     * @test
     * @covers \ApacheSolrForTypo3\Solr\Routing\RoutingService::determineSiteLanguage
     */
    public function canDetermineSiteLanguageMultiByteTest()
    {
        $uri = new Uri('http://domain.example/日本語/');
        $routingService = new RoutingService();

        $language = $routingService->determineSiteLanguage($this->site, $uri);
        $this->assertEquals(
            'ja',
            $language->getTwoLetterIsoCode()
        );
        $this->assertTrue(
            $language->isEnabled()
        );
    }

    protected function getRoutingService(string $fixtureName = 'siteConfiguration.yaml'): RoutingService
    {
        $configuration = Yaml::parse($this->getFixtureContentByName($fixtureName));
        $routingService = new RoutingService($configuration['routeEnhancers']['example']['solr']);
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
                    'color:red',
                    'product:candy',
                ]
            ]
        ];
        /*
         * The order of the facet name based on their first appearance in the given filter array
         * The order of the values should be alphanumeric
         */
        $expectedResult = [
            'tx_solr' => [
                'filter' => [
                    'color:green,red,yellow',
                    'taste:matcha,sour',
                    'product:candy,sweets'
                ]
            ]
        ];

        $this->assertTrue($routingService->shouldConcatQueryParameters());
        $this->assertEquals(
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
                    'taste:matcha,sour'
                ]
            ]
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
                    'taste:sour'
                ]
            ]
        ];

        $this->assertTrue($routingService->shouldConcatQueryParameters());
        $this->assertEquals(
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
                ]
            ]
        ];
        /*
         * The order of the facet name based on their first appearance in the given filter array
         * The order of the values should be alphanumeric
         */
        $expectedResult = [
            'color' => 'green,red,yellow',
            'taste' => 'matcha,sour',
            'product' => 'candy,sweets'
        ];

        $this->assertTrue($routingService->shouldMaskQueryParameter());
        $queryParameters = $routingService->concatQueryParameter($queryParameters);

        $this->assertEquals(
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
            'product' => 'candy,sweets'
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
                ]
            ]
        ];
        $this->assertTrue($routingService->shouldMaskQueryParameter());
        $queryParameters = $routingService->unmaskQueryParameters($queryParameters);

        $this->assertEquals(
            $expectedResult,
            $routingService->inflateQueryParameter($queryParameters)
        );
    }
}