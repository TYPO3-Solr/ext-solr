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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Util;

use ApacheSolrForTypo3\Solr\System\Url\UrlHelper;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to check the functionallity of the UrlHelper
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class UrlHelperTest extends UnitTest
{

    /**
     * @return array
     */
    public function removeQueryParameter()
    {
        return [
            'cHash at the end' => [
                'input' => 'index.php?id=1&cHash=ddd',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => '/index.php?id=1',
             ],
            'cHash at the beginning' => [
                'input' => 'index.php?cHash=ddd&id=1',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => '/index.php?id=1',
            ],
            'cHash in the middle' => [
                'input' => 'index.php?foo=bar&cHash=ddd&id=1',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => '/index.php?foo=bar&id=1',
            ],
            'result is urlencoded' => [
                'input' => 'index.php?foo[1]=bar&cHash=ddd&id=1',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => '/index.php?foo%5B1%5D=bar&id=1',
            ],
            'result is urlencoded with unexisting remove param' => [
                'input' => 'index.php?foo[1]=bar&cHash=ddd&id=1',
                'queryParameterToRemove' => 'notExisting',
                'expectedUrl' => '/index.php?foo%5B1%5D=bar&cHash=ddd&id=1',
            ],
        ];
    }
    /**
     * @dataProvider removeQueryParameter
     * @test
     */
    public function testCanRemoveQueryParameter($input, $queryParameterToRemove, $expectedUrl)
    {
        $urlHelper = new UrlHelper($input);
        $urlHelper->removeQueryParameter($queryParameterToRemove);
        self::assertSame($expectedUrl, $urlHelper->getUrl(), 'Can not remove query parameter as expected');
    }

    /**
     * @return array
     */
    public function getUrl()
    {
        return [
            'nothing should be changed' => ['inputUrl' => 'index.php?foo%5B1%5D=bar&cHash=ddd&id=1', 'expectedOutputUrl' => '/index.php?foo%5B1%5D=bar&cHash=ddd&id=1'],
            'url should be encoded' => ['inputUrl' => 'index.php?foo[1]=bar&cHash=ddd&id=1', 'expectedOutputUrl' => '/index.php?foo%5B1%5D=bar&cHash=ddd&id=1'],
            'url with https protocol' => ['inputUrl' => 'https://www.google.de/index.php', 'expectedOutputUrl' => 'https://www.google.de/index.php'],
            'url with port' => ['inputUrl' => 'http://www.google.de:8080/index.php', 'expectedOutputUrl' => 'http://www.google.de:8080/index.php'],
        ];
    }

    /**
     * @dataProvider getUrl
     * @test
     * @param string $inputUrl
     * @param string $expectedOutputUrl
     */
    public function testGetUrl($inputUrl, $expectedOutputUrl)
    {
        $urlHelper = new UrlHelper($inputUrl);
        self::assertSame($expectedOutputUrl, $urlHelper->getUrl(), 'Can not get expected output url');
    }

    /**
     * @test
     */
    public function testSetHost()
    {
        $urlHelper = new UrlHelper('http://www.google.de/test/index.php?foo=bar');
        $urlHelper->setHost('www.test.de');
        self::assertSame('http://www.test.de/test/index.php?foo=bar', $urlHelper->getUrl());
    }

    /**
     * @test
     */
    public function testSetHostWithPort()
    {
        $urlHelper = new UrlHelper('http://www.google.de/test/index.php?foo=bar');
        $urlHelper->setHost('www.test.de:8080');
        self::assertSame('http://www.test.de:8080/test/index.php?foo=bar', $urlHelper->getUrl());
    }

    /**
     * @test
     */
    public function testSetScheme()
    {
        $urlHelper = new UrlHelper('http://www.google.de/test/index.php?foo=bar');
        $urlHelper->setScheme('https');
        self::assertSame('https://www.google.de/test/index.php?foo=bar', $urlHelper->getUrl());
    }

    /**
     * @test
     */
    public function testSetPath()
    {
        $urlHelper = new UrlHelper('http://www.google.de/one/two?foo=bar');
        $urlHelper->setPath('/one/two');
        self::assertSame('http://www.google.de/one/two?foo=bar', $urlHelper->getUrl());
    }

    public function unmodifiedUrl()
    {
        return [
            'noQuery' => ['http://www.site.de/en/test'],
            'withQuery' => ['http://www.site.de/en/test?id=1'],
            'withQueries' => ['http://www.site.de/en/test?id=1&L=2'],

        ];
    }
    /**
     * @dataProvider unmodifiedUrl
     */
    public function testGetUnmodifiedUrl($uri)
    {
        $urlHelper = new UrlHelper($uri);
        self::assertSame($uri, $urlHelper->getUrl(), 'Could not get unmodified url');
    }
}
