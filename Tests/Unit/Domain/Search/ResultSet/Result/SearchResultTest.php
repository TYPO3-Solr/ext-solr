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

use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;

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

    public function setUp()
    {
        $document = new \Apache_Solr_Document();
        $document->setField('id', 4711);
        $document->setField('score', 0.55);
        $document->setField('content', 'foobar');
        $document->setField('isElevated', true);
        $document->setField('title', 'The title');
        $document->setField('url', '://mytestdomain.com/test');
        $document->setField('type', 'pages');
        $this->searchResult = new SearchResult($document);
    }

    /**
     * @test
     */
    public function canGetId()
    {
        $this->assertSame(4711, $this->searchResult->getId(), 'Could not get id from searchResult');
    }

    /**
     * @test
     */
    public function canGetScore()
    {
        $this->assertSame(0.55, $this->searchResult->getScore(), 'Could not get score from searchResult');
    }

    /**
     * @test
     */
    public function canGetContent()
    {
        $this->assertSame('foobar', $this->searchResult->getContent(), 'Could not get content from searchResult');
    }

    /**
     * @test
     */
    public function canGetType()
    {
        $this->assertSame('pages', $this->searchResult->getType(), 'Could not get type from searchResult');
    }

    /**
     * @test
     */
    public function canGetTitle()
    {
        $this->assertSame('The title', $this->searchResult->getTitle(), 'Could not get title from searchResult');
    }

    /**
     * @test
     */
    public function canGetUrl()
    {
        $this->assertSame('://mytestdomain.com/test', $this->searchResult->getUrl(), 'Could not get url from searchResult');
    }

    /**
     * @test
     */
    public function canGetIsElevated()
    {
        $this->assertSame(true, $this->searchResult->getIsElevated(), 'Could not get isElevated from searchResult');
    }
}
