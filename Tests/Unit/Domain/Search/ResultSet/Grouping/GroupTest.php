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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItemCollection;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test case for the Group class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupTest extends UnitTest
{

    /**
     * @test
     */
    public function testCanGroupName()
    {
        $group = new Group('typeGroup');
        $this->assertSame('typeGroup', $group->getGroupName(), 'Can not getGroupName from group');

        $group->setGroupName('changedTypeGroup');
        $this->assertSame('changedTypeGroup', $group->getGroupName(), 'Can not getGroupName from group');

    }

    /**
     * @test
     */
    public function canGetGroupItemsReturnEmptyCollection()
    {
        $group = new Group('typeGroup');
        $this->assertSame(0, $group->getGroupItems()->getCount(), 'Can not get empty groupItem collection');
    }

    /**
     * @test
     */
    public function canGetResultsPerPage()
    {
        $group = new Group('typeGroup', 22);
        $this->assertSame(22, $group->getResultsPerPage(), 'Can not get results per page');

        $group->setResultsPerPage(11);
        $this->assertSame(11, $group->getResultsPerPage(), 'Can not get results per page');
    }

    /**
     * @test
     */
    public function canSetGroupItems()
    {
        $group = new Group('typeGroup', 10);
        $groupItems = new GroupItemCollection();
        $groupItem = new GroupItem($group, 'test', 12, 0, 22.0);
        $groupItems[] = $groupItem;

        $group->setGroupItems($groupItems);

        $this->assertSame($groupItems, $group->getGroupItems(), 'Can not get group items from group');
    }

    /**
     * @test
     */
    public function canAddGroupItem()
    {
        $group = new Group('typeGroup', 10);

        $this->assertCount(0, $group->getGroupItems(), 'GroupItems are not empty from the beginning');
        $groupItem = new GroupItem($group, 'test', 12, 0, 22.0);
        $group->addGroupItem($groupItem);

        $this->assertCount(1, $group->getGroupItems(), 'Unexpected group item count after adding a group');
    }
}