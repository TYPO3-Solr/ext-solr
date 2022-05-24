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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\OptionBase\Options;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionsFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the dateRange queryBuilder
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class OptionsFacetQueryBuilderTest extends UnitTest
{
    /**
     * @test
     */
    public function canBuildSortParameter()
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
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1
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

    /**
     * @test
     */
    public function canBuildLimitParameter()
    {
        /**
         * limit 20
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
            'facetLimit' => 20,
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1
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

    /**
     * @test
     */
    public function canBuildLimitParameterFromGlobalSetting()
    {
        /**
         * limit
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration
        );

        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->willReturn(
            15
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1
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

    /**
     * @test
     */
    public function canBuildMincountParameter()
    {
        /**
         * mincount = 2
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
            'minimumCount' => 2,
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100
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

    /**
     * @return array
     */
    public function getGlobalMinimumCountValue()
    {
        return [
            ['configuredMinimumCount' => 5, 'expectedMinimumCount' => 5],
            ['configuredMinimumCount' => 0, 'expectedMinimumCount' => 0],
            ['configuredMinimumCount' => null, 'expectedMinimumCount' => 1],

        ];
    }

    /**
     * @dataProvider getGlobalMinimumCountValue
     * @test
     */
    public function canBuildMincountParameterFromGlobalSetting($configuredMinimumCount, $expectedMinimumCount)
    {
        /**
         * mincount = 2
         */
        $fakeFacetConfiguration = [
            'field' => 'category',
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration
        );

        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->willReturn(
            $expectedMinimumCount
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100
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

    /**
     * @test
     */
    public function canBuildMetricsParameter()
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
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('category')->willReturn(
            $fakeFacetConfiguration
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingFacetLimit')->with(100)->willReturn(
            100
        );
        $configurationMock->expects(self::any())->method('getSearchFacetingMinimumCount')->with(1)->willReturn(
            1
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
