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
