<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * No Results found view command
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class NoResultsCommand implements PluginCommand
{

    /**
     * Parent plugin
     *
     * @var Results
     */
    protected $parentPlugin;


    /**
     * Constructor.
     *
     * @param CommandPluginBase $parentPlugin Parent plugin object.
     */
    public function __construct(CommandPluginBase $parentPlugin)
    {
        $this->parentPlugin = $parentPlugin;
    }

    public function execute()
    {
        $spellChecker = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\SpellChecker');
        $suggestionsLink = $spellChecker->getSpellcheckingSuggestions();

        $markers = $this->getLabelMarkers();

        if ($this->parentPlugin->typoScriptConfiguration->getSearchSpellcheckingSearchUsingSpellCheckerSuggestion()) {
            $suggestionResults =  $this->getSuggestionResults();
            $markers['suggestion_results'] = $suggestionResults;
            $markers['has_suggestion_results'] = trim($suggestionResults) !== '';
        }

        // TODO change to if $spellChecker->hasSuggestions()
        if (!empty($suggestionsLink)) {
            $markers['suggestion'] = $suggestionsLink;
        }

        return $markers;
    }

    /**
     * Constructs label markers.
     *
     * @return array Array of label markers.
     */
    protected function getLabelMarkers()
    {
        $spellChecker = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\SpellChecker');
        $searchWord = $this->parentPlugin->getCleanUserQuery();

        $nothingFound = strtr(
            $this->parentPlugin->pi_getLL('no_results_nothing_found'),
            array(
                '@searchWord' => $searchWord
            )
        );

        $showingResultsSuggestion = strtr(
            $this->parentPlugin->pi_getLL('no_results_showing_results_suggestion'),
            array(
                '@suggestedWord' => $spellChecker->getCollatedSuggestion()
            )
        );

        # TODO add link to execute query
        $searchForOriginal = strtr(
            $this->parentPlugin->pi_getLL('no_results_search_for_original'),
            array(
                '@searchWord' => $searchWord
            )
        );

        $searchedFor = strtr(
            $this->parentPlugin->pi_getLL('results_searched_for'),
            array(
                '@searchWord' => $searchWord
            )
        );

        $markers = array(
            'query' => $searchWord,
            'nothing_found' => $nothingFound,
            'showing_results_suggestion' => $showingResultsSuggestion,
            'search_for_original' => $searchForOriginal,
            'searched_for' => $searchedFor,
        );

        return $markers;
    }

    /**
     * Gets the results for the suggested keywords.
     *
     * Conducts a new search using the suggested keywords and uses that search
     * to render the regular results command.
     *
     * @return string The rendered results command for the results of the suggested keywords.
     */
    protected function getSuggestionResults()
    {
        $spellChecker = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\SpellChecker');
        $suggestedKeywords = $spellChecker->getCollatedSuggestion();
        $suggestionResults = '';

        if (empty($suggestedKeywords)) {
            return $suggestionResults;
        }

        $plugin = $this->parentPlugin;
        $searchResultSetService = $this->parentPlugin->getSearchResultSetService();
        // repeat the last search with suggestion as query string
        $usedRequest = $searchResultSetService->getLastResultSet()->getUsedSearchRequest();
        $usedRequest->setRawQueryString($suggestedKeywords);
        $searchResultSetService->search($usedRequest);

        $resultsCommand = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Plugin\\Results\\ResultsCommand', $plugin
        );
        $commandVariables = $resultsCommand->execute();
        $suggestionResults = $plugin->renderCommand('results', $commandVariables);

        return $suggestionResults;
    }
}
