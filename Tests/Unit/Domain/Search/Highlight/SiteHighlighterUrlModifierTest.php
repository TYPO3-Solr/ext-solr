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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Highlight;

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
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar',
            ],
            'cHashIsRemoved' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar',
                'hello world',
                true,
                false,
                'http://mywebsite.de/home/index.html?foo=bar&sword_list%5B0%5D=hello&sword_list%5B1%5D=world&no_cache=1',
            ],
            'cHashIsKept' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar',
                'hello world',
                true,
                true,
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar&sword_list%5B0%5D=hello&sword_list%5B1%5D=world&no_cache=1',
            ],
            'fragmentIsKept' => [
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar#test',
                'hello world',
                true,
                true,
                'http://mywebsite.de/home/index.html?cHash=HHZUUUdfsdf&foo=bar&sword_list%5B0%5D=hello&sword_list%5B1%5D=world&no_cache=1#test',
            ],
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
        self::assertSame($expectedResult, $result);
    }
}
