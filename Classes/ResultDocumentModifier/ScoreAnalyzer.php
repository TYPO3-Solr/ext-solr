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

use ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Util;

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
            $highScores = $this->analyzeScore($resultDocument);
            $resultDocument['score_analysis'] = $this->renderScoreAnalysis($highScores);
        }

        return $resultDocument;
    }

    protected function analyzeScore(array $resultDocument)
    {
        $highScores = array();
        $debugData = $this->search->getDebugResponse()->explain->{$resultDocument['id']};

        /* TODO Provide better parsing
         *
         * parsing could be done line by line,
         * 		* recording indentation level
         * 		* replacing abbreviations
         * 		* replacing phrases like "product of" by mathematical symbols (* or x)
         * 		* ...
         */

        // matches search term weights, ex: 0.42218783 = (MATCH) weight(content:iPod^40.0 in 43), product of:
        $pattern = '/(.*) = \(MATCH\) weight\((.*)\^/';
        $matches = array();
        preg_match_all($pattern, $debugData, $matches);

        foreach ($matches[0] as $key => $value) {
            // split field from search term
            list($field, $searchTerm) = explode(':', $matches[2][$key]);

            // keep track of highest score per search term
            if (!isset($highScores[$field])
                || $highScores[$field]['score'] < $matches[1][$key]
            ) {
                $highScores[$field] = array(
                    'score' => $matches[1][$key],
                    'field' => $field,
                    'searchTerm' => $searchTerm
                );
            }
        }

        return $highScores;
    }

    /**
     * Renders an overview of how the score for a certain document has been
     * calculated.
     *
     * @param array $highScores The result document which to analyse
     * @return string The HTML showing the score analysis
     */
    protected function renderScoreAnalysis(array $highScores)
    {
        $configuration = Util::getSolrConfiguration();
        $content = '';
        $scores = array();
        $totalScore = 0;

        foreach ($highScores as $field => $highScore) {
            $pattern = '/' . $highScore['field'] . '\^([\d.]*)/';
            $matches = array();
            preg_match_all($pattern,
                $configuration->getSearchQueryQueryFields(), $matches);

            $scores[] = '
				<td>+ ' . $highScore['score'] . '</td>
				<td>' . $highScore['field'] . '</td>
				<td>' . $matches[1][0] . '</td>';

            $totalScore += $highScore['score'];
        }

        $content = '<table style="width: 100%; border: 1px solid #aaa; font-size: 11px; background-color: #eee;">
			<tr style="border-bottom: 2px solid #aaa; font-weight: bold;"><td>Score</td><td>Field</td><td>Boost</td></tr><tr>'
            . implode('</tr><tr>', $scores)
            . '</tr>
			<tr><td colspan="3"><hr style="border-top: 1px solid #aaa; height: 0; padding: 0; margin: 0;" /></td></tr>
			<tr><td colspan="3">= ' . $totalScore . ' (Inaccurate analysis! Not all parts of the score have been taken into account.)</td></tr>
			</table>';

        return $content;
    }
}
