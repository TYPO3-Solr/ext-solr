<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * View helper class to turn a result document's relevance score into a
 * percent value.
 *
 * Replaces view helpers ###RELEVANCE:###RESULT_DOCUMENT######
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Relevance implements ViewHelper
{

    /**
     * instance of ApacheSolrForTypo3\Solr\Search
     *
     * @var Search
     */
    protected $search = null;

    /**
     * Result set maximum score.
     *
     * @var float
     */
    protected $maxScore = 0.0;


    /**
     * Constructor
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = array())
    {
        if (is_null($this->search)) {
            $this->search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search');
            $this->maxScore = $this->search->getMaximumResultScore();
        }
    }

    /**
     * Creates the HTML for the relevance output
     *
     * @param array $arguments Array of arguments, [0] is expected to contain the result document.
     * @return string The score as percent value.
     */
    public function execute(array $arguments = array())
    {
        $content = '';
        $document = $arguments[0];

        if (count($arguments) > 1) {
            // a pipe character caused the serialized document to be split up
            $document = implode('|', $arguments);
        }

        if ($this->search->hasSearched() && $this->search->getNumberOfResults()) {
            $score = $this->getScore($document);
            $maximumScore = $this->getMaximumScore($document);
            $content = $this->render($score, $maximumScore);
        }

        return $content;
    }

    /**
     * Gets the document's score.
     *
     * @param string $document The result document as serialized array
     * @return float The document's score
     * @throws \RuntimeException if the serialized result document array cannot be unserialized
     */
    protected function getScore($document)
    {
        $rawDocument = $document;
        $score = 0;

        if (is_numeric($document)) {
            // backwards compatibility
            GeneralUtility::deprecationLog('You are using an old notation of the '
                . 'relevance view helpers. The notation used to be '
                . '###RELEVANCE:###RESULT_DOCUMENT.SCORE######, please change '
                . 'this to simply provide the whole result document: '
                . '###RELEVANCE:###RESULT_DOCUMENT######'
            );

            return $document;
        }

        $document = unserialize($document);
        if (is_array($document)) {
            $score = $document['score'];
        } else {
            if ($rawDocument == '###RESULT_DOCUMENT###') {
                // unresolved marker
                // may happen when using search.spellchecking.searchUsingSpellCheckerSuggestion
                // -> ignore
            } else {
                $solrConfiguration = Util::getSolrConfiguration();
                if ($solrConfiguration->getValueByPathOrDefaultValue('plugin.tx_solr.logging.exceptions', false)) {
                    GeneralUtility::devLog('Could not resolve document score for relevance calculation',
                        'solr', 3, array(
                            'rawDocument' => $rawDocument,
                            'unserializedDocument' => $document
                        ));
                }

                throw new \RuntimeException(
                    'Could not resolve document score for relevance calculation',
                    1343670545
                );
            }
        }

        return $score;
    }

    /**
     * Gets the maximum score based on the result set or a group if grouping
     * is activated.
     *
     * @param string $document The result document as serialized array
     * @return float Maximum score
     */
    protected function getMaximumScore($document)
    {
        $maximumScore = $this->maxScore;

        $document = unserialize($document);
        if (is_array($document) && array_key_exists('__solr_grouping_groupMaximumScore',
                $document)
        ) {
            $maximumScore = $document['__solr_grouping_groupMaximumScore'];
        }

        return $maximumScore;
    }

    /**
     * Renders the relevance as percentage value.
     *
     * @param float $documentScore The current document's score
     * @param float $maximumScore The maximum score to relate to.
     * @return string Relevance as percentage value
     */
    protected function render($documentScore, $maximumScore)
    {
        $content = '';

        if ($maximumScore > 0) {
            $score = floatval($documentScore);
            $scorePercentage = round($score * 100 / $maximumScore);
            $content = $scorePercentage;
        }

        return $content;
    }
}
