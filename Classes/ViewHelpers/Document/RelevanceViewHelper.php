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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Document;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use Closure;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class RelevanceViewHelper
 */
class RelevanceViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @inheritDoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'The context searchResultSet', true);
        $this->registerArgument('document', SearchResult::class, 'The document to highlight', true);
        $this->registerArgument('maximumScore', 'float', 'The maximum score that should be used for percentage calculation, if nothing is passed the maximum from the resultSet is used');
    }

    /**
     * Renders relevance.
     *
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpUnused
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ) {
        /** @var SearchResult $document */
        $document = $arguments['document'];

        /** @var SearchResultSet $resultSet */
        $resultSet = $arguments['resultSet'];

        $configuration = $resultSet->getUsedSearchRequest()?->getContextTypoScriptConfiguration();
        if ($configuration?->isPureVectorSearchEnabled()) {
            $maximumScore = 1;
            $documentScore = $document->getVectorSimilarityScore();
        } else {
            $maximumScore = $arguments['maximumScore'] ?? $resultSet->getMaximumScore();
            $documentScore = $document->getScore();
        }

        $content = 0;
        if ($maximumScore <= 0) {
            return $content;
        }

        $multiplier = 100 / $maximumScore;
        return (string)round($documentScore * $multiplier);
    }
}
