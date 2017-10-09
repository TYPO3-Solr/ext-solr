<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
    public function testCanFoo()
    {
        $group = new Group('typeGroup');
        $this->assertSame('typeGroup', $group->getGroupName(), 'Can not getGroupName from group');
    }

    /**
     * @test
     */
    public function canGetGroupItemsReturnEmptyCollection()
    {
        $group = new Group('typeGroup');
        $this->assertSame(0, $group->getGroupItems()->getCount(), 'Can not get empty groupItem collection');
    }
}