<?php
namespace ApacheSolrForTypo3\Solr\Test\Domain\Search\Highlight;

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

use ApacheSolrForTypo3\Solr\Domain\Search\Highlight\SiteHighlighterUrlModifier;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SiteHighlighterUrlModifierTest extends UnitTest
{

    public function canModifyDataProvider()
    {
        return [
            'nothingChangedWhenNoSearchWordPresent' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar',
                '',
                false,
                true,
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar'
            ],
            'cHashIsRemoved' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar',
                'hello world',
                true,
                false,
                'http://mywebsite.de/home/index.html?foo=bar&sword_list%5B0%5D=hello&sword_list%5B1%5D=world&no_cache=1'
            ],
            'cHashIsKept' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar',
                'hello world',
                true,
                true,
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar&sword_list%5B0%5D=hello&sword_list%5B1%5D=world&no_cache=1'
            ],
            'fragmentIsKept' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar#test',
                'hello world',
                true,
                true,
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar&sword_list%5B0%5D=hello&sword_list%5B1%5D=world&no_cache=1#test'
            ]
        ];

    }

    /**
     * @dataProvider canModifyDataProvider
     * @test
     */
    public function canModify($inputUrl, $keywords, $no_cache, $keepCHash, $expectedResult)
    {
        $siteHighlightingModifier = new SiteHighlighterUrlModifier();
        $result = $siteHighlightingModifier->modify($inputUrl, $keywords, $no_cache, $keepCHash);
        $this->assertSame($expectedResult, $result);
    }
}
