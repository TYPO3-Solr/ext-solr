<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Debug;

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

use ApacheSolrForTypo3\Solr\Domain\Search\Score\ScoreCalculationService;
use ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Score\ScoreCalculationServiceTest;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractViewHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * Class DocumentScoreAnalyzerViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Debug
 */
class DocumentScoreAnalyzerViewHelper extends AbstractViewHelper implements CompilableInterface
{
    /**
     * @var ScoreCalculationService
     */
    protected static $scoreService;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Get document relevance percentage
     *
     * @param \Apache_Solr_Document $document
     * @return string
     */
    public function render(\Apache_Solr_Document $document)
    {
        return self::renderStatic(
            ['document' => $document],
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
        $content = '';
        // only check whether a BE user is logged in, don't need to check
        // for enabled score analysis as we wouldn't be here if it was disabled
        if (empty($GLOBALS['TSFE']->beUserLogin)) {
            return $content;
        }

        $document = $arguments['document'];

        /** @var $resultSet SearchResultSet */
        $resultSet = $renderingContext->getControllerContext()->getSearchResultSet();
        $debugData = $resultSet->getUsedSearch()->getDebugResponse()->explain->{$document->getId()};

        /** @var $scoreService ScoreCalculationService */
        $scoreService = self::getScoreService();
        $queryFields = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchQueryQueryFields();
        $content = $scoreService->getRenderedScores($debugData, $queryFields);

        return '<div class="document-score-analysis">' . $content . '</div>';
    }

    /**
     * @return ScoreCalculationService
     */
    protected static function getScoreService()
    {
        if (isset(self::$scoreService)) {
            return self::$scoreService;
        }

        self::$scoreService = GeneralUtility::makeInstance(ScoreCalculationService::class);
        return self::$scoreService;
    }
}
