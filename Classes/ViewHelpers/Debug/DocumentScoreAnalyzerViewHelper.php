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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Debug;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\Score\ScoreCalculationService;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class DocumentScoreAnalyzerViewHelper
 *
 *
 * @noinspection PhpUnused Used in {@link Resources/Private/Partials/Result/Document.html}
 */
class DocumentScoreAnalyzerViewHelper extends AbstractSolrFrontendViewHelper
{
    protected static ?ScoreCalculationService $scoreService = null;

    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('document', SearchResult::class, 'The solr document', true);
    }

    /**
     * @throws AspectNotFoundException
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function render()
    {
        $content = '';
        // only check whether a BE user is logged in, don't need to check
        // for enabled score analysis as we wouldn't be here if it was disabled
        $backendUserIsLoggedIn = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'isLoggedIn');
        if ($backendUserIsLoggedIn === false) {
            return $content;
        }

        $document = $this->arguments['document'];

        /** @var SearchResultSet $resultSet */
        $resultSet = self::getUsedSearchResultSetFromRenderingContext($this->renderingContext);
        $debugData = '';
        if (
            $resultSet->getUsedSearch()->getDebugResponse() !== null
            && !empty($resultSet->getUsedSearch()->getDebugResponse()->explain)
        ) {
            $debugData = $resultSet->getUsedSearch()->getDebugResponse()->explain->{$document->getId()} ?? '';
        }

        $scoreService = self::getScoreService();
        $queryFields = $resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchQueryQueryFields();
        $content = $scoreService->getRenderedScores($debugData, $queryFields);

        return '<div class="document-score-analysis">' . $content . '</div>';
    }

    protected static function getScoreService(): ScoreCalculationService
    {
        if (isset(self::$scoreService)) {
            return self::$scoreService;
        }

        self::$scoreService = GeneralUtility::makeInstance(ScoreCalculationService::class);
        return self::$scoreService;
    }
}
