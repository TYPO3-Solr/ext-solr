<?php

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Schmidt
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

use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchRequestTest extends UnitTest
{

    /**
     * @var SearchRequest
     */
    protected $searchRequest;

    /**
     * @return void
     */
    public function setUp()
    {
        $this->searchRequest = new SearchRequest();
    }

    /**
     * @test
     */
    public function testGetPageIsNullWhenNothingWasPassed()
    {
        $this->assertNull($this->searchRequest->getPage(), 'Page was expected to be null');
    }

    /**
     * @test
     */
    public function testCanMerge()
    {
        $this->searchRequest = new SearchRequest(array('page' => 2));
        $this->assertSame(2, $this->searchRequest->getPage(), 'Retrieved unexpected page');

        $this->searchRequest->mergeArguments(array('page' => 8));
        $this->assertSame(8, $this->searchRequest->getPage(), 'Page was not properly merged');
    }
}
