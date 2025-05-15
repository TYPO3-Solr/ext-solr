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
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use UnexpectedValueException;

/**
 * The LastSearchesService is responsible to return the LastSearches from the session or database,
 * depending on the configuration.
 */
class LastSearchesService
{
    protected TypoScriptConfiguration $configuration;

    protected FrontendUserSession $session;

    protected LastSearchesRepository $lastSearchesRepository;

    public function __construct(
        TypoScriptConfiguration $typoscriptConfiguration,
        ?FrontendUserSession $session = null,
        ?LastSearchesRepository $lastSearchesRepository = null,
    ) {
        $this->configuration = $typoscriptConfiguration;
        $this->session = $session ?? GeneralUtility::makeInstance(FrontendUserSession::class);
        $this->lastSearchesRepository = $lastSearchesRepository ?? GeneralUtility::makeInstance(LastSearchesRepository::class);
    }

    /**
     * Retrieves the last searches from the session or database depending on the configuration.
     *
     * @throws DBALException
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
     * @throws DBALException
     */
    public function addToLastSearches(string $keywords): void
    {
        $mode = $this->configuration->getSearchLastSearchesMode();
        switch ($mode) {
            case 'user':
                $this->storeKeywordsToSession($keywords);
                break;
            case 'global':
                $this->lastSearchesRepository->add($keywords, $this->configuration->getSearchLastSearchesLimit());
                break;
            case 'disabled':
                break;
            default:
                throw new UnexpectedValueException(
                    'Unknown mode for plugin.tx_solr.search.lastSearches.mode, valid modes are "user", "global" or "disabled".',
                    1342456570,
                );
        }
    }

    /**
     * Returns the last searched keywords from the user's session
     *
     * @return string[] An array containing the last searches of the current user
     */
    protected function getLastSearchesFromSession(int $limit): array
    {
        $lastSearches = $this->session->getLastSearches();
        return array_slice(array_reverse(array_unique($lastSearches)), 0, $limit);
    }

    /**
     * Stores the keywords from the current query to the user's session.
     */
    protected function storeKeywordsToSession(string $keywords): void
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
