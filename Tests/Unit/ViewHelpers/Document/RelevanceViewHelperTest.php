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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\Document\RelevanceViewHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class RelevanceViewHelperTest extends SetUpUnitTestCase
{
    #[Test]
    public function canCalculateRelevance(): void
    {
        $resultSetMock = $this->getSearchResultSetMock();
        $resultSetMock->expects(self::any())->method('getMaximumScore')->willReturn(5.5);

        $documentMock = $this->createMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getScore')->willReturn(0.55);

        $arguments = [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
        ];
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $score = RelevanceViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);

        self::assertEquals(10.0, $score, 'Unexpected score');
    }

    #[Test]
    public function canCalculateRelevanceFromPassedMaximumScore(): void
    {
        $resultSetMock = $this->getSearchResultSetMock();
        $resultSetMock->expects(self::never())->method('getMaximumScore');

        $documentMock = $this->createMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getScore')->willReturn(0.55);

        $arguments = [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
            'maximumScore' => 11,
        ];
        $renderingContextMock = $this->createMock(RenderingContextInterface::class);
        $score = RelevanceViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);

        self::assertEquals(5.0, $score, 'Unexpected score');
    }

    #[Test]
    public function canCalculateRelevanceFromVectorSimilarityScore(): void
    {
        $resultSetMock = $this->getSearchResultSetMock(true);

        $documentMock = $this->createMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getVectorSimilarityScore')->willReturn(0.85);
        $documentMock->expects(self::never())->method('getScore');

        $relevanceViewHelperTestable = new RelevanceViewHelper();
        $relevanceViewHelperTestable->setRenderingContext($this->createMock(RenderingContextInterface::class));
        $relevanceViewHelperTestable->setArguments([
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
        ]);

        self::assertEquals(85.0, $relevanceViewHelperTestable->render());
    }

    protected function getSearchResultSetMock(bool $vectorSearchEnabled = false): MockObject&SearchResultSet
    {
        $typoscriptConfigurationMock = $this->createMock(TypoScriptConfiguration::class);
        $typoscriptConfigurationMock->method('isPureVectorSearchEnabled')->willReturn($vectorSearchEnabled);

        $searchRequestMock = $this->createMock(SearchRequest::class);
        $searchRequestMock->method('getContextTypoScriptConfiguration')->willReturn($typoscriptConfigurationMock);

        $resultSetMock = $this->createMock(SearchResultSet::class);
        $resultSetMock->expects(self::once())->method('getUsedSearchRequest')->willReturn($searchRequestMock);
        return $resultSetMock;
    }
}
