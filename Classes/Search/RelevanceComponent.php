<?php
namespace ApacheSolrForTypo3\Solr\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Query;

/**
 * Boosting search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class RelevanceComponent extends AbstractComponent implements QueryAware
{

    /**
     * Solr query
     *
     * @var Query
     */
    protected $query;

    /**
     * Initializes the search component.
     *
     * Sets minimum match, boost function, boost query and tie breaker.
     *
     */
    public function initializeSearchComponent()
    {
        if (!empty($this->searchConfiguration['query.']['minimumMatch'])) {
            $this->query->setMinimumMatch($this->searchConfiguration['query.']['minimumMatch']);
        }

        if (!empty($this->searchConfiguration['query.']['boostFunction'])) {
            $this->query->setBoostFunction($this->searchConfiguration['query.']['boostFunction']);
        }

        if (!empty($this->searchConfiguration['query.']['boostQuery'])) {
            $this->query->setBoostQuery($this->searchConfiguration['query.']['boostQuery']);
        }

        if (!empty($this->searchConfiguration['query.']['boostQuery.'])) {
            $boostQueries = [];
            $boostConfiguration = $this->searchConfiguration['query.']['boostQuery.'];

            foreach ($boostConfiguration as $query) {
                $boostQueries[] = $query;
            }

            $this->query->setBoostQuery($boostQueries);
        }

        if (!empty($this->searchConfiguration['query.']['tieParameter'])) {
            $this->query->setTieParameter($this->searchConfiguration['query.']['tieParameter']);
        }

        $this->initializePhraseParameters();
        $this->initializeXgramPhraseParameters();
    }

    /**
     * Provides the extension component with an instance of the current query.
     *
     * @param Query $query Current query
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }

    /**
     * Initializes all for phrase search relevant params
     *
     * Folowing Query Parameters:
     *   "ps" Phrase Slop
     *   "qs" Query Phrase Slop
     *
     */
    protected function initializePhraseParameters() {
        if (empty($this->searchConfiguration['query.']['phrase']) || $this->searchConfiguration['query.']['phrase'] !== 1) {
            return;
        }

        if (!empty($this->searchConfiguration['query.']['phrase']['slop'])) {
            $this->query->setPhraseSlopParameter($this->searchConfiguration['query.']['phrase']['slop']);
        }

        if (!empty($this->searchConfiguration['query.']['phrase']['querySlop'])) {
            $this->query->setQueryPhraseSlopParameter($this->searchConfiguration['query.']['phrase']['querySlop']);
        }
    }

    /**
     * Initializes all for bigram phrase search relevant params
     *
     * Folowing Query Parameters:
     *   "ps" Phrase Slop
     *   "qs" Query Phrase Slop
     *
     */
    protected function initializeXgramPhraseParameters() {
        if (!empty($this->searchConfiguration['query.']['bigramPhrase']) && $this->searchConfiguration['query.']['bigramPhrase'] === 1
            && !empty($this->searchConfiguration['query.']['bigramPhrase']['slop'])) {
            $this->query->setQueryPhraseSlopParameter($this->searchConfiguration['query.']['bigramPhrase']['slop']);
        }

        if (!empty($this->searchConfiguration['query.']['trigramPhrase']) && $this->searchConfiguration['query.']['trigramPhrase'] === 1
            && !empty($this->searchConfiguration['query.']['trigramPhrase']['slop'])) {
            $this->query->setQueryPhraseSlopParameter($this->searchConfiguration['query.']['trigramPhrase']['slop']);
        }
    }

}
