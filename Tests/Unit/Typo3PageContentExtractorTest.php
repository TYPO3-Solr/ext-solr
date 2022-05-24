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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
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

    protected function setUp(): void
    {
        $this->typoScripConfigurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $this->typoScripConfigurationMock->expects(self::once())->method(
            'getIndexQueuePagesExcludeContentByClassArray'
        )->willReturn(['typo3-search-exclude']);
        parent::setUp();
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
        self::assertEquals($expectedResult, $actualResult);
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
        self::assertEquals($expectedResult, $actualResult);
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

        self::assertStringContainsString('Was ein schöner Tag', $actualResult);
        self::assertStringNotContainsString('Remove me', $actualResult);
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

        self::assertStringContainsString('100€', $actualResult);
        self::assertStringNotContainsString('Remove me', $actualResult);
    }

    public function canGetIndexableContentDataProvider()
    {
        return [
            'can extract simple text' => [
                'content' => '<p>Hello solr for TYPO3</p>',
                'expectedResult' => 'Hello solr for TYPO3',
            ],
            'can extract umlauts' => [
                'content' => '<p>Heute ist ein sch&ouml;ner tag</p>',
                'expectedResult' => 'Heute ist ein schöner tag',
            ],
            'can extract subtag content' => [
                'content' => '<p>Heute ist ein <strong>sch&ouml;ner</strong> tag</p>',
                'expectedResult' => 'Heute ist ein schöner tag',
            ],
            'removes inline styles' => [
                'content' => '<style> body { background-color: linen; }</style><p>Heute ist ein <strong>sch&ouml;ner</strong> tag</p>',
                'expectedResult' => 'Heute ist ein schöner tag',
            ],
            'removes a line break' => [
                'content' => '<p>If <b>the value</b> is <br/> please contact me</p>',
                'expectedResult' => 'If the value is please contact me',
            ],
            'keep less then character' => [
                'content' => '<p>If <b>the value</b> is &lt;50 please contact me</p>',
                'expectedResult' => 'If the value is <50 please contact me',
            ],
            'keep escaped html' => [
                'content' => '<em>this</em> is how to make &lt;b&gt;fat&lt;/b&gt;',
                'expectedResult' => 'this is how to make <b>fat</b>',
            ],
            'support chinese characters' => [
                'content' => '媒体和投资者 新闻 财务报告 公司股票信息 概览',
                'expectedResult' => '媒体和投资者 新闻 财务报告 公司股票信息 概览',
            ],
        ];
    }

    /**
     * @dataProvider canGetIndexableContentDataProvider
     * @test
     */
    public function canGetIndexableContent($content, $expectedResult)
    {
        $content = '<!-- TYPO3SEARCH_begin -->' . $content . '<!-- TYPO3SEARCH_end -->';

        $contentExtractor = GeneralUtility::makeInstance(Typo3PageContentExtractor::class, $content);
        $contentExtractor->setConfiguration($this->typoScripConfigurationMock);

        $actualResult = $contentExtractor->getIndexableContent();
        self::assertStringContainsString($expectedResult, $actualResult);
    }
}
