<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\FrequentSearches;

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The FrequentSearchesService is used to retrieve the frequent searches from the database or cache.
 */
class FrequentSearchesService
{
    /**
     * Instance of the caching frontend used to cache this command's output.
     */
    protected ?AbstractFrontend $cache;

    protected ?StatisticsRepository $statisticsRepository;

    protected TypoScriptConfiguration $configuration;

    public function __construct(
        TypoScriptConfiguration $typoscriptConfiguration,
        ?AbstractFrontend $cache = null,
        ?StatisticsRepository $statisticsRepository = null,
    ) {
        $this->configuration = $typoscriptConfiguration;
        $this->cache = $cache;
        $this->statisticsRepository = $statisticsRepository ?? GeneralUtility::makeInstance(StatisticsRepository::class);
    }

    /**
     * Generates an array with terms and hits
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     */
    public function getFrequentSearchTerms(ServerRequestInterface $typo3Request): array
    {
        $frequentSearchConfiguration = $this->configuration->getSearchFrequentSearchesConfiguration();

        $identifier = $this->getCacheIdentifier($typo3Request, $frequentSearchConfiguration);

        if ($this->hasValidCache() && $this->cache->has($identifier)) {
            $terms = $this->cache->get($identifier);
        } else {
            $terms = $this->getFrequentSearchTermsFromStatistics($typo3Request, $frequentSearchConfiguration);

            if (isset($frequentSearchConfiguration['sortBy']) && $frequentSearchConfiguration['sortBy'] === 'hits') {
                arsort($terms);
            } else {
                ksort($terms);
            }

            $lifetime = null;
            if (isset($frequentSearchConfiguration['cacheLifetime'])) {
                $lifetime = (int)($frequentSearchConfiguration['cacheLifetime']);
            }

            if ($this->hasValidCache()) {
                $this->cache->set($identifier, $terms, [], $lifetime);
            }
        }

        return $terms;
    }

    /**
     * Gets frequent search terms from the statistics tracking table.
     *
     * @throws DBALException
     */
    protected function getFrequentSearchTermsFromStatistics(ServerRequestInterface $serverRequest, array $frequentSearchConfiguration): array
    {
        $terms = [];

        if ($frequentSearchConfiguration['select.']['checkRootPageId']) {
            $checkRootPidWhere = 'root_pid = ' . $serverRequest->getAttribute('frontend.page.information')?->getLocalRootLine()[0]['uid'];
        } else {
            $checkRootPidWhere = '1';
        }
        if ($frequentSearchConfiguration['select.']['checkLanguage']) {
            $checkLanguageWhere = ' AND language =' . $serverRequest->getAttribute('language')?->getLanguageId();
        } else {
            $checkLanguageWhere = '';
        }

        $frequentSearchConfiguration['select.']['ADD_WHERE'] = $checkRootPidWhere .
            $checkLanguageWhere . ' ' .
            $frequentSearchConfiguration['select.']['ADD_WHERE'];

        $frequentSearchTerms = $this->statisticsRepository
            ->getFrequentSearchTermsFromStatisticsByFrequentSearchConfiguration($frequentSearchConfiguration);

        foreach ($frequentSearchTerms as $term) {
            $cleanedTerm = html_entity_decode($term['search_term'], ENT_QUOTES, 'UTF-8');
            $terms[$cleanedTerm] = $term['hits'];
        }

        return $terms;
    }

    /**
     * Returns cache identifier for given $frequentSearchConfiguration
     */
    protected function getCacheIdentifier(ServerRequestInterface $serverRequest, array $frequentSearchConfiguration): string
    {
        // Use configuration as cache identifier
        $identifier = 'frequentSearchesTags';

        if (isset($frequentSearchConfiguration['select.']['checkRootPageId']) && $frequentSearchConfiguration['select.']['checkRootPageId']) {
            $identifier .= '_RP' . (int)$serverRequest->getAttribute('frontend.page.information')?->getLocalRootLine()[0]['uid'];
        }
        if (isset($frequentSearchConfiguration['select.']['checkLanguage']) && $frequentSearchConfiguration['select.']['checkLanguage']) {
            $identifier .= '_L' . $serverRequest->getAttribute('language')?->getLanguageId();
        }

        $identifier .= '_' . hash('md5', serialize($frequentSearchConfiguration));
        return $identifier;
    }

    /**
     * Checks if this service has a valid cache class
     */
    protected function hasValidCache(): bool
    {
        return $this->cache instanceof FrontendInterface;
    }
}
