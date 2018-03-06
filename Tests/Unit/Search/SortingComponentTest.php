<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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
    public function setUp()
    {
        $this->query = new Query('');
        $this->searchRequestMock = $this->getDumbMock(SearchRequest::class);

        $this->sortingComponent = new SortingComponent();
        $this->sortingComponent->setQuery($this->query);
        $this->sortingComponent->setSearchRequest($this->searchRequestMock);
    }

    /**
     * @test
     */
    public function sortingFromUrlIsNotAppliedWhenSortingIsDisabled()
    {
        $this->searchRequestMock->expects($this->once())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->initializeSearchComponent();
        $this->assertNull($this->query->getQueryParameter('sort'), 'No sorting should be present in query');
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
                    'type.' => ['field' => 'type', 'label' => 'Type']
                ]
            ]
        ]);
        $this->searchRequestMock->expects($this->once())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->initializeSearchComponent();
        $this->assertSame('sortTitle asc', $this->query->getQueryParameter('sort'), 'Sorting was not applied in the query as expected');
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
                    'type.' => ['field' => 'type', 'label' => 'Type']
                ]
            ]
        ]);
        $this->searchRequestMock->expects($this->once())->method('getArguments')->willReturn(['sort' => 'title INVALID']);
        $this->sortingComponent->initializeSearchComponent();
        $this->assertNull($this->query->getQueryParameter('sort'), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function sortByIsApplied()
    {
        $this->sortingComponent->setSearchConfiguration([
            'query.' => [
                'sortBy' => 'price desc'
            ]
        ]);
        $this->searchRequestMock->expects($this->once())->method('getArguments')->willReturn([]);
        $this->sortingComponent->initializeSearchComponent();
        $this->assertSame('price desc', $this->query->getQueryParameter('sort'), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function urlSortingHasPrioriy()
    {
        $this->sortingComponent->setSearchConfiguration([
            'query.' => [
                'sortBy' => 'price desc'
            ],
            'sorting' => 1,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type']
                ]
            ]
        ]);
        $this->searchRequestMock->expects($this->once())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->initializeSearchComponent();
        $this->assertSame('sortTitle asc', $this->query->getQueryParameter('sort'), 'No sorting should be present in query');
    }

    /**
     * @test
     */
    public function querySortingHasPriorityWhenSortingIsDisabled()
    {
        $this->sortingComponent->setSearchConfiguration([
            'query.' => [
                'sortBy' => 'price desc'
            ],
            'sorting' => 0,
            'sorting.' => [
                'options.' => [
                    'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
                    'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
                    'type.' => ['field' => 'type', 'label' => 'Type']
                ]
            ]
        ]);
        $this->searchRequestMock->expects($this->once())->method('getArguments')->willReturn(['sort' => 'title asc']);
        $this->sortingComponent->initializeSearchComponent();
        $this->assertSame('price desc', $this->query->getQueryParameter('sort'), 'No sorting should be present in query');
    }
}