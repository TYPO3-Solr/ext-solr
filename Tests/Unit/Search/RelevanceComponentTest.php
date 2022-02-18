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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
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
                    'querySlop' => 2,
                ],
            ],
        ];

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('qs', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame(2, $this->getQueryParameters($query)['qs'], 'querySlop was not applied as qs parameter');
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
                    'querySlop' => 2,
                ],
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('qs', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertArrayNotHasKey('qs', $this->getQueryParameters($query), 'querySlop should still be null because phrase is disabled');
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
                    'slop' => 3,
                ],
            ],
        ];

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame(3, $this->getQueryParameters($query)['ps'], 'slop was not applied as qs parameter');
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
                    'slop' => 3,
                ],
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertArrayNotHasKey('ps', $this->getQueryParameters($query), 'PhraseSlop should be null, when phrase is disabled');
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
                    'slop' => 4,
                ],
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');

        self::assertArrayNotHasKey('ps2', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame(4, $this->getQueryParameters($query)['ps2'], 'slop was not applied as qs parameter');
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
                    'slop' => 4,
                ],
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps2', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertArrayNotHasKey('ps2', $this->getQueryParameters($query), 'ps2 parameter should be empty because bigramPhrases are disabled');
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
                    'slop' => 4,
                ],
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps3', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame(4, $this->getQueryParameters($query)['ps3'], 'slop was not applied as qs parameter');
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
                    'slop' => 4,
                ],
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps3', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertArrayNotHasKey('ps3', $this->getQueryParameters($query), 'ps3 parameter should be empty because bigramPhrases are disabled');
    }

    /**
     * @test
     */
    public function canSetTieParameter()
    {
        $searchConfiguration = [
            'query.' => [
                'tieParameter' => '0.78',
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('tie', $this->getQueryParameters($query));

        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame((float)'0.78', $this->getQueryParameters($query)['tie'], 'tieParameter was not applied as tie parameter');
    }

    /**
     * @test
     */
    public function canSetBoostQuery()
    {
        $searchConfiguration = [
            'query.' => [
                'boostQuery' => 'type:pages^100',
            ],
        ];

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('bq', $this->getQueryParameters($query));

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame('type:pages^100', $this->getQueryParameters($query)['bq'], 'Configured boostQuery was not applied');
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
                    'type:tx_solr_file^400',
                 ],
            ],
        ];

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('bq', $this->getQueryParameters($query));

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame('type:pages^100', $this->getQueryParameters($query)['bq'][0], 'Configured boostQuery was not applied');
        self::assertSame('type:tx_solr_file^400', $this->getQueryParameters($query)['bq'][1], 'Configured boostQuery was not applied');
    }

    /**
     * @test
     */
    public function canSetBoostFunction()
    {
        $searchConfiguration = [
            'query.' => [
                'boostFunction' => 'sum(clicks)^100',
            ],
        ];

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('bf', $this->getQueryParameters($query));

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame('sum(clicks)^100', $this->getQueryParameters($query)['bf'], 'Configured boostFunction was not applied');
    }

    /**
     * @test
     */
    public function canSetMinimumMatch()
    {
        $searchConfiguration = [
            'query.' => [
                'minimumMatch' => '<1',
            ],
        ];

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('mm', $this->getQueryParameters($query));

        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder($typoscriptConfiguration);

        $relevanceComponent = new RelevanceComponent($queryBuilder);
        $relevanceComponent->setSearchConfiguration($searchConfiguration);
        $relevanceComponent->setQuery($query);
        $relevanceComponent->initializeSearchComponent();

        self::assertSame('<1', $this->getQueryParameters($query)['mm'], 'Configured minimumMatch was not applied');
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
                    'search.' => $searchConfiguration,
                ],
            ],
        ]);
    }
}
