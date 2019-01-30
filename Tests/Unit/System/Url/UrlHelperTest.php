<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\Util;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 Timo Hund <timo.hund@dkd.de>
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
                'expectedUrl' => 'index.php?id=1'
             ],
            'cHash at the beginning' => [
                'input' => 'index.php?cHash=ddd&id=1',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => 'index.php?id=1'
            ],
            'cHash in the middle' => [
                'input' => 'index.php?foo=bar&cHash=ddd&id=1',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => 'index.php?foo=bar&id=1'
            ],
            'result is urlencoded' => [
                'input' => 'index.php?foo[1]=bar&cHash=ddd&id=1',
                'queryParameterToRemove' => 'cHash',
                'expectedUrl' => 'index.php?foo%5B1%5D=bar&id=1'
            ],
            'result is urlencoded with unexisting remove param' => [
                'input' => 'index.php?foo[1]=bar&cHash=ddd&id=1',
                'queryParameterToRemove' => 'notExisting',
                'expectedUrl' => 'index.php?foo%5B1%5D=bar&cHash=ddd&id=1'
            ]
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
        $this->assertSame($expectedUrl, $urlHelper->getUrl(), 'Can not remove query parameter as expected');
    }

    /**
     * @return array
     */
    public function getUrl()
    {
        return [
            'nothing should be changed' => ['inputUrl' => 'index.php?foo%5B1%5D=bar&cHash=ddd&id=1', 'expectedOutputUrl' => 'index.php?foo%5B1%5D=bar&cHash=ddd&id=1'],
            'url should be encoded' => ['inputUrl' => 'index.php?foo[1]=bar&cHash=ddd&id=1', 'expectedOutputUrl' => 'index.php?foo%5B1%5D=bar&cHash=ddd&id=1']
        ];
    }

    /**
     *
     * @dataProvider getUrl
     * @test
     * @param string $inputUrl
     * @param string $expectedOutputUrl
     */
    public function testGetUrl($inputUrl, $expectedOutputUrl)
    {
        $urlHelper = new UrlHelper($inputUrl);
        $this->assertSame($expectedOutputUrl, $urlHelper->getUrl(), 'Can not get expected output url');
    }
}