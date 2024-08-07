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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit test case for the SearchResultCollection.
 */
class SearchResultCollectionTest extends SetUpUnitTestCase
{
    protected SearchResultCollection $searchResultCollection;

    protected function setUp(): void
    {
        $this->searchResultCollection = new SearchResultCollection();
        parent::setUp();
    }

    #[Test]
    public function getHasGroupsReturnsFalseByDefault(): void
    {
        self::assertFalse($this->searchResultCollection->getHasGroups());
    }

    #[Test]
    public function getHasGroupsReturnsTrueWhenGroupsExist(): void
    {
        $groupA = new Group('foo');
        $this->searchResultCollection->getGroups()->add($groupA);
        self::assertTrue($this->searchResultCollection->getHasGroups());
    }

    #[Test]
    public function canSetAndGetGroupCollection(): void
    {
        $groupCollection = new GroupCollection();
        $this->searchResultCollection->setGroups($groupCollection);
        self::assertSame($groupCollection, $this->searchResultCollection->getGroups());
    }
}
