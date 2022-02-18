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

namespace ApacheSolrForTypo3\Solr\Domain\Search\LastSearches;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Session\FrontendUserSession;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

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
        $this->session = $session ?? GeneralUtility::makeInstance(FrontendUserSession::class);
        $this->lastSearchesRepository = $lastSearchesRepository ?? GeneralUtility::makeInstance(LastSearchesRepository::class);
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
     * @throws UnexpectedValueException
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
                throw new UnexpectedValueException(
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
