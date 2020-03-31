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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\Score\ScoreCalculationService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class DocumentScoreAnalyzerViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class DocumentScoreAnalyzerViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var ScoreCalculationService
     */
    protected static $scoreService;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('document', SearchResult::class, 'The solr document', true);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $content = '';
        // only check whether a BE user is logged in, don't need to check
        // for enabled score analysis as we wouldn't be here if it was disabled
        $backendUserIsLoggedIn = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if ($backendUserIsLoggedIn === false) {
            return $content;
        }

        $document = $arguments['document'];

        /** @var $resultSet SearchResultSet */
        $resultSet = self::getUsedSearchResultSetFromRenderingContext($renderingContext);
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
