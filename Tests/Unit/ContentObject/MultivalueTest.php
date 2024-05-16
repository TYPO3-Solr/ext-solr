<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ContentObject;

use ApacheSolrForTypo3\Solr\ContentObject\Multivalue;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the SOLR_MULTIVALUE cObj.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class MultivalueTest extends SetUpContentObject
{
    protected function getTestableContentObjectClassName(): string
    {
        return Multivalue::class;
    }

    #[Test]
    public function convertsCommaSeparatedListFromRecordToSerializedArrayOfTrimmedValues(): void
    {
        $list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
        $expected = 'a:9:{i:0;s:3:"abc";i:1;s:3:"def";i:2;s:3:"ghi";i:3;s:3:"jkl";i:4;s:3:"mno";i:5;s:3:"pqr";i:6;s:3:"stu";i:7;s:3:"vwx";i:8;s:2:"yz";}';

        $this->contentObjectRenderer->start(['list' => $list]);

        $actual = $this->contentObjectRenderer->cObjGetSingle(
            Multivalue::CONTENT_OBJECT_NAME,
            [
                'field' => 'list',
                'separator' => ',',
            ]
        );

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function convertsCommaSeparatedListFromValueToSerializedArrayOfTrimmedValues(): void
    {
        $list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
        $expected = 'a:9:{i:0;s:3:"abc";i:1;s:3:"def";i:2;s:3:"ghi";i:3;s:3:"jkl";i:4;s:3:"mno";i:5;s:3:"pqr";i:6;s:3:"stu";i:7;s:3:"vwx";i:8;s:2:"yz";}';

        $this->contentObjectRenderer->start([]);

        $actual = $this->contentObjectRenderer->cObjGetSingle(
            Multivalue::CONTENT_OBJECT_NAME,
            [
                'value' => $list,
                'separator' => ',',
            ]
        );

        self::assertEquals($expected, $actual);
    }
}
