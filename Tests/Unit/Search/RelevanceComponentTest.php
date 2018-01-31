<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\SearchQuery;
use ApacheSolrForTypo3\Solr\Search\RelevanceComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Solarium\QueryType\Select\RequestBuilder;

/**
 * Testcase for RelevanceComponent
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RelevanceComponentTest extends UnitTest
{

    /**
     * @param $query
     * @return array
     */
    protected function getQueryParameters($query)
    {
        $requestBuilder = new RequestBuilder();
        $request = $requestBuilder->build($query);
        return $request->getParams();
    }

    /**
     * @test
     */
    public function canSetQuerySlop()
    {
        $searchConfiguration = [
            'query.' => [
                'phrase' => 1,
                'phrase.' => [
                    'querySlop' => 2
                ]
            ]
        ];

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['qs']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame(2, $this->getQueryParameters($query)['qs'], 'querySlop was not applied as qs parameter');
    }

    /**
     * @test
     */
    public function querySlopIsNotSetWhenPhraseIsDisabled()
    {
        $searchConfiguration = [
            'query.' => [
                'phrase' => 0,
                'phrase.' => [
                    'querySlop' => 2
                ]
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['qs']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertNull($this->getQueryParameters($query)['qs'], 'querySlop should still be null because phrase is disabled');
    }

    /**
     * @test
     */
    public function canSetSlop()
    {
        $searchConfiguration = [
            'query.' => [
                'phrase' => 1,
                'phrase.' => [
                    'slop' => 3
                ]
            ]
        ];

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['ps']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame(3, $this->getQueryParameters($query)['ps'], 'slop was not applied as qs parameter');
    }

    /**
     * @test
     */
    public function slopIsNullWhenPhraseIsDisabled()
    {
        $searchConfiguration = [
            'query.' => [
                'phrase' => 0,
                'phrase.' => [
                    'slop' => 3
                ]
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['ps']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertNull($this->getQueryParameters($query)['ps'], 'PhraseSlop should be null, when phrase is disabled');
    }

    /**
     * @test
     */
    public function canSetBigramPhraseSlop()
    {
        $searchConfiguration = [
            'query.' => [
                'bigramPhrase' => 1,
                'bigramPhrase.' => [
                    'slop' => 4
                ]
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');

        $this->assertNull($this->getQueryParameters($query)['ps2']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame(4, $this->getQueryParameters($query)['ps2'], 'slop was not applied as qs parameter');
    }

    /**
     * @test
     */
    public function canNotSetBigramPhraseSlopWhenBigramPhraseIsDisabled()
    {
        $searchConfiguration = [
            'query.' => [
                'bigramPhrase' => 0,
                'bigramPhrase.' => [
                    'slop' => 4
                ]
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['ps2']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertNull($this->getQueryParameters($query)['ps2'], 'ps2 parameter should be empty because bigramPhrases are disabled');
    }

    /**
     * @test
     */
    public function canSetTrigramPhraseSlop()
    {
        $searchConfiguration = [
            'query.' => [
                'trigramPhrase' => 1,
                'trigramPhrase.' => [
                    'slop' => 4
                ]
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['ps3']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame(4, $this->getQueryParameters($query)['ps3'], 'slop was not applied as qs parameter');
    }

    /**
     * @test
     */
    public function canNotSetTrigramPhraseSlopWhenBigramPhraseIsDisabled()
    {
        $searchConfiguration = [
            'query.' => [
                'trigramPhrase' => 0,
                'trigramPhrase.' => [
                    'slop' => 4
                ]
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['ps3']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertNull($this->getQueryParameters($query)['ps3'], 'ps3 parameter should be empty because bigramPhrases are disabled');
    }


    /**
     * @test
     */
    public function canSetTieParameter()
    {
        $searchConfiguration = [
            'query.' => [
                'tieParameter' => '0.78',
            ]
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['tie']);

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame('0.78', $this->getQueryParameters($query)['tie'], 'tieParameter was not applied as tie parameter');
    }

    /**
     * @test
     */
    public function canSetBoostQuery()
    {
        $searchConfiguration = [
            'query.' => [
                'boostQuery' => 'type:pages^100',
            ]
        ];

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['bq']);

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame('type:pages^100', $this->getQueryParameters($query)['bq'], 'Configured boostQuery was not applied');
    }

    /**
     * @test
     */
    public function canSetBoostQueries()
    {
        $searchConfiguration = [
            'query.' => [
                'boostQuery.' => [
                    'type:pages^100',
                    'type:tx_solr_file^400'
                 ]
            ]
        ];

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['bq']);

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame('type:pages^100', $this->getQueryParameters($query)['bq'][0], 'Configured boostQuery was not applied');
        $this->assertSame('type:tx_solr_file^400', $this->getQueryParameters($query)['bq'][1], 'Configured boostQuery was not applied');
    }

    /**
     * @test
     */
    public function canSetBoostFunction()
    {
        $searchConfiguration = [
            'query.' => [
                'boostFunction' => 'sum(clicks)^100'
            ]
        ];

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['bf']);

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame('sum(clicks)^100', $this->getQueryParameters($query)['bf'], 'Configured boostFunction was not applied');
    }

    /**
     * @test
     */
    public function canSetMinimumMatch()
    {
        $searchConfiguration = [
            'query.' => [
                'minimumMatch' => '<1'
            ]
        ];

        $query = new SearchQuery();
        $query->setQuery('test');
        $this->assertNull($this->getQueryParameters($query)['mm']);

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);

        $relevanceComponent = new RelevanceComponent($queryBuilder);
        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        $this->assertSame('<1', $this->getQueryParameters($query)['mm'], 'Configured minimumMatch was not applied');
    }

    /**
     * @param array $searchConfiguration
     * @return TypoScriptConfiguration
     */
    protected function getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration)
    {
        return new TypoScriptConfiguration([
            'plugin.' => [
                'tx_solr.' => [
                    'search.' => $searchConfiguration
                ]
            ]
        ]);
    }
}