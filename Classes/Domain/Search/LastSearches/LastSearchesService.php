<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\LastSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * The LastSearchesService is responsible to return the LastSearches from the session or database,
 * depending on the configuration.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class LastSearchesService
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected $tsfe;

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $database;

    /**
     * @param TypoScriptConfiguration $typoscriptConfiguration
     * @param TypoScriptFrontendController $tsfe
     * @param DatabaseConnection $database
     */
    public function __construct(TypoScriptConfiguration $typoscriptConfiguration, TypoScriptFrontendController $tsfe, DatabaseConnection $database)
    {
        $this->configuration = $typoscriptConfiguration;
        $this->tsfe = $tsfe;
        $this->database = $database;
    }

    /**
     * @return array
     */
    public function getLastSearches()
    {
        $lastSearchesKeywords = array();
        $mode   = $this->configuration->getSearchLastSearchesMode();
        $limit  = $this->configuration->getSearchLastSearchesLimit();

        switch ($mode) {
            case 'user':
                $lastSearchesKeywords = $this->getLastSearchesFromSession($limit);
                break;
            case 'global':
                $lastSearchesKeywords = $this->getLastSearchesFromDatabase($limit);
                break;
        }

        return $lastSearchesKeywords;
    }

    /**
     * Gets the last searched keywords from the user's session
     *
     * @param int $limit
     * @return array An array containing the last searches of the current user
     */
    protected function getLastSearchesFromSession($limit)
    {
        $lastSearches = $this->getLastSearchesFromFrontendSession();

        if (!is_array($lastSearches)) {
            return array();
        }

        $lastSearches = array_slice(array_reverse(array_unique($lastSearches)), 0, $limit);

        return $lastSearches;
    }

    /**
     * Gets the last searched keywords from the database
     *
     * @param integer|bool $limit
     * @return array An array containing the last searches of the current user
     */
    protected function getLastSearchesFromDatabase($limit = false)
    {
        $limit = $limit ? intval($limit) : false;
        $lastSearchesRows = $this->database->exec_SELECTgetRows(
            'DISTINCT keywords',
            'tx_solr_last_searches',
            '',
            '',
            'tstamp DESC',
            $limit
        );

        $lastSearches = array();
        foreach ($lastSearchesRows as $row) {
            $lastSearches[] = $row['keywords'];
        }

        return $lastSearches;
    }

    /**
     * @return mixed
     */
    protected function getLastSearchesFromFrontendSession()
    {
        return $this->tsfe->fe_user->getKey('ses', 'tx_solr_lastSearches');
    }
}
