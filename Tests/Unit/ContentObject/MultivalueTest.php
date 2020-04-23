<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\ContentObject\Multivalue;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Tests for the SOLR_MULTIVALUE cObj.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MultivalueTest extends UnitTest
{
    /**
     * @var ContentObjectRenderer
     */
    protected $contentObject;

    /**
     * @test
     */
    public function convertsCommaSeparatedListFromRecordToSerializedArrayOfTrimmedValues()
    {
        $GLOBALS['TSFE']->cObjectDepthCounter = 2;

        $list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
        $expected = 'a:9:{i:0;s:3:"abc";i:1;s:3:"def";i:2;s:3:"ghi";i:3;s:3:"jkl";i:4;s:3:"mno";i:5;s:3:"pqr";i:6;s:3:"stu";i:7;s:3:"vwx";i:8;s:2:"yz";}';

        $this->contentObject->start(['list' => $list]);

        $actual = $this->contentObject->cObjGetSingle(
            Multivalue::CONTENT_OBJECT_NAME,
            [
                'field' => 'list',
                'separator' => ','
            ]
        );

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function convertsCommaSeparatedListFromValueToSerializedArrayOfTrimmedValues()
    {
        $list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
        $expected = 'a:9:{i:0;s:3:"abc";i:1;s:3:"def";i:2;s:3:"ghi";i:3;s:3:"jkl";i:4;s:3:"mno";i:5;s:3:"pqr";i:6;s:3:"stu";i:7;s:3:"vwx";i:8;s:2:"yz";}';

        $this->contentObject->start([]);

        $actual = $this->contentObject->cObjGetSingle(
            Multivalue::CONTENT_OBJECT_NAME,
            [
                'value' => $list,
                'separator' => ','
            ]
        );

        $this->assertEquals($expected, $actual);
    }

    protected function setUp()
    {
        // fake a registered hook
        $GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][Multivalue::CONTENT_OBJECT_NAME] = Multivalue::class;

        $GLOBALS['TSFE'] = $this->getDumbMock(TypoScriptFrontendController::class);

        $this->contentObject = $this->getMockBuilder(ContentObjectRenderer::class)
            ->setMethods(['getResourceFactory', 'getEnvironmentVariable'])
            ->setConstructorArgs([$GLOBALS['TSFE']])->getMock();
    }

    protected function tearDown()
    {
        unset($GLOBALS['TSFE']);
    }
}
