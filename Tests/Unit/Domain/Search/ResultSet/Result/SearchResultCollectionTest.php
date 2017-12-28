<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test case for the SearchResultCollection.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchResultCollectionTest extends UnitTest
{

    /**
     * @var SearchResultCollection
     */
    protected $searchResultCollection = null;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->searchResultCollection = new SearchResultCollection();
    }

    /**
     * @test
     */
    public function getHasGroupsReturnsFalseByDefault()
    {
        $this->assertFalse($this->searchResultCollection->getHasGroups());
    }

    /**
     * @test
     */
    public function getHasGroupsReturnsTrueWhenGroupsExist()
    {
        $groupA = new Group('foo');
        $this->searchResultCollection->getGroups()->add($groupA);
        $this->assertTrue($this->searchResultCollection->getHasGroups());
    }

    /**
     * @test
     */
    public function canSetAndGetGroupCollection()
    {
        $groupCollection = new GroupCollection();
        $this->searchResultCollection->setGroups($groupCollection);
        $this->assertSame($groupCollection, $this->searchResultCollection->getGroups());
    }
}
