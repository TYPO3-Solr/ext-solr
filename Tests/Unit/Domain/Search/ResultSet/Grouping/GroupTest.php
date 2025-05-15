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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Grouping;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItemCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit test case for the Group class
 */
class GroupTest extends SetUpUnitTestCase
{
    #[Test]
    public function testCanGroupName(): void
    {
        $group = new Group('typeGroup');
        self::assertSame('typeGroup', $group->getGroupName(), 'Can not getGroupName from group');

        $group->setGroupName('changedTypeGroup');
        self::assertSame('changedTypeGroup', $group->getGroupName(), 'Can not getGroupName from group');
    }

    #[Test]
    public function canGetGroupItemsReturnEmptyCollection(): void
    {
        $group = new Group('typeGroup');
        self::assertSame(0, $group->getGroupItems()->getCount(), 'Can not get empty groupItem collection');
    }

    #[Test]
    public function canGetResultsPerPage(): void
    {
        $group = new Group('typeGroup', 22);
        self::assertSame(22, $group->getResultsPerPage(), 'Can not get results per page');

        $group->setResultsPerPage(11);
        self::assertSame(11, $group->getResultsPerPage(), 'Can not get results per page');
    }

    #[Test]
    public function canSetGroupItems(): void
    {
        $group = new Group('typeGroup', 10);
        $groupItems = new GroupItemCollection();
        $groupItem = new GroupItem(
            $group,
            'test',
            12,
            0,
            22.0,
            $this->createMock(SearchRequest::class),
        );
        $groupItems[] = $groupItem;

        $group->setGroupItems($groupItems);

        self::assertSame($groupItems, $group->getGroupItems(), 'Can not get group items from group');
    }

    #[Test]
    public function canAddGroupItem(): void
    {
        $group = new Group('typeGroup', 10);

        self::assertCount(0, $group->getGroupItems(), 'GroupItems are not empty from the beginning');
        $groupItem = new GroupItem(
            $group,
            'test',
            12,
            0,
            22.0,
            $this->createMock(SearchRequest::class),
        );
        $group->addGroupItem($groupItem);

        self::assertCount(1, $group->getGroupItems(), 'Unexpected group item count after adding a group');
    }
}
