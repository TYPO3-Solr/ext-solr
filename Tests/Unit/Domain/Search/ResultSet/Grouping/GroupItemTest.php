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
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit test case for the Group class
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupItemTest extends SetUpUnitTestCase
{
    /**
     * @var GroupItem
     */
    protected $groupItem;

    /**
     * @var Group
     */
    protected $parentGroup;

    protected function setUp(): void
    {
        $this->parentGroup = new Group('typeGroup');
        $this->groupItem = new GroupItem(
            $this->parentGroup,
            'pages',
            12,
            1,
            99,
            $this->createMock(SearchRequest::class)
        );
        parent::setUp();
    }

    #[Test]
    public function canGetMaximumScore()
    {
        self::assertSame(99.0, $this->groupItem->getMaximumScore(), 'Unexpected maximumScore');
    }

    #[Test]
    public function canGetStart()
    {
        self::assertSame(1, $this->groupItem->getStart(), 'Unexpected start');
    }

    #[Test]
    public function canGetNumFound()
    {
        self::assertSame(12, $this->groupItem->getAllResultCount(), 'Unexpected numFound');
    }

    #[Test]
    public function canGetGroupValue()
    {
        self::assertSame('pages', $this->groupItem->getGroupValue(), 'Unexpected groupValue');
    }

    #[Test]
    public function canGetGroup()
    {
        self::assertSame($this->parentGroup, $this->groupItem->getGroup(), 'Unexpected parentGroup');
    }

    #[Test]
    public function canGetSearchResults()
    {
        self::assertSame(0, $this->groupItem->getSearchResults()->getCount());

        $searchResult = $this->createMock(SearchResult::class);
        $this->groupItem->addSearchResult($searchResult);

        self::assertSame(1, $this->groupItem->getSearchResults()->getCount());
    }
}
