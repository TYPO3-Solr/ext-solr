<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Document;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class RelevanceViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RelevanceViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('resultSet', SearchResultSet::class, 'The context searchResultSet', true);
        $this->registerArgument('document', SearchResult::class, 'The document to highlight', true);
        $this->registerArgument('maximumScore', 'float', 'The maximum score that should be used for percentage calculation, if nothing is passed the maximum from the resultSet is used', false);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var $document SearchResult */
        $document = $arguments['document'];

            /** @var $resultSet SearchResultSet */
        $resultSet = $arguments['resultSet'];

        $maximumScore = $arguments['maximumScore'] ?? $resultSet->getMaximumScore();
        $content = 0;

        if ($maximumScore <= 0) {
            return $content;
        }

        $documentScore = $document->getScore();
        $score = floatval($documentScore);
        $multiplier = 100 / $maximumScore;
        $scorePercentage = round($score * $multiplier);
        $content = $scorePercentage;
        return $content;
    }
}
