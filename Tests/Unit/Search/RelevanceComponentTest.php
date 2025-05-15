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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Search\RelevanceComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Solarium\QueryType\Select\RequestBuilder;

/**
 * Testcase for RelevanceComponent
 */
class RelevanceComponentTest extends SetUpUnitTestCase
{
    /**
     * @param $query
     * @return array
     */
    protected function getQueryParameters($query): array
    {
        $requestBuilder = new RequestBuilder();
        $request = $requestBuilder->build($query);
        return $request->getParams();
    }

    #[Test]
    public function canSetQuerySlop(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('qs', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame(2, $this->getQueryParameters($query)['qs'], 'querySlop was not applied as qs parameter');
    }

    #[Test]
    public function querySlopIsNotSetWhenPhraseIsDisabled(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('qs', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertArrayNotHasKey('qs', $this->getQueryParameters($query), 'querySlop should still be null because phrase is disabled');
    }

    #[Test]
    public function canSetSlop(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $relevanceComponent->__invoke($event);

        self::assertSame(3, $this->getQueryParameters($query)['ps'], 'slop was not applied as qs parameter');
    }

    #[Test]
    public function slopIsNullWhenPhraseIsDisabled(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertArrayNotHasKey('ps', $this->getQueryParameters($query), 'PhraseSlop should be null, when phrase is disabled');
    }

    #[Test]
    public function canSetBigramPhraseSlop(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $query = new Query();
        $query->setQuery('test');

        self::assertArrayNotHasKey('ps2', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame(4, $this->getQueryParameters($query)['ps2'], 'slop was not applied as qs parameter');
    }

    #[Test]
    public function canNotSetBigramPhraseSlopWhenBigramPhraseIsDisabled(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps2', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertArrayNotHasKey('ps2', $this->getQueryParameters($query), 'ps2 parameter should be empty because bigramPhrases are disabled');
    }

    #[Test]
    public function canSetTrigramPhraseSlop(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps3', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame(4, $this->getQueryParameters($query)['ps3'], 'slop was not applied as qs parameter');
    }

    #[Test]
    public function canNotSetTrigramPhraseSlopWhenBigramPhraseIsDisabled(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );
        $relevanceComponent = new RelevanceComponent($queryBuilder);

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('ps3', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertArrayNotHasKey('ps3', $this->getQueryParameters($query), 'ps3 parameter should be empty because bigramPhrases are disabled');
    }

    #[Test]
    public function canSetTieParameter(): void
    {
        $searchConfiguration = [
            'query.' => [
                'tieParameter' => '0.78',
            ],
        ];
        $typoscriptConfiguration = $this->getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration);
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $query = new Query();
        $query->setQuery('test');
        self::assertArrayNotHasKey('tie', $this->getQueryParameters($query));

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame((float)'0.78', $this->getQueryParameters($query)['tie'], 'tieParameter was not applied as tie parameter');
    }

    #[Test]
    public function canSetBoostQuery(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame('type:pages^100', $this->getQueryParameters($query)['bq'], 'Configured boostQuery was not applied');
    }

    #[Test]
    public function canSetBoostQueries(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame('type:pages^100', $this->getQueryParameters($query)['bq'][0], 'Configured boostQuery was not applied');
        self::assertSame('type:tx_solr_file^400', $this->getQueryParameters($query)['bq'][1], 'Configured boostQuery was not applied');
    }

    #[Test]
    public function canSetBoostFunction(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame('sum(clicks)^100', $this->getQueryParameters($query)['bf'], 'Configured boostFunction was not applied');
    }

    #[Test]
    public function canSetMinimumMatch(): void
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
        $queryBuilder = new QueryBuilder(
            $typoscriptConfiguration,
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class),
        );

        $event = new AfterSearchQueryHasBeenPreparedEvent(
            $query,
            $this->createMock(SearchRequest::class),
            $this->createMock(Search::class),
            $typoscriptConfiguration,
        );

        $subject = new RelevanceComponent($queryBuilder);
        $subject->__invoke($event);

        self::assertSame('<1', $this->getQueryParameters($query)['mm'], 'Configured minimumMatch was not applied');
    }

    protected function getTypoScriptConfigurationWithQueryConfiguration($searchConfiguration): TypoScriptConfiguration
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
