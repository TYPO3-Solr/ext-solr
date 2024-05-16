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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\HtmlContentExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;

/**
 * Tests the HtmlContentExtractor
 *
 * Timo Hund <timo.hund@dkd.de>
 */
class HtmlContentExtractorTest extends SetUpUnitTestCase
{
    #[Test]
    public function canGetTagContent(): void
    {
        $extractor = new HtmlContentExtractor(self::getFixtureContentByName('fixture2.html'));
        $tagContent = $extractor->getTagContent();

        $expectedTagContent = [
            'tagsH1' => 'Level 1 headline',
            'tagsH2H3' => 'Level 2 headline Level 3 headline',
        ];

        self::assertSame($expectedTagContent, $tagContent, 'Extractor did not retrieve expected tag content');
    }

    public static function getIndexableContentDataProvider(): Traversable
    {
        yield 'unifyWhitespaces' => [
            'websiteContent' => static::getFixtureContentByName('fixture2.html'),
            'expectedIndexableContent' => 'Title Level 1 headline Hello World Level 2 headline Level 3 headline',
        ];
        yield 'unifyTabs' => [
            'websiteContent' => "Test\t\tTest",
            'expectedIndexableContent' => 'Test Test',
        ];
        yield 'removeScriptTags' => [
            'websiteContent' => '<script>foo</script>Test',
            'expectedIndexableContent' => 'Test',
        ];
        yield 'decodeEntities' => [
            'websiteContent' => 'B&auml;hm',
            'expectedIndexableContent' => 'Bähm',
        ];
        yield 'decodeSpaceEntities' => [
            'websiteContent' => 'B&auml;hm&nbsp; Bum',
            'expectedIndexableContent' => 'Bähm Bum',
        ];
        yield 'decodeSpaceUtf8Nbsp' => [
            'websiteContent' => 'test <br/>afterNBSP',
            'expectedIndexableContent' => 'test afterNBSP',
        ];
    }

    #[DataProvider('getIndexableContentDataProvider')]
    #[Test]
    public function canUnifyWhitespacesInIndexableContent($websiteContent, $expectedIndexableContent): void
    {
        $extractor = new HtmlContentExtractor($websiteContent);
        self::assertSame($expectedIndexableContent, $extractor->getIndexableContent(), 'Unexpected indexable content');
    }
}
