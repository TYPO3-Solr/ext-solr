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

use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractViewHelper;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * Class RelevanceViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Document
 */
class RelevanceViewHelper extends AbstractViewHelper implements CompilableInterface
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Get document relevance percentage
     *
     * @param SearchResultSet $resultSet
     * @param \Apache_Solr_Document $document
     * @return int
     */
    public function render(SearchResultSet $resultSet, \Apache_Solr_Document $document)
    {
        return self::renderStatic(
            [
                'resultSet' => $resultSet,
                'document' => $document
            ],
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * @param array $arguments
     * @param callable $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var $document \Apache_Solr_Document */
        $document = $arguments['document'];

            /** @var $resultSet SearchResultSet */
        $resultSet = $arguments['resultSet'];

        $maximumScore = $document->__solr_grouping_groupMaximumScore ? : $resultSet->getUsedSearch()->getMaximumResultScore();
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
