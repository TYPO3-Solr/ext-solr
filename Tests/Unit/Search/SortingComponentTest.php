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
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search\SortingComponent;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase for SortingComponent
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SortingComponentTest extends UnitTest
{

    /**
     * @var Query
     */
    protected $query;

    /**
     * @var SearchRequest
     */
    protected $searchRequestMock;

    /**
     * @var SortingComponent
     */
    protected $sortingComponent;

    /**
     * SortingComponentTest constructor.
     */
    protected function setUp(): void
    {
        $this->query = new Query();
        $this->query->setQuery('');
        $this->searchRequestMock = $this->getDumbMock(SearchRequest::class);

        $this->sortingComponent = new SortingComponent();
        $this->sortingComponent->setQuery($this->query);
        $this->sortingComponent->setSearchRequest($this->searchRequestMock);
        parent::setUp();
    }

    /**
     * @test
     */
    public function sortingFromUrlIsNotAppliedWhenSortingIsDisabled()
    {
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->initializeSearchComponent();
        self::assertSame([], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function validSortingFromUrlIsApplied()
    {
        $this->sortingComponent->setSearchConfiguration([
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
        $this->sortingComponent->initializeSearchComponent();
        self::assertSame(['sortTitle' => 'asc'], $this->query->getSorts(), 'Sorting was not applied in the query as expected');
    }

    /**
     * @test
     */
    public function invalidSortingFromUrlIsNotApplied()
    {
        $this->sortingComponent->setSearchConfiguration([
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type'],
                ],
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn(['sort' => 'title INVALID']);
        $this->sortingComponent->initializeSearchComponent();
        self::assertSame([], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function sortByIsApplied()
    {
        $this->sortingComponent->setSearchConfiguration([
            'query.' => [
                'sortBy' => 'price desc',
            ],
        ]);
        $this->searchRequestMock->expects(self::any())->method('getArguments')->willReturn([]);
        $this->sortingComponent->initializeSearchComponent();
        self::assertSame(['price' => 'desc'], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function urlSortingHasPrioriy()
    {
        $this->sortingComponent->setSearchConfiguration([
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
        $this->sortingComponent->initializeSearchComponent();
        self::assertSame(['sortTitle' =>  'asc'], $this->query->getSorts(), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function querySortingHasPriorityWhenSortingIsDisabled()
    {
        $this->sortingComponent->setSearchConfiguration([
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
        $this->sortingComponent->initializeSearchComponent();
        self::assertSame(['price' => 'desc'], $this->query->getSorts(), 'No sorting should be present in query');
    }
}
