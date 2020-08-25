<?php declare(strict_types = 1);
namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Thomas Hohn <tho@systime.dk>
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

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Calculates the SearchQueryStatistics
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class StatisticsRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $table = 'tx_solr_statistics';

    /**
     * Fetches must popular search keys words from the table tx_solr_statistics
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $limit
     * @return mixed
     */
    public function getSearchStatistics(int $rootPageId, int $days = 30, $limit = 10)
    {
        $now = time();
        $timeStart = (int)($now - 86400 * $days); // 86400 seconds/day
        $limit = (int)$limit;

        return $this->getPreparedQueryBuilderForSearchStatisticsAndTopKeywords($rootPageId, $timeStart, $limit)
            ->execute()->fetchAll();
    }

    /**
     * Returns prepared QueryBuilder for two purposes:
     * for getSearchStatistics() and getTopKeyWordsWithOrWithoutHits() methods
     *
     * @param int $rootPageId
     * @param int $timeStart
     * @param int $limit
     * @return QueryBuilder
     */
    protected function getPreparedQueryBuilderForSearchStatisticsAndTopKeywords(int $rootPageId, int $timeStart, int $limit) : QueryBuilder
    {
        $countRows = $this->countByRootPageId($rootPageId);
        $queryBuilder = $this->getQueryBuilder();
        $statisticsQueryBuilder = $queryBuilder
            ->select('keywords')
            ->add('select', $queryBuilder->expr()->count('keywords', 'count'), true)
            ->add('select', $queryBuilder->expr()->avg('num_found', 'hits'), true)
            ->add('select', '(' . $queryBuilder->expr()->count('keywords') . ' * 100 / ' . $countRows . ') AS percent', true)
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->gt('tstamp', $timeStart),
                $queryBuilder->expr()->eq('root_pid', $rootPageId)
            )
            ->groupBy('keywords')
            ->orderBy('count', 'DESC')
            ->addOrderBy('hits', 'DESC')
            ->addOrderBy('keywords', 'ASC')
            ->setMaxResults($limit);

        return $statisticsQueryBuilder;
    }

    /**
     * Find Top search keywords with results
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $limit
     * @return array
     */
    public function getTopKeyWordsWithHits(int $rootPageId, int $days = 30, int $limit = 10) : array
    {
        return $this->getTopKeyWordsWithOrWithoutHits($rootPageId, $days, $limit, false);
    }

    /**
     * Find Top search keywords without results
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $limit
     * @return array
     */
    public function getTopKeyWordsWithoutHits(int $rootPageId, int $days = 30, int $limit = 10) : array
    {
        return $this->getTopKeyWordsWithOrWithoutHits($rootPageId, $days, $limit, true);
    }

    /**
     * Find Top search keywords with or without results
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $limit
     * @param bool $withoutHits
     * @return array
     */
    protected function getTopKeyWordsWithOrWithoutHits(int $rootPageId, int $days = 30, int $limit = 10, bool $withoutHits = false) : array
    {
        $now = time();
        $timeStart = $now - 86400 * $days; // 86400 seconds/day

        $queryBuilder = $this->getPreparedQueryBuilderForSearchStatisticsAndTopKeywords($rootPageId, $timeStart, $limit);
        // Check if we want without or with hits
        if ($withoutHits === true) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('num_found', 0));
        } else {
            $queryBuilder->andWhere($queryBuilder->expr()->gt('num_found', 0));
        }

        return $queryBuilder->execute()->fetchAll();
    }

    /**
     * Get number of queries over time
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $bucketSeconds Seconds per bucket
     * @return array [labels, data]
     */
    public function getQueriesOverTime(int $rootPageId, int $days = 30, int $bucketSeconds = 3600) : array
    {
        $now = time();
        $timeStart = $now - 86400 * intval($days); // 86400 seconds/day

        $queryBuilder = $this->getQueryBuilder();
        $result = $queryBuilder
            ->addSelectLiteral(
                'FLOOR(tstamp/' . $bucketSeconds . ') AS bucket',
                '(tstamp - (tstamp % 86400)) AS timestamp',
                $queryBuilder->expr()->count('*', 'numQueries')
            )
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->gt('tstamp', $timeStart),
                $queryBuilder->expr()->eq('root_pid', $rootPageId)
            )
            ->groupBy('bucket', 'timestamp')
            ->orderBy('bucket', 'ASC')
            ->execute()->fetchAll();

        return $result;
    }

    /**
     * Regurns a result set by given plugin.tx_solr.search.frequentSearches.select configuration.
     *
     * @param array $frequentSearchConfiguration
     * @return array Array of frequent search terms, keys are the terms, values are hits
     */
    public function getFrequentSearchTermsFromStatisticsByFrequentSearchConfiguration(array $frequentSearchConfiguration) : array
    {
        $queryBuilder = $this->getQueryBuilder();
        $resultSet = $queryBuilder
            ->addSelectLiteral(
                $frequentSearchConfiguration['select.']['SELECT']
            )
            ->from($frequentSearchConfiguration['select.']['FROM'])
            ->add('where', $frequentSearchConfiguration['select.']['ADD_WHERE'], true)
            ->add('groupBy', $frequentSearchConfiguration['select.']['GROUP_BY'], true)
            ->add('orderBy', $frequentSearchConfiguration['select.']['ORDER_BY'])
            ->setMaxResults((int)$frequentSearchConfiguration['limit'])
            ->execute()->fetchAll();

        return $resultSet;
    }

    /**
     * Persists statistics record
     *
     * @param array $statisticsRecord
     * @return void
     */
    public function saveStatisticsRecord(array $statisticsRecord)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->insert($this->table)->values($statisticsRecord)->execute();
    }

    /**
     * Counts rows for specified site
     *
     * @param int $rootPageId
     * @return int
     */
    public function countByRootPageId(int $rootPageId): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return (int)$this->getQueryBuilder()
            ->count('*')
            ->from($this->table)
            ->andWhere($queryBuilder->expr()->eq('root_pid', $rootPageId))
            ->execute()->fetchColumn(0);
    }
}
