<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit;

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

use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tests the TYPO3 page content extractor
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Typo3PageContentExtractorTest extends UnitTest
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $typoScripConfigurationMock;

    public function setUp()
    {
        $this->typoScripConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->typoScripConfigurationMock->expects($this->once())->method(
            'getIndexQueuePagesExcludeContentByClassArray'
        )->will($this->returnValue(['typo3-search-exclude']));
    }

    /**
     * @test
     */
    public function changesNbspToSpace()
    {
        $content = '<!-- TYPO3SEARCH_begin -->In Olten&nbsp;ist<!-- TYPO3SEARCH_end -->';
        $expectedResult = 'In Olten ist';

        $contentExtractor = GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $content);
        $contentExtractor->setConfiguration($this->typoScripConfigurationMock);
        $actualResult = $contentExtractor->getIndexableContent();
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function canExcludeContentByClass()
    {
        $content = '<!-- TYPO3SEARCH_begin --><div class="typo3-search-exclude">Exclude content</div><p>Expected content</p><!-- TYPO3SEARCH_end -->';
        $expectedResult = '<!-- TYPO3SEARCH_begin --><p>Expected content</p><!-- TYPO3SEARCH_end -->';

        $contentExtractor = GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $content);
        $contentExtractor->setConfiguration($this->typoScripConfigurationMock);

        $actualResult = $contentExtractor->excludeContentByClass($content);
        $this->assertEquals($expectedResult, $actualResult);
    }

    /**
     * @test
     */
    public function excludeContentKeepsEncodingForUmlaut()
    {
        $content = '<!-- TYPO3SEARCH_begin --><div class="typo3-search-exclude">Remove me</div><p>Was ein schöner Tag</p><!-- TYPO3SEARCH_end -->';

        $contentExtractor = GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $content);
        $contentExtractor->setConfiguration($this->typoScripConfigurationMock);

        $actualResult = $contentExtractor->excludeContentByClass($content);

        $this->assertContains('Was ein schöner Tag', $actualResult);
        $this->assertNotContains('Remove me', $actualResult);
    }

    /**
     * @test
     */
    public function excludeContentKeepsEncodingForEuroSign()
    {
        $content = '<!-- TYPO3SEARCH_begin --><div class="typo3-search-exclude">Remove me</div><p>100€</p><!-- TYPO3SEARCH_end -->';

        $contentExtractor = GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $content);
        $contentExtractor->setConfiguration($this->typoScripConfigurationMock);

        $actualResult = $contentExtractor->excludeContentByClass($content);

        $this->assertContains('100€', $actualResult);
        $this->assertNotContains('Remove me', $actualResult);
    }

    public function canGetIndexableContentDataProvider() {
        return [
            'can extract simple text' => [
                'content' => '<p>Hello solr for TYPO3</p>',
                'expectedResult' => 'Hello solr for TYPO3'
            ],
            'can extract umlauts' => [
                'content' => '<p>Heute ist ein sch&ouml;ner tag</p>',
                'expectedResult' => 'Heute ist ein schöner tag'
            ],
            'can extract subtag content' => [
                'content' => '<p>Heute ist ein <strong>sch&ouml;ner</strong> tag</p>',
                'expectedResult' => 'Heute ist ein schöner tag'
            ],
            'removes inline styles' => [
                'content' => '<style> body { background-color: linen; }</style><p>Heute ist ein <strong>sch&ouml;ner</strong> tag</p>',
                'expectedResult' => 'Heute ist ein schöner tag'
            ],
            'removes a line break' => [
                'content' => '<p>If <b>the value</b> is <br/> please contact me</p>',
                'expectedResult' => 'If the value is please contact me'
            ],
            'keep less then character' => [
                'content' => '<p>If <b>the value</b> is &lt;50 please contact me</p>',
                'expectedResult' => 'If the value is <50 please contact me'
            ],
            'keep escaped html' => [
                'content' => '<em>this</em> is how to make &lt;b&gt;fat&lt;/b&gt;',
                'expectedResult' => 'this is how to make <b>fat</b>'
            ]
        ];
    }

    /**
     *
     * @dataProvider canGetIndexableContentDataProvider
     * @test
     */
    public function canGetIndexableContent($content, $expectedResult)
    {
        $content = '<!-- TYPO3SEARCH_begin -->' . $content . '<!-- TYPO3SEARCH_end -->';

        $contentExtractor = GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $content);
        $contentExtractor->setConfiguration($this->typoScripConfigurationMock);

        $actualResult = $contentExtractor->getIndexableContent();
        $this->assertContains($expectedResult, $actualResult);
    }
}
