<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Grouping;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test case for the GroupCollection class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupCollectionTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetByGroupName()
    {
        $groupA = new Group('type');
        $groupB = new Group('color');
        $groupC = new Group('price');

        $groupCollection = new GroupCollection();
        $groupCollection[] = $groupA;
        $groupCollection[] = $groupB;
        $groupCollection[] = $groupC;

        $this->assertSame($groupB, $groupCollection->getByName('color'), 'Could not get groupByName');
        $this->assertNull($groupCollection->getByName('unexisting'), 'Could not get groupByName');

    }

    /**
     * @test
     */
    public function canGetGroupNames()
    {
        $groupA = new Group('type');
        $groupB = new Group('color');
        $groupC = new Group('price');

        $groupCollection = new GroupCollection();
        $groupCollection[] = $groupA;
        $groupCollection[] = $groupB;
        $groupCollection[] = $groupC;

        $this->assertSame(['type','color','price'], $groupCollection->getGroupNames(), 'Could not get groupNames');
    }

    /**
     * @test
     */
    public function canGetHasWithName()
    {
        $groupA = new Group('price');
        $groupCollection = new GroupCollection();
        $groupCollection[] = $groupA;

        $this->assertTrue($groupCollection->getHasWithName('price'), 'Item that should be in GroupCollection does not occure in GroupCollection');
        $this->assertFalse($groupCollection->getHasWithName('nonexisting'), 'Unexisting GroupCollection item was indicated to exist in the collection');
    }

}