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
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use ApacheSolrForTypo3\Solr\ViewHelpers\Document\RelevanceViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

class RelevanceViewHelperTest extends SetUpUnitTestCase
{
    #[Test]
    public function canCalculateRelevance(): void
    {
        $resultSetMock = $this->createMock(SearchResultSet::class);
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
        $resultSetMock = $this->createMock(SearchResultSet::class);
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
}
