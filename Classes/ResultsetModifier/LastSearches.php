<?php
namespace ApacheSolrForTypo3\Solr\ResultsetModifier;

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

use ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * Logs the keywords from the query into the user's session or the database -
 * depending on configuration.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class LastSearches implements ResultSetModifier
{

    protected $prefix = 'tx_solr';

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;


    /**
     * Does not actually modify the result set, but tracks the search keywords.
     *
     * (non-PHPdoc)
     * @see ResultSetModifier::modifyResultSet()
     * @param \ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand $resultCommand
     * @param array $resultSet
     * @return array
     */
    public function modifyResultSet(
        ResultsCommand $resultCommand,
        array $resultSet
    ) {
        $this->configuration = $resultCommand->getParentPlugin()->getConfiguration();
        $keywords = $resultCommand->getParentPlugin()->getSearchResultSetService()->getSearch()->getQuery()->getKeywordsCleaned();

        $keywords = trim($keywords);
        if (empty($keywords)) {
            return $resultSet;
        }

        switch ($this->configuration->getSearchLastSearchesMode()) {
            case 'user':
                $this->storeKeywordsToSession($keywords);
                break;
            case 'global':
                $this->storeKeywordsToDatabase($keywords);
                break;
            default:
                throw new \UnexpectedValueException(
                    'Unknown mode for plugin.tx_solr.search.lastSearches.mode, valid modes are "user" or "global".',
                    1342456570
                );
        }

        return $resultSet;
    }

    /**
     * Stores the keywords from the current query to the user's session.
     *
     * @param string $keywords The current query's keywords
     * @return void
     */
    protected function storeKeywordsToSession($keywords)
    {
        $currentLastSearches = $GLOBALS['TSFE']->fe_user->getKey(
            'ses',
            $this->prefix . '_lastSearches'
        );

        if (!is_array($currentLastSearches)) {
            $currentLastSearches = array();
        }

        $lastSearches = $currentLastSearches;
        $newLastSearchesCount = array_push($lastSearches, $keywords);

        while ($newLastSearchesCount > $this->configuration->getSearchLastSearchesLimit()) {
            array_shift($lastSearches);
            $newLastSearchesCount = count($lastSearches);
        }

        $GLOBALS['TSFE']->fe_user->setKey(
            'ses',
            $this->prefix . '_lastSearches',
            $lastSearches
        );
    }

    /**
     * Stores the keywords from the current query to the database.
     *
     * @param string $keywords The current query's keywords
     * @return void
     */
    protected function storeKeywordsToDatabase($keywords)
    {
        $nextSequenceId = $this->getNextSequenceId();

        $GLOBALS['TYPO3_DB']->sql_query(
            'INSERT INTO tx_solr_last_searches (sequence_id, tstamp, keywords)
			VALUES ('
            . $nextSequenceId . ', '
            . time() . ', '
            . $GLOBALS['TYPO3_DB']->fullQuoteStr($keywords,
                'tx_solr_last_searches')
            . ')
			ON DUPLICATE KEY UPDATE tstamp = ' . time() . ', keywords = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($keywords,
                'tx_solr_last_searches')
        );
    }

    /**
     * Gets the sequence id for the next search entry.
     *
     * @return integer The id to be used as the next sequence id for storing the last search keywords.
     */
    protected function getNextSequenceId()
    {
        $nextSequenceId = 0;
        $numberOfLastSearchesToLog = $this->configuration['search.']['lastSearches.']['limit'];

        $row = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            '(sequence_id + 1) % ' . $numberOfLastSearchesToLog . ' as next_sequence_id',
            'tx_solr_last_searches',
            '',
            '',
            'tstamp DESC',
            1
        );

        if (!empty($row)) {
            $nextSequenceId = $row[0]['next_sequence_id'];
        }

        return $nextSequenceId;
    }
}
