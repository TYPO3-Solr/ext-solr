<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\Score;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo.renner@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

/**
 * Provides the functionality to calculate scores and renders them in a minimalistic template.
 *
 * @author Ingo Renner <ingo.renner@gmail.com>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class ScoreCalculationService
{

    /**
     * Renders an overview of how the score for a certain document has been
     * calculated.
     *
     * @param string $solrDebugData debug data from the solr response
     * @param string $queryFields
     * @return string The HTML showing the score analysis
     */
    public function getRenderedScores($solrDebugData, $queryFields)
    {
        $highScores = $this->parseScores($solrDebugData, $queryFields);
        return $this->render($highScores);
    }

    /**
     * Renders an array of score objects into an score output.
     *
     * @param array $highScores
     * @return string
     */
    public function render(array $highScores)
    {
        $scores = [];
        $totalScore = 0;

        foreach ($highScores as $highScore) {
            /** @var $highScore Score */
            $scores[] = '
				<td>+ ' . htmlspecialchars($highScore->getScore()) . '</td>
				<td>' . htmlspecialchars($highScore->getFieldName()) . '</td>
				<td>' . htmlspecialchars($highScore->getBoost()) . '</td>';

            $totalScore += $highScore->getScore();
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

    /**
     * Parses the debugData and the queryFields into an array of score objects.
     *
     * @param string $debugData
     * @param string $queryFields
     * @return array[] array of Score
     */
    public function parseScores($debugData, $queryFields)
    {
        $highScores = [];

        /* TODO Provide better parsing
         *
         * parsing could be done line by line,
         * 		* recording indentation level
         * 		* replacing abbreviations
         * 		* replacing phrases like "product of" by mathematical symbols (* or x)
         * 		* ...
         */

        // matches search term weights, ex: 0.42218783 = (MATCH) weight(content:iPod^40.0 in 43), product of:
        $pattern = '/(.*) = weight\(([^ \)]*)/';
        $scoreMatches = [];
        preg_match_all($pattern, $debugData, $scoreMatches);

        foreach ($scoreMatches[0] as $key => $value) {
            // split field from search term
            list($field, $searchTerm) = explode(':', $scoreMatches[2][$key]);

            $currentScoreValue = $scoreMatches[1][$key];

            $scoreWasSetForFieldBefore = isset($highScores[$field]);
            $scoreIsHigher = false;
            if ($scoreWasSetForFieldBefore) {
                /** @var  $previousScore Score */
                $previousScore = $highScores[$field];
                $scoreIsHigher = $previousScore->getScore() < $currentScoreValue;
            }

            // keep track of highest score per search term
            if (!$scoreWasSetForFieldBefore || $scoreIsHigher) {
                $pattern = '/' . preg_quote($field, '/') . '\^([\d.]*)/';
                $boostMatches = [];
                preg_match_all($pattern, $queryFields, $boostMatches);
                $boost = $boostMatches[1][0];
                $highScores[$field] = new Score($boost, $field, $currentScoreValue, $searchTerm);
            }
        }

        return $highScores;
    }
}
