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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Unit test case for the SearchResult.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SearchResultTest extends UnitTest
{
    /**
     * @var SearchResult
     */
    protected $searchResult;

    protected function setUp(): void
    {
        $fields = [
            'id' => 4711,
            'title' => 'The title',
            'score' => 0.55,
            'content' => 'foobar',
            'isElevated' => true,
            'url' => '://mytestdomain.com/test',
            'type' => 'pages',
        ];
        $this->searchResult = new SearchResult($fields);
        parent::setUp();
    }

    /**
     * @test
     */
    public function canGetId()
    {
        self::assertSame(4711, $this->searchResult->getId(), 'Could not get id from searchResult');
    }

    /**
     * @test
     */
    public function canGetScore()
    {
        self::assertSame(0.55, $this->searchResult->getScore(), 'Could not get score from searchResult');
    }

    /**
     * @test
     */
    public function canGetContent()
    {
        self::assertSame('foobar', $this->searchResult->getContent(), 'Could not get content from searchResult');
    }

    /**
     * @test
     */
    public function canGetType()
    {
        self::assertSame('pages', $this->searchResult->getType(), 'Could not get type from searchResult');
    }

    /**
     * @test
     */
    public function canGetTitle()
    {
        self::assertSame('The title', $this->searchResult->getTitle(), 'Could not get title from searchResult');
    }

    /**
     * @test
     */
    public function canGetUrl()
    {
        self::assertSame('://mytestdomain.com/test', $this->searchResult->getUrl(), 'Could not get url from searchResult');
    }

    /**
     * @test
     */
    public function canGetIsElevated()
    {
        self::assertTrue($this->searchResult->getIsElevated(), 'Could not get isElevated from searchResult');
    }

    /**
     * @test
     */
    public function getOnUnexistingFieldReturnsNull()
    {
        self::assertNull($this->searchResult->getUnexistingField(), 'Calling getter for unexisting field does not return null');
    }
}
