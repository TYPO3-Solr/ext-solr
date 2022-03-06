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
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DBALException;
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
    protected TypoScriptConfiguration $configuration;

    /**
     * @var FrontendUserSession
     */
    protected FrontendUserSession $session;

    /**
     * @var LastSearchesRepository
     */
    protected LastSearchesRepository $lastSearchesRepository;

    /**
     * @param TypoScriptConfiguration $typoscriptConfiguration
     * @param FrontendUserSession|null $session
     * @param LastSearchesRepository|null $lastSearchesRepository
     */
    public function __construct(
        TypoScriptConfiguration $typoscriptConfiguration,
        FrontendUserSession $session = null,
        LastSearchesRepository $lastSearchesRepository = null
    ) {
        $this->configuration = $typoscriptConfiguration;
        $this->session = $session ?? GeneralUtility::makeInstance(FrontendUserSession::class);
        $this->lastSearchesRepository = $lastSearchesRepository ?? GeneralUtility::makeInstance(LastSearchesRepository::class);
    }

    /**
     * Retrieves the last searches from the session or database depending on the configuration.
     *
     * @return array
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function getLastSearches(): array
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
     * @throws DBALDriverException
     * @throws DBALException|\Doctrine\DBAL\DBALException
     */
    public function addToLastSearches(string $keywords)
    {
        $mode = $this->configuration->getSearchLastSearchesMode();
        switch ($mode) {
            case 'user':
                $this->storeKeywordsToSession($keywords);
                break;
            case 'global':
                $this->lastSearchesRepository->add($keywords, $this->configuration->getSearchLastSearchesLimit());
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
    protected function getLastSearchesFromSession(int $limit): array
    {
        $lastSearches = $this->session->getLastSearches();
        return array_slice(array_reverse(array_unique($lastSearches)), 0, $limit);
    }

    /**
     * Stores the keywords from the current query to the user's session.
     *
     * @param string $keywords The current query's keywords
     */
    protected function storeKeywordsToSession(string $keywords)
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
