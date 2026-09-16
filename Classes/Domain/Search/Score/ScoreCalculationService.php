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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Score;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides the functionality to calculate scores and renders them in a minimalistic template.
 */
class ScoreCalculationService
{
    private array $fieldBoostMapping;

    /**
     * Renders an overview in HTML of how the score for a certain document has been calculated by Apache Solr using debug data.
     *
     * @param string $solrDebugData debug data from the solr response
     * @param string $queryFields
     * @return string The HTML showing the score analysis
     */
    public function getRenderedScores(string $solrDebugData, string $queryFields): string
    {
        foreach (GeneralUtility::trimExplode(',', $queryFields, true) as $queryField) {
            list($field, $boost) = explode('^', $queryField);
            $this->fieldBoostMapping[$field] = (float)$boost;
        }

        $solrDebugArray = explode(PHP_EOL, trim($solrDebugData));
        $highScores = $this->parseScores($solrDebugArray);
        return $this->render($highScores);
    }

    /**
     * Renders an array of score objects into a score output.
     */
    public function render(array $highScores): string
    {
        $scores = [];

        $content = '<table class="table">'
            . '<thead><tr><th>Score</th><th>Field</th><th>Boost</th><th>Search term</th></tr></thead>'
            . '<tbody>';

        foreach ($highScores as $highScore) {
            $content .= $this->renderRow($highScore['node'], $level = 0, null);
            foreach ($highScore['children'] ?? [] as $child) {
                $content .= $this->renderRow($child['node'], $level = 1, $highScore['node']);
                foreach ($child['children'] ?? [] as $grandchild) {
                    $content .= $this->renderRow($grandchild['node'], $level = 2, $child['node']);
                    foreach ($grandchild['children'] ?? [] as $greatgrandchild) {
                        $content .= $this->renderRow($greatgrandchild['node'], $level = 3, $grandchild['node']);
                    }
                }
            }
        }

        $content .= '</tbody>'
            . '</table>';

        return $content;
    }

    private function renderRow($node, $level, $parent)
    {
        $style = '';
        if ($parent?->getFieldName() === 'max of') {
            if ($parent->getScore() != $node->getScore()) {
                $style = 'color:gray';
            }
        }
        $pad = str_repeat('&nbsp', $level * 7);
        return '<tr>'
                . '<td style="' . $style . '">' . $pad . '+&nbsp;' . number_format($node->getScore(), 2) . '</td>'
                . '<td style="' . $style . '">' . htmlspecialchars($node->getFieldName()) . '</td>'
                . '<td style="' . $style . '">' . htmlspecialchars($node->getBoost()) . '</td>'
                . '<td style="' . $style . '">' . htmlspecialchars($node->getSearchTerm()) . '</td>'
                .'</tr>';
    }

    /**
     * Recursively turns an array of indented lines into a hierarchical array.
     */
    private function parseScores(array &$lines = [], int $depth = 0, int $failsafe = 0): array
    {
        if ($failsafe >= 1000) {
            die('failsafe');
        }

        $result = [];
        while ($line = current($lines)) {
            $indentation = strlen($line) - strlen(ltrim($line));
            $currentDepth = (int)($indentation / 2);

            if ($currentDepth < $depth) {
                // that's the next parent already!
                break;
            }

            if ($currentDepth == $depth) {
                // that's a sibling
                array_shift($lines);
            }

            if ($currentDepth >= $depth) {
                // that's the first kid
                $result[] = [
                    'node' => $this->parseLine(trim($line)),
                    'children' => $this->parseScores($lines, $depth+1, $failsafe++),
                ];
            }
        }

        return $result;
    }

    /**
     * Parses a single line of score debugging output and
     * transforms it into a Score object.
     */
    private function parseLine(string $line): ?Score
    {
        if (preg_match('/(\d+\.\d+) = weight\((.*)\)/', $line, $weightMatch)) {
            $score = (float)$weightMatch[1];
            $field = '';
            $boost = 0.0;
            $searchTerm = '??';
            if (preg_match('/(\w+):(\w+)/', $weightMatch[2], $match)) {
                $field = $match[1];
                $boost = $this->fieldBoostMapping[$field] ?? 0.0;
                $searchTerm = $match[2];
            } elseif (preg_match('/(\w+):"([\w\ ]+)"/', $weightMatch[2], $match)) {
                $field = $match[1];
                $boost = $this->fieldBoostMapping[$field] ?? 0.0;
                $searchTerm = $match[2];
            }
            $score = new Score($boost, $field, $score, $searchTerm);
        } elseif (preg_match('/(\d+\.\d+) = sum of:/', $line, $match)) {
            $score = (float)$match[1];
            $score = new Score(0.0, 'sum of', $score, '');
        } elseif (preg_match('/(\d+\.\d+) = max of:/', $line, $match)) {
            $score = (float)$match[1];
            $score = new Score(0.0, 'max of', $score, '');
        } elseif (preg_match('/(\d+\.\d+) = FunctionQuery\((.*)\),/', $line, $match)) {
            $score = (float)$match[1];
            $function = $match[2];
            $score = new Score(0.0, 'boostFunction', $score, $function);
        } elseif (preg_match('/(\d+\.\d+) = (.*)/', $line, $match)) {
            $score = (float)$match[1];
            $misc = $match[2];
            $score = new Score(0.0, '', $score, $misc);
        } else {
            $score = null;
        }

        return $score;
    }
}
