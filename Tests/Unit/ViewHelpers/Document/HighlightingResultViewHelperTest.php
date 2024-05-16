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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\ViewHelpers\Document;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\Document\HighlightResultViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Traversable;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class HighlightingResultViewHelperTest extends SetUpUnitTestCase
{
    public static function canRenderCreateHighlightSnippedDataProvider(): Traversable
    {
        yield [
            ['hello <em>world</em>', 'hi <em>world</em>'],
            'hello <em>world</em> ### hi <em>world</em>',
            '<em>|</em>',
        ];
        yield [
            ['hello <em>world</em>', 'hi <em>world</em> <h1>somethingelse</h1>'],
            'hello <em>world</em> ### hi <em>world</em> &lt;h1&gt;somethingelse&lt;/h1&gt;',
            '<em>|</em>',
        ];
        yield [
            ['hello <em>world</em>', 'hi <em>world</em> <h1>somethingelse</h1>'],
            'hello &lt;em&gt;world&lt;/em&gt; ### hi &lt;em&gt;world&lt;/em&gt; &lt;h1&gt;somethingelse&lt;/h1&gt;',
            ' ',
        ];
    }

    #[DataProvider('canRenderCreateHighlightSnippedDataProvider')]
    #[Test]
    public function canRenderCreateHighlightSnipped(array $input, $expectedOutput, $configuredWrap)
    {
        /** @var RenderingContextInterface|MockObject $renderingContextMock */
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);

        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::once())->method('getSearchResultsHighlightingFragmentSeparator')->willReturn(
            '###'
        );
        $configurationMock->expects(self::once())->method('getSearchResultsHighlightingWrap')->willReturn(
            $configuredWrap
        );

        $searchRequestMock = $this->createMock(SearchRequest::class);
        $searchRequestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn(
            $configurationMock
        );

        $fakeHighlightedContent = new stdClass();
        $fakeHighlightedContent->foo = new stdClass();
        $fakeHighlightedContent->foo->content = $input;

        $searchMock = $this->createMock(Search::class);
        $searchMock->expects(self::once())->method('getHighlightedContent')->willReturn(
            $fakeHighlightedContent
        );

        $resultSetMock = $this->createMock(SearchResultSet::class);
        $resultSetMock->expects(self::any())->method('getUsedSearchRequest')->willReturn(
            $searchRequestMock
        );

        $resultSetMock->expects(self::any())->method('getUsedSearch')->willReturn(
            $searchMock
        );

        $documentMock = $this->createMock(SearchResult::class);
        $documentMock->expects(self::any())->method('getId')->willReturn('foo');

        $viewHelper = new HighlightResultViewHelper();
        $viewHelper->setRenderingContext($renderingContextMock);
        $viewHelper->setArguments([
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
            'fieldName' => 'content',
        ]);

        $output = $viewHelper->render();
        self::assertSame($expectedOutput, $output);
    }
}
