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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Facets\RangeBased\DateRange;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for the dateRange queryBuilder
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueryGroupFacetQueryBuilderTest extends UnitTest
{

    /**
     * @test
     */
    public function canBuildQueryGroupFacetWithKeepAllOptionsOnSelection()
    {

        /**
         * queryGroup {
         *    keepAllOptionsOnSelection = 1
         *    week {
         *       query = [NOW/DAY-7DAYS TO *]
         *    }
         *    month {
         *       query = [NOW/DAY-1MONTH TO NOW/DAY-7DAYS]
         *    }
         * }
         */
        $fakeFacetConfiguration = [
            'type' => 'queryGroup',
            'field' => 'created',
            'keepAllOptionsOnSelection' => 1,
            'queryGroup.' => [
                'week.' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                'month.' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
            ],
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('testFacet')->willReturn(
            $fakeFacetConfiguration
        );

        $builder = new QueryGroupFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);
        $expectedFacetParameters = [
            'facet.query' => [
                '{!ex=created}created:[NOW/DAY-7DAYS TO *]',
                '{!ex=created}created:[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]',
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    /**
     * @test
     */
    public function canBuildQueryGroupFacetWithKeepAllFacetsOnSelection()
    {

        /**
         * faceting {
         *    keepAllFacetsOnSelection = 1
         *    facets {
         *       queryGroup {
         *          week {
         *          query = [NOW/DAY-7DAYS TO *]
         *       }
         *       month {
         *          query = [NOW/DAY-1MONTH TO NOW/DAY-7DAYS]
         *       }
         *    }
         * }
         */
        $fakeFacetConfiguration = [
            'keepAllFacetsOnSelection' => 1,
            'facets.' => [
                'type.' => [
                    'field' => 'type',
                ],
                'creations.' => [
                    'type' => 'queryGroup',
                    'field' => 'created',
                    'queryGroup.' => [
                        'week.' => ['query' => '[NOW/DAY-7DAYS TO *]'],
                        'month.' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'],
                    ],
                ],

            ],
        ];

        $fakeConfiguration['plugin.']['tx_solr.']['search.']['faceting.'] = $fakeFacetConfiguration;
        $configuration = new TypoScriptConfiguration($fakeConfiguration);
        $builder = new QueryGroupFacetQueryBuilder();

        $facetParameters = $builder->build('creations', $configuration);
        $expectedFacetParameters = [
            'facet.query' => [
                '{!ex=type,created}created:[NOW/DAY-7DAYS TO *]',
                '{!ex=type,created}created:[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]',
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }

    /**
     * @test
     */
    public function canBuild()
    {

        /**
         * queryGroup {
         *    week {
         *       query = [NOW/DAY-7DAYS TO *]
         *    }
         *    month {
         *       query = [NOW/DAY-1MONTH TO NOW/DAY-7DAYS]
         *    }
         * }
         */
        $fakeFacetConfiguration = [
            'type' => 'queryGroup',
            'field' => 'created',
            'queryGroup.' => [
                'week.' => ['query' => '[NOW/DAY-14DAYS TO *]'],
                'month.' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-14DAYS]'],
            ],
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchFacetingFacetByName')->with('testFacet')->willReturn(
            $fakeFacetConfiguration
        );

        $builder = new QueryGroupFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);
        $expectedFacetParameters = [
            'facet.query' => [
                'created:[NOW/DAY-14DAYS TO *]',
                'created:[NOW/DAY-1MONTH TO NOW/DAY-14DAYS]',
            ],
        ];

        self::assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }
}
