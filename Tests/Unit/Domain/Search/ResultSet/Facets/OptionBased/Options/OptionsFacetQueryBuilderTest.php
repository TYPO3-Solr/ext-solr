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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBased\Options;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Testcase for the dateRange queryBuilder
 */
class OptionsFacetQueryBuilderTest extends SetUpUnitTestCase
{
    #[Test]
    public function canBuildSortParameter(): void
    {
        /**
         * sortBy = index
         * sortDirection = desc
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
            'sortBy' => 'index',
            'sortDirection' => 'desc',
        ];
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1,
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => 100,
                    'mincount' => 1,
                    'sort' => 'index desc',
                ],
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    #[Test]
    public function canBuildLimitParameter(): void
    {
        /**
         * limit 20
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
            'facetLimit' => 20,
        ];
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1,
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => 20,
                    'mincount' => 1,
                ],
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    #[Test]
    public function canBuildLimitParameterFromGlobalSetting(): void
    {
        /**
         * limit
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
        ];
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration,
        );

        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->willReturn(
            15,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1,
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => 15,
                    'mincount' => 1,
                ],
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    #[Test]
    public function canBuildMincountParameter(): void
    {
        /**
         * mincount = 2
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
            'minimumCount' => 2,
        ];
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100,
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => 100,
                    'mincount' => 2,
                ],
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    public static function getGlobalMinimumCountValue(): Traversable
    {
        yield ['configuredMinimumCount' => 5, 'expectedMinimumCount' => 5];
        yield ['configuredMinimumCount' => 0, 'expectedMinimumCount' => 0];
        yield ['configuredMinimumCount' => null, 'expectedMinimumCount' => 1];
    }

    #[DataProvider('getGlobalMinimumCountValue')]
    #[Test]
    public function canBuildMincountParameterFromGlobalSetting($configuredMinimumCount, $expectedMinimumCount): void
    {
        /**
         * mincount = 2
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
        ];
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration,
        );

        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->willReturn(
            $expectedMinimumCount,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100,
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => 100,
                    'mincount' => $expectedMinimumCount,
                ],
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    #[Test]
    public function canBuildMetricsParameter(): void
    {
        /**
         * metrics {
         *    downloads = sum(downloads_intS)
         * }
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
            'metrics.' => [
                'downloads' => 'sum(downloads_intS)',
            ],
        ];
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100,
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1,
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => 100,
                    'mincount' => 1,
                    'facet' => [
                        'metrics_downloads' => 'sum(downloads_intS)',
                    ],
                ],
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }
}
