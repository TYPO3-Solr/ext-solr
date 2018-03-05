<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet\Sorting;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting\SortingHelper;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Class SortingHelperTest
 */
class SortingHelperTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetSortFieldFromUrlParameter()
    {
        $sortConfiguration = [
            'relevance.' => ['field' => 'relevance', 'label' => 'Title'],
            'title.' => ['field' => 'sortTitle', 'label' => 'Title'],
            'type.' => ['field' => 'type', 'label' => 'Type']
        ];
        $sorting = new SortingHelper($sortConfiguration);
        $sortField = $sorting->getSortFieldFromUrlParameter('title asc');
        $this->assertSame('sortTitle asc', $sortField);

        $sortField = $sorting->getSortFieldFromUrlParameter('title desc');
        $this->assertSame('sortTitle desc', $sortField);

        $sortField = $sorting->getSortFieldFromUrlParameter('title desc,type asc');
        $this->assertSame('sortTitle desc, type asc', $sortField);
    }

    /**
     * @test
     */
    public function canThrowExceptionForUnconfiguredSorting()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sorting configuration found for option name unconfigured');
        $sorting = new SortingHelper([]);
        $sorting->getSortFieldFromUrlParameter('unconfigured asc');
    }
}