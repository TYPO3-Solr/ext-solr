<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Search\FrequentSearches;

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

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Cache\Frontend\AbstractFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * The FrequentSearchesService is used to retrieve the frequent searches from the database or cache.
 *
 * @author Dimitri Ebert <dimitri.ebert@dkd.de>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class FrequentSearchesService
{

    /**
     * Instance of the caching frontend used to cache this command's output.
     *
     * @var AbstractFrontend
     */
    protected $cache;

    /**
     * @var TypoScriptFrontendController
     */
    protected $tsfe;

    /**
     * @var StatisticsRepository
     */
    protected $statisticsRepository;

    /**
     * @var TypoScriptConfiguration
     */
    protected $configuration;

    /**
     * @param TypoScriptConfiguration $typoscriptConfiguration
     * @param AbstractFrontend $cache
     * @param TypoScriptFrontendController $tsfe
     * @param StatisticsRepository $statisticsRepository
     */
    public function __construct(TypoScriptConfiguration $typoscriptConfiguration, AbstractFrontend $cache, TypoScriptFrontendController $tsfe, StatisticsRepository $statisticsRepository = null)
    {
        $this->configuration = $typoscriptConfiguration;
        $this->cache = $cache;
        $this->tsfe = $tsfe;
        $this->statisticsRepository = $statisticsRepository ?? GeneralUtility::makeInstance(StatisticsRepository::class);
    }

    /**
     * Generates an array with terms and hits
     *
     * @return array Tags as array with terms and hits
     */
    public function getFrequentSearchTerms() : array
    {
        $frequentSearchConfiguration = $this->configuration->getSearchFrequentSearchesConfiguration();

        $identifier = $this->getCacheIdentifier($frequentSearchConfiguration);

        if ($this->cache->has($identifier)) {
            $terms = $this->cache->get($identifier);
        } else {
            $terms = $this->getFrequentSearchTermsFromStatistics($frequentSearchConfiguration);

            if ($frequentSearchConfiguration['sortBy'] === 'hits') {
                arsort($terms);
            } else {
                ksort($terms);
            }

            $lifetime = null;
            if (isset($frequentSearchConfiguration['cacheLifetime'])) {
                $lifetime = intval($frequentSearchConfiguration['cacheLifetime']);
            }

            $this->cache->set($identifier, $terms, [], $lifetime);
        }

        return $terms;
    }

    /**
     * Gets frequent search terms from the statistics tracking table.
     *
     * @param array $frequentSearchConfiguration
     * @return array Array of frequent search terms, keys are the terms, values are hits
     */
    protected function getFrequentSearchTermsFromStatistics(array $frequentSearchConfiguration) : array
    {
        $terms = [];

        if ($frequentSearchConfiguration['select.']['checkRootPageId']) {
            $checkRootPidWhere = 'root_pid = ' . $this->tsfe->tmpl->rootLine[0]['uid'];
        } else {
            $checkRootPidWhere = '1';
        }
        if ($frequentSearchConfiguration['select.']['checkLanguage']) {
            $checkLanguageWhere = ' AND language =' . Util::getLanguageUid();
        } else {
            $checkLanguageWhere = '';
        }

        $frequentSearchConfiguration['select.']['ADD_WHERE'] = $checkRootPidWhere .
            $checkLanguageWhere . ' ' .
            $frequentSearchConfiguration['select.']['ADD_WHERE'];

        $frequentSearchTerms = $this->statisticsRepository->getFrequentSearchTermsFromStatisticsByFrequentSearchConfiguration($frequentSearchConfiguration);

        if (!is_array($frequentSearchTerms)) {
            return $terms;
        }

        foreach ($frequentSearchTerms as $term) {
            $cleanedTerm = html_entity_decode($term['search_term'], ENT_QUOTES, 'UTF-8');
            $terms[$cleanedTerm] = $term['hits'];
        }

        return $terms;
    }

    /**
     * @param array $frequentSearchConfiguration
     * @return string
     */
    protected function getCacheIdentifier(array $frequentSearchConfiguration) : string
    {
        // Use configuration as cache identifier
        $identifier = 'frequentSearchesTags';

        if ($frequentSearchConfiguration['select.']['checkRootPageId']) {
            $identifier .= '_RP' . (int)$this->tsfe->tmpl->rootLine[0]['uid'];
        }
        if ($frequentSearchConfiguration['select.']['checkLanguage']) {
            $identifier .= '_L' . Util::getLanguageUid();
        }

        $identifier .= '_' . md5(serialize($frequentSearchConfiguration));
        return $identifier;
    }
}
