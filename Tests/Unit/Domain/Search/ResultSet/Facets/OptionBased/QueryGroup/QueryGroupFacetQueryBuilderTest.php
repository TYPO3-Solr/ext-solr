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
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup\QueryGroupFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RangeBased\DateRange\DateRangeFacetQueryBuilder;
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
                'month.' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]']
            ]
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('testFacet')->will(
            $this->returnValue($fakeFacetConfiguration)
        );

        $builder = new QueryGroupFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);
        $expectedFacetParameters = [
            'facet.query' => [
                '{!ex=created}created:[NOW/DAY-7DAYS TO *]',
                '{!ex=created}created:[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
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
                        'month.' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]']
                    ]
                ]

            ]
        ];

        $fakeConfiguration['plugin.']['tx_solr.']['search.']['faceting.'] = $fakeFacetConfiguration;
        $configuration = new TypoScriptConfiguration($fakeConfiguration);
        $builder = new QueryGroupFacetQueryBuilder();

        $facetParameters = $builder->build('creations', $configuration);
        $expectedFacetParameters = [
            'facet.query' => [
                '{!ex=type,created}created:[NOW/DAY-7DAYS TO *]',
                '{!ex=type,created}created:[NOW/DAY-1MONTH TO NOW/DAY-7DAYS]'
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
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
                'month.' => ['query' => '[NOW/DAY-1MONTH TO NOW/DAY-14DAYS]']
            ]
        ];
        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects($this->once())->method('getSearchFacetingFacetByName')->with('testFacet')->will(
            $this->returnValue($fakeFacetConfiguration)
        );

        $builder = new QueryGroupFacetQueryBuilder();
        $facetParameters = $builder->build('testFacet', $configurationMock);
        $expectedFacetParameters = [
            'facet.query' => [
                'created:[NOW/DAY-14DAYS TO *]',
                'created:[NOW/DAY-1MONTH TO NOW/DAY-14DAYS]'
            ]
        ];

        $this->assertSame($expectedFacetParameters, $facetParameters, 'Can not build facet parameters as expected');
    }
}