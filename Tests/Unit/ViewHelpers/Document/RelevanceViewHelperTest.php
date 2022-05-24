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
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use ApacheSolrForTypo3\Solr\ViewHelpers\Document\RelevanceViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RelevanceViewHelperTest extends UnitTest
{

    /**
     * @test
     */
    public function canCalculateRelevance()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $resultSetMock->expects(self::any())->method('getMaximumScore')->willReturn(5.5);

        $documentMock = $this->getDumbMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getScore')->willReturn(0.55);

        $arguments = [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
        ];
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $score = RelevanceViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);

        self::assertEquals(10.0, $score, 'Unexpected score');
    }

    /**
     * @test
     */
    public function canCalculateRelevanceFromPassedMaximumScore()
    {
        $resultSetMock = $this->getDumbMock(SearchResultSet::class);
        $resultSetMock->expects(self::never())->method('getMaximumScore');

        $documentMock = $this->getDumbMock(SearchResult::class);
        $documentMock->expects(self::once())->method('getScore')->willReturn(0.55);

        $arguments = [
            'resultSet' => $resultSetMock,
            'document' => $documentMock,
            'maximumScore' => 11,
        ];
        $renderingContextMock = $this->getDumbMock(RenderingContextInterface::class);
        $score = RelevanceViewHelper::renderStatic($arguments, function () {}, $renderingContextMock);

        self::assertEquals(5.0, $score, 'Unexpected score');
    }
}
