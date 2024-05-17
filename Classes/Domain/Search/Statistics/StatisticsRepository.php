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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics;

use ApacheSolrForTypo3\Solr\System\Records\AbstractRepository;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Calculates the SearchQueryStatistics
 */
class StatisticsRepository extends AbstractRepository
{
    protected string $table = 'tx_solr_statistics';

    /**
     * Fetches most popular search keys words from the table tx_solr_statistics
     *
     * @throws DBALException
     */
    public function getSearchStatistics(int $rootPageId, int $days = 30, int $limit = 10): array
    {
        $now = time();
        $timeStart = $now - 86400 * $days; // 86400 seconds/day
        return $this->getPreparedQueryBuilderForSearchStatisticsAndTopKeywords($rootPageId, $timeStart, $limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns prepared QueryBuilder for two purposes:
     * for getSearchStatistics() and getTopKeyWordsWithOrWithoutHits() methods
     *
     * @throws DBALException
     */
    protected function getPreparedQueryBuilderForSearchStatisticsAndTopKeywords(int $rootPageId, int $timeStart, int $limit): QueryBuilder
    {
        $countRows = $this->countByRootPageId($rootPageId);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select('keywords');

        $queryBuilder->getConcreteQueryBuilder()
            ->addSelect(
                $queryBuilder->expr()->count('keywords', 'count'),
                $queryBuilder->expr()->avg('num_found', 'hits'),
                '(' . $queryBuilder->expr()->count('keywords') . ' * 100 / ' . $countRows . ') AS percent'
            );

        $queryBuilder
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->gt('tstamp', $timeStart),
                $queryBuilder->expr()->eq('root_pid', $rootPageId)
            )
            ->groupBy('keywords')
            ->orderBy('count', 'DESC')
            ->addOrderBy('hits', 'DESC')
            ->addOrderBy('keywords', 'ASC')
            ->setMaxResults($limit);
        return $queryBuilder;
    }

    /**
     * Find Top search keywords with results
     *
     * @throws DBALException
     */
    public function getTopKeyWordsWithHits(int $rootPageId, int $days = 30, int $limit = 10): array
    {
        return $this->getTopKeyWordsWithOrWithoutHits($rootPageId, $days, $limit);
    }

    /**
     * Find Top search keywords without results
     *
     * @throws DBALException
     */
    public function getTopKeyWordsWithoutHits(int $rootPageId, int $days = 30, int $limit = 10): array
    {
        return $this->getTopKeyWordsWithOrWithoutHits($rootPageId, $days, $limit, true);
    }

    /**
     * Find Top search keywords with or without results
     *
     * @throws DBALException
     */
    protected function getTopKeyWordsWithOrWithoutHits(int $rootPageId, int $days = 30, int $limit = 10, bool $withoutHits = false): array
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

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Get number of queries over time
     *
     * @throws DBALException
     */
    public function getQueriesOverTime(int $rootPageId, int $days = 30, int $bucketSeconds = 3600): array
    {
        $now = time();
        $timeStart = $now - 86400 * $days; // 86400 seconds/day

        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder
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
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns an Array of frequent search terms, keys are the terms, values are hits of a result set by given
     * plugin.tx_solr.search.frequentSearches.select configuration.
     *
     * @throws DBALException
     */
    public function getFrequentSearchTermsFromStatisticsByFrequentSearchConfiguration(array $frequentSearchConfiguration): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->addSelectLiteral(
                $frequentSearchConfiguration['select.']['SELECT']
            )
            ->from($frequentSearchConfiguration['select.']['FROM']);

        $queryBuilder
            ->getConcreteQueryBuilder()
            ->where($frequentSearchConfiguration['select.']['ADD_WHERE'])
            ->groupBy($frequentSearchConfiguration['select.']['GROUP_BY'])
            ->orderBy($frequentSearchConfiguration['select.']['ORDER_BY'])
            ->setMaxResults((int)$frequentSearchConfiguration['limit']);

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Persists statistics record
     */
    public function saveStatisticsRecord(array $statisticsRecord): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->insert($this->table)->values($statisticsRecord)->executeStatement();
    }

    /**
     * Counts rows for specified site
     *
     * @throws DBALException
     */
    public function countByRootPageId(int $rootPageId): int
    {
        $queryBuilder = $this->getQueryBuilder();
        return (int)$this->getQueryBuilder()
            ->count('*')
            ->from($this->table)
            ->andWhere($queryBuilder->expr()->eq('root_pid', $rootPageId))
            ->executeQuery()
            ->fetchOne();
    }
}
