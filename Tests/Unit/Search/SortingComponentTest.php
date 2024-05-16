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
use ApacheSolrForTypo3\Solr\Search\SortingComponent;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Testcase for SortingComponent
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SortingComponentTest extends SetUpUnitTestCase
{
    protected Query|MockObject $query;
    protected SearchRequest|MockObject $searchRequestMock;
    protected SortingComponent|MockObject $sortingComponent;

    /**
     * SortingComponentTest constructor.
     */
    protected function setUp(): void
    {
        $this->query = new Query();
        $this->query->setQuery('');
        $this->searchRequestMock = $this->createMock(SearchRequest::class);

        $queryBuilder = new QueryBuilder(
            $this->createMock(TypoScriptConfiguration::class),
            $this->createMock(SolrLogManager::class),
            $this->createMock(SiteHashService::class)
        );

        $this->sortingComponent = new SortingComponent($queryBuilder);
        parent::setUp();
    }

    #[Test]
    public function sortingFromUrlIsNotAppliedWhenSortingIsDisabled(): void
    {
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $this->createMock(TypoScriptConfiguration::class));
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->__invoke($event);
        self::assertSame([], $event->getQuery()->getSorts(), 'No sorting should be present in query');
    }

    #[Test]
    public function validSortingFromUrlIsApplied(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['sortTitle' => 'asc'], $this->query->getSorts(), 'Sorting was not applied in the query as expected');
    }

    #[Test]
    public function invalidSortingFromUrlIsNotApplied(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title INVALID']);
        $this->sortingComponent->__invoke($event);
        self::assertSame([], $this->query->getSorts(), 'No sorting should be present in query');
    }

    #[Test]
    public function sortByIsApplied(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'query.' => [
                'sortBy' => 'price desc',
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn([]);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['price' => 'desc'], $this->query->getSorts(), 'No sorting should be present in query');
    }

    #[Test]
    public function urlSortingHasPrioriy(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'query.' => [
                'sortBy' => 'price desc',
            ],
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['sortTitle' =>  'asc'], $this->query->getSorts(), 'No sorting should be present in query');
    }

    #[Test]
    public function querySortingHasPriorityWhenSortingIsDisabled(): void
    {
        $configuration = $this->createMock(TypoScriptConfiguration::class);
        $configuration->method('getSearchConfiguration')->willReturn([
            'query.' => [
                'sortBy' => 'price desc',
            ],
            'sorting' => 0,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $event = new AfterSearchQueryHasBeenPreparedEvent($this->query, $this->searchRequestMock, $this->createMock(Search::class), $configuration);
        $this->sortingComponent->__invoke($event);
        self::assertSame(['price' => 'desc'], $this->query->getSorts(), 'No sorting should be present in query');
    }
}
