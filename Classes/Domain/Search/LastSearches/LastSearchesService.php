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

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * The LastSearchesService is responsible to return the LastSearches from the session or database,
 * depending on the configuration.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class LastSearchesService
{

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @var FrontendUserSession
     */
    protected $session;

    /**
     * @var LastSearchesRepository
     */
    protected $lastSearchesRepository;

    /**
     * @param TypoScriptConfiguration $typoscriptConfiguration
     * @param FrontendUserSession|null $session
     * @param LastSearchesRepository|null $lastSearchesRepository
     */
    public function __construct(TypoScriptConfiguration $typoscriptConfiguration, FrontendUserSession $session = null, LastSearchesRepository $lastSearchesRepository = null)
    {
        $this->configuration = $typoscriptConfiguration;
        $this->session = isset($session) ? $session : GeneralUtility::makeInstance(FrontendUserSession::class);
        $this->lastSearchesRepository = isset($lastSearchesRepository) ? $lastSearchesRepository : GeneralUtility::makeInstance(LastSearchesRepository::class);
    }

    /**
     * Retrieves the last searches from the session or database depending on the configuration.
     *
     * @return array
     */
    public function getLastSearches()
    {
        $lastSearchesKeywords = [];
        $mode   = $this->configuration->getSearchLastSearchesMode();
        $limit  = $this->configuration->getSearchLastSearchesLimit();

        switch ($mode) {
            case 'user':
                $lastSearchesKeywords = $this->getLastSearchesFromSession($limit);
                break;
            case 'global':
                $lastSearchesKeywords = $this->lastSearchesRepository->findAllKeywords($limit);
                break;
        }

        return $lastSearchesKeywords;
    }

    /**
     * Saves the keywords to the last searches in the database or session depending on the configuration.
     *
     * @param string $keywords
     * @throws \UnexpectedValueException
     */
    public function addToLastSearches($keywords)
    {
        $mode = $this->configuration->getSearchLastSearchesMode();
        switch ($mode) {
            case 'user':
                $this->storeKeywordsToSession($keywords);
                break;
            case 'global':
                $this->lastSearchesRepository->add($keywords, (int)$this->configuration->getSearchLastSearchesLimit());
                break;
            default:
                throw new \UnexpectedValueException(
                    'Unknown mode for plugin.tx_solr.search.lastSearches.mode, valid modes are "user" or "global".',
                    1342456570
                );
        }
    }

    /**
     * Gets the last searched keywords from the user's session
     *
     * @param int $limit
     * @return array An array containing the last searches of the current user
     */
    protected function getLastSearchesFromSession($limit)
    {
        $lastSearches = $this->session->getLastSearches();
        $lastSearches = array_slice(array_reverse(array_unique($lastSearches)), 0, $limit);

        return $lastSearches;
    }

    /**
     * Stores the keywords from the current query to the user's session.
     *
     * @param string $keywords The current query's keywords
     * @return void
     */
    protected function storeKeywordsToSession($keywords)
    {
        $currentLastSearches = $this->session->getLastSearches();
        $lastSearches = $currentLastSearches;
        $newLastSearchesCount = array_push($lastSearches, $keywords);

        while ($newLastSearchesCount > $this->configuration->getSearchLastSearchesLimit()) {
            array_shift($lastSearches);
            $newLastSearchesCount = count($lastSearches);
        }

        $this->session->setLastSearches($lastSearches);
    }
}
