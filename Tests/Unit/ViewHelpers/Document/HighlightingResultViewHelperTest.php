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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Document;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\ViewHelpers\Document\HighlightResultViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class HighlightingResultViewHelperTest extends UnitTest
{

    /**
     * @return array
     */
    public function canRenderCreateHighlightSnippedDataProvider()
    {
        return [
            [
                ['hello <em>world</em>', 'hi <em>world</em>'],
                'hello <em>world</em> ### hi <em>world</em>',
                '<em>|</em>',
            ],
            [
                ['hello <em>world</em>', 'hi <em>world</em> <h1>somethingelse</h1>'],
                'hello <em>world</em> ### hi <em>world</em> &lt;h1&gt;somethingelse&lt;/h1&gt;',
                '<em>|</em>',
            ],
            [
                ['hello <em>world</em>', 'hi <em>world</em> <h1>somethingelse</h1>'],
                'hello &lt;em&gt;world&lt;/em&gt; ### hi &lt;em&gt;world&lt;/em&gt; &lt;h1&gt;somethingelse&lt;/h1&gt;',
                ' ',
            ],
        ];
    }

    /**
     * @dataProvider canRenderCreateHighlightSnippedDataProvider
     * @test
     */
    public function canRenderCreateHighlightSnipped(array $input, $expectedOutput, $configuredWrap)
    {
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);

        $configurationMock = $this->getDumbMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchResultsHighlightingFragmentSeparator')->willReturn(
            '###'
        );
        $configurationMock->expects(self::once())->method('getSearchResultsHighlightingWrap')->willReturn(
            $configuredWrap
        );

        $searchRequestMock = $this->getDumbMock(SearchRequest::class);
        $searchRequestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn(
            $configurationMock
        );

        $fakeHighlightedContent = new \stdClass();
        $fakeHighlightedContent->foo = new \stdClass();
        $fakeHighlightedContent->foo->content = $input;

        $searchMock = $this->getDumbMock(Search::class);
        $searchMock->expects(self::once())->method('getHighlightedContent')->willReturn(
            $fakeHighlightedContent
        );

        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $resultSetMock->expects(self::any())->method('getUsedSearchRequest')->willReturn(
            $searchRequestMock
        );

        $resultSetMock->expects(self::any())->method('getUsedSearch')->willReturn(
            $searchMock
        );

        $documentMock = $this->getDumbMock(SearchResult::class);
        $documentMock->expects(self::any())->method('getId')->willReturn('foo');

        $viewHelper = new HighlightResultViewHelper();
        $viewHelper->setRenderingContext($renderingContextMock);
        $viewHelper->setArguments(
            [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
            'fieldName' => 'content', ]
        );

        $output = $viewHelper->render();
        self::assertSame($expectedOutput, $output);
    }
}
