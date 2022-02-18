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

/**
 * Tests the HtmlContentExtractor
 *
 * Timo Hund <timo.hund@dkd.de>
 */
class HtmlContentExtractorTest extends UnitTest
{

    /**
     * @test
     */
    public function canGetTagContent()
    {
        $extractor = new HtmlContentExtractor($this->getFixtureContentByName('fixture2.html'));
        $tagContent = $extractor->getTagContent();

        $expectedTagContent = [
            'tagsH1' => 'Level 1 headline',
            'tagsH2H3' => 'Level 2 headline Level 3 headline',
        ];

        self::assertSame($expectedTagContent, $tagContent, 'Extractor did not retrieve expected tag content');
    }

    public function getIndexableContentDataProvider()
    {
        return [
            'unifyWhitespaces' => [
                'websiteContent' => $this->getFixtureContentByName('fixture2.html'),
                'exptectedIndexableContent' => 'Title Level 1 headline Hello World Level 2 headline Level 3 headline',
            ],
            'unifyTabs' => [
                'websiteContent' => "Test\t\tTest",
                'exptectedIndexableContent' => 'Test Test',
            ],
            'removeScriptTags' => [
                'websiteContent' => '<script>foo</script>Test',
                'exptectedIndexableContent' => 'Test',
            ],
            'decodeEntities' => [
                'websiteContent' => 'B&auml;hm',
                'exptectedIndexableContent' => 'Bähm',
            ],
            'decodeSpaceEntities' => [
                'websiteContent' => 'B&auml;hm&nbsp; Bum',
                'exptectedIndexableContent' => 'Bähm Bum',
            ],
            'decodeSpaceUtf8Nbsp' => [
                'websiteContent' => 'test <br/>afterNBSP',
                'exptectedIndexableContent' => 'test afterNBSP',
            ],
        ];
    }

    /**
     * @dataProvider getIndexableContentDataProvider
     * @test
     */
    public function canUnifyWhitespacesInIndexableContent($websiteContent, $expectedIndexableContent)
    {
        $extractor = new HtmlContentExtractor($websiteContent);
        self::assertSame($expectedIndexableContent, $extractor->getIndexableContent(), 'Unexpected indexable content');
    }
}
