<?php
namespace ApacheSolrForTypo3\Solr\ResultDocumentModifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\Score\ScoreCalculationService;
use ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides an analysis of how the score of a document was calculated below
 * each result document.
 *
 * Caution: Currently only a few values are taken into account during rendering
 *          of the analysis yet. Feel free to contribute better analysis.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class ScoreAnalyzer implements ResultDocumentModifier
{

    /**
     * @var Search
     */
    protected $search;


    /**
     * Modifies the given query and returns the modified query as result
     *
     * @param ResultsCommand $resultCommand The search result command
     * @param array $resultDocument Result document
     * @return array The document with fields as array
     */
    public function modifyResultDocument(
        ResultsCommand $resultCommand,
        array $resultDocument
    ) {
        $this->search = $resultCommand->getParentPlugin()->getSearchResultSetService()->getSearch();

        // only check whether a BE user is logged in, don't need to check
        // for enabled score analysis as we wouldn't be here if it was disabled
        if ($GLOBALS['TSFE']->beUserLogin) {
            $configuration = Util::getSolrConfiguration();
            $queryFields = $configuration->getSearchQueryQueryFields();
            $debugData = $this->search->getDebugResponse()->explain->{$resultDocument['id']};
            $scoreCalculationService = GeneralUtility::makeInstance(ScoreCalculationService::class);
            $resultDocument['score_analysis'] = $scoreCalculationService->getRenderedScores($debugData, $queryFields);
        }

        return $resultDocument;
    }
}
