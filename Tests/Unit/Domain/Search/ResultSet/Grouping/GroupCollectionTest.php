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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit test case for the GroupCollection class
 */
class GroupCollectionTest extends SetUpUnitTestCase
{
    #[Test]
    public function canGetByGroupName(): void
    {
        $groupA = new Group('type');
        $groupB = new Group('color');
        $groupC = new Group('price');

        $groupCollection = new GroupCollection();
        $groupCollection[] = $groupA;
        $groupCollection[] = $groupB;
        $groupCollection[] = $groupC;

        self::assertSame($groupB, $groupCollection->getByName('color'), 'Could not get groupByName');
        self::assertNull($groupCollection->getByName('unexisting'), 'Could not get groupByName');
    }

    #[Test]
    public function canGetGroupNames(): void
    {
        $groupA = new Group('type');
        $groupB = new Group('color');
        $groupC = new Group('price');

        $groupCollection = new GroupCollection();
        $groupCollection[] = $groupA;
        $groupCollection[] = $groupB;
        $groupCollection[] = $groupC;

        self::assertSame(['type', 'color', 'price'], $groupCollection->getGroupNames(), 'Could not get groupNames');
    }

    #[Test]
    public function canGetHasWithName(): void
    {
        $groupA = new Group('price');
        $groupCollection = new GroupCollection();
        $groupCollection[] = $groupA;

        self::assertTrue($groupCollection->getHasWithName('price'), 'Item that should be in GroupCollection does not occure in GroupCollection');
        self::assertFalse($groupCollection->getHasWithName('nonexisting'), 'Unexisting GroupCollection item was indicated to exist in the collection');
    }
}
