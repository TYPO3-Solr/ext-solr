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

use function json_encode;

/**
 * Tests for the SOLR_MULTIVALUE cObj.
 */
final class MultivalueTest extends SetUpContentObject
{
    protected function getTestableContentObjectClassName(): string
    {
        return Multivalue::class;
    }

    #[Test]
    public function convertsCommaSeparatedListFromRecordToJsonEncodedArrayOfTrimmedValues(): void
    {
        $list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
        $expected = json_encode(
            [
                'abc',
                'def',
                'ghi',
                'jkl',
                'mno',
                'pqr',
                'stu',
                'vwx',
                'yz',
            ],
        );

        $this->contentObjectRenderer->start(['list' => $list]);

        $actual = $this->contentObjectRenderer->cObjGetSingle(
            Multivalue::CONTENT_OBJECT_NAME,
            [
                'field' => 'list',
                'separator' => ',',
            ],
        );

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function convertsCommaSeparatedListFromValueToJsonEncodedArrayOfTrimmedValues(): void
    {
        $list = 'abc, def, ghi, jkl, mno, pqr, stu, vwx, yz';
        $expected = json_encode(
            [
                'abc',
                'def',
                'ghi',
                'jkl',
                'mno',
                'pqr',
                'stu',
                'vwx',
                'yz',
            ],
        );

        $this->contentObjectRenderer->start([]);

        $actual = $this->contentObjectRenderer->cObjGetSingle(
            Multivalue::CONTENT_OBJECT_NAME,
            [
                'value' => $list,
                'separator' => ',',
            ],
        );

        self::assertEquals($expected, $actual);
    }
}
