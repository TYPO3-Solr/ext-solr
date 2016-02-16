<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Dimitri Ebert <dimitri.ebert@dkd.de>
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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use ApacheSolrForTypo3\Solr\Template;

/**
 * Last searches view command to display a user's last searches or the last
 * searches of all users.
 *
 * @author Dimitri Ebert <dimitri.ebert@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class LastSearchesCommand implements PluginCommand
{

    /**
     * Parent plugin
     *
     * @var Results
     */
    protected $parentPlugin;

    /**
     * Configuration
     *
     * @var array
     */
    protected $configuration;

    /**
     * Constructor.
     *
     * @param CommandPluginBase $parentPlugin Parent plugin object.
     */
    public function __construct(CommandPluginBase $parentPlugin)
    {
        $this->parentPlugin = $parentPlugin;
        $this->configuration = $parentPlugin->typoScriptConfiguration;
    }

    /**
     * Provides the values for the markers for the last search links
     *
     * @return array an array containing values for markers for the last search links template
     */
    public function execute()
    {
        if (!$this->configuration->getSearchLastSearches()) {
            // command is not activated, intended early return
            return null;
        }

        $lastSearches = $this->getLastSearches();
        if (empty($lastSearches)) {
            return null;
        }

        $marker = array(
            'loop_lastsearches|lastsearch' => $lastSearches
        );

        return $marker;
    }

    /**
     * Prepares the content for the last search markers
     *
     * @return array An array with content for the last search markers
     */
    protected function getLastSearches()
    {
        $lastSearchesKeywords = array();
        $mode   = $this->configuration->getSearchLastSearchesMode();
        $limit  = $this->configuration->getSearchLastSearchesLimit();
        switch ($mode) {
            case 'user':
                $lastSearchesKeywords = $this->getLastSearchesFromSession();
                break;
            case 'global':
                $lastSearchesKeywords = $this->getLastSearchesFromDatabase($limit);
                break;
        }
        // fill array for output
        $i = 0;
        $lastSearches = array();
        foreach ($lastSearchesKeywords as $keywords) {
            if (++$i > $limit) {
                break;
            }

            $keywords = stripslashes($keywords);
            $lastSearches[] = array(
                'q' => Template::escapeMarkers($keywords),
                'parameters' => '&q=' . html_entity_decode($keywords,
                        ENT_NOQUOTES, 'UTF-8'),
                'pid' => $this->parentPlugin->getLinkTargetPageId()
            );
        }

        return $lastSearches;
    }

    /**
     * Gets the last searched keywords from the user's session
     *
     * @return array An array containing the last searches of the current user
     */
    protected function getLastSearchesFromSession()
    {
        $lastSearches = $GLOBALS['TSFE']->fe_user->getKey(
            'ses',
            $this->parentPlugin->prefixId . '_lastSearches'
        );

        if (!is_array($lastSearches)) {
            $lastSearches = array();
        }

        $lastSearches = array_reverse(array_unique($lastSearches));

        return $lastSearches;
    }

    /**
     * Gets the last searched keywords from the database
     *
     * @return array An array containing the last searches of the current user
     */
    protected function getLastSearchesFromDatabase($limit = false)
    {
        $limit = $limit ? intval($limit) : false;
        $lastSearchesRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
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
}
