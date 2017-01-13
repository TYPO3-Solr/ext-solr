<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Query\LinkBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Spell checker / Did you mean
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class SpellChecker
{

    /**
     * Search instance
     *
     * @var Search
     */
    protected $search;

    /**
     * Configuration
     *
     * @var array
     */
    protected $configuration;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->search = GeneralUtility::makeInstance(Search::class);
        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Gets the collated suggestion
     *
     * @return string collated suggestion
     */
    public function getCollatedSuggestion()
    {
        $suggestions = $this->search->getSpellcheckingSuggestions();

        return $suggestions['collation'];
    }

    /**
     * Checks whether the user's query was correctly spelled.
     *
     * @return bool TRUE if the query terms were correctly spelled, FALSE otherwise
     */
    public function isIncorrectlySpelled()
    {
        $suggestions = $this->getSuggestions();

        return $suggestions['correctlySpelled'];
    }

    /**
     * Gets the raw spellchecking suggestions
     *
     * @return array Array of suggestions
     */
    public function getSuggestions()
    {
        $suggestions = $this->search->getSpellcheckingSuggestions();

        return $suggestions;
    }

    /**
     * Query URL with a suggested/corrected query
     *
     * @return string Suggestion/spell checked query URL
     */
    public function getSuggestionQueryUrl()
    {
        $suggestions = $this->getSuggestions();

        $query = clone $this->search->getQuery();
        $query->setKeywords($suggestions['collation']);

        $queryLinkBuilder = GeneralUtility::makeInstance(LinkBuilder::class, $query);
        $queryLinkBuilder->setLinkTargetPageId($GLOBALS['TSFE']->id);

        return $queryLinkBuilder->getQueryUrl();
    }

    /**
     * Generates a link with spell checking suggestions if it is activated and
     * spell checking suggestions are returned by Solr.
     *
     * @return string A link to start over with a new search using the suggested keywords.
     */
    public function getSpellCheckingSuggestions()
    {
        $suggestionsLink = '';

        if ($this->configuration->getSearchSpellchecking() && $this->search->hasSearched()) {
            $suggestions = $this->getSuggestions();
            if ($suggestions && !$suggestions['correctlySpelled'] && !empty($suggestions['collation'])) {
                $suggestionsLink = $GLOBALS['TSFE']->cObj->noTrimWrap(
                    $this->getSuggestionQueryLink(),
                    $this->configuration->getSearchSpellcheckingWrap()
                );
            }
        }

        return $suggestionsLink;
    }

    /**
     * Query link with a suggested/corrected query
     *
     * @return string Suggestion/spell checked query link
     */
    public function getSuggestionQueryLink()
    {
        $suggestions = $this->getSuggestions();

        $query = clone $this->search->getQuery();
        $query->setKeywords($suggestions['collation']);

        $queryLinkBuilder = GeneralUtility::makeInstance(LinkBuilder::class, $query);
        $queryLinkBuilder->setLinkTargetPageId($GLOBALS['TSFE']->id);

        return $queryLinkBuilder->getQueryLink(htmlspecialchars($query->getKeywordsRaw()));
    }
}
