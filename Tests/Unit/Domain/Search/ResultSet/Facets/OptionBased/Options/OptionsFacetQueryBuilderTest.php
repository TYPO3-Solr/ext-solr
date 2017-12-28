<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  (c) 2016 Markus Friedrich <markus.friedrich@dkd.de>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('category')->will(
            $this->returnValue($fakeFacetConfiguration)
        );
        $configurationMock->expects($this->once())->method('getSearchFacetingFacets')->will(
            $this->returnValue([])
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => -1,
                    'mincount' => 1,
                    'sort' => 'index desc',
                ],
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
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
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('category')->will(
            $this->returnValue($fakeFacetConfiguration)
        );
        $configurationMock->expects($this->once())->method('getSearchFacetingFacets')->will(
            $this->returnValue([])
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
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
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
            'field' => 'category'
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('category')->will(
            $this->returnValue($fakeFacetConfiguration)
        );
        $configurationMock->expects($this->once())->method('getSearchFacetingFacets')->will(
            $this->returnValue([])
        );
        $configurationMock->expects($this->any())->method('getSearchFacetingFacetLimit')->will(
            $this->returnValue(15)
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
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
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
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('category')->will(
            $this->returnValue($fakeFacetConfiguration)
        );
        $configurationMock->expects($this->once())->method('getSearchFacetingFacets')->will(
            $this->returnValue([])
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => -1,
                    'mincount' => 2,
                ],
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    /**
     * @test
     */
    public function canBuildMincountParameterFromGlobalSetting()
    {
        /**
         * mincount = 2
         */
        $fakeFacetConfiguration = [
            'field' => 'category'
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('category')->will(
            $this->returnValue($fakeFacetConfiguration)
        );
        $configurationMock->expects($this->once())->method('getSearchFacetingFacets')->will(
            $this->returnValue([])
        );
        $configurationMock->expects($this->any())->method('getSearchFacetingMinimumCount')->will(
            $this->returnValue(5)
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => -1,
                    'mincount' => 5,
                ],
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
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
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('category')->will(
            $this->returnValue($fakeFacetConfiguration)
        );
        $configurationMock->expects($this->once())->method('getSearchFacetingFacets')->will(
            $this->returnValue([])
        );

        $builder = new OptionsFacetQueryBuilder();
        $facetParameters = $builder->build('category', $configurationMock);
        $expectedFacetParameters = [
            'json.facet' => [
                'category' => [
                    'type' => 'terms',
                    'field' => 'category',
                    'limit' => -1,
                    'mincount' => 1,
                    'facet' => [
                        'metrics_downloads' => 'sum(downloads_intS)',
                    ],
                ],
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }
}
