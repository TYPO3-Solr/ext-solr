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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Calculates the SearchQueryStatistics
 */
class StatisticsRepository extends AbstractRepository
{
    protected string $table = 'tx_solr_statistics';

    /**
     * Distinct search terms per period the statistics module evaluates at most
     */
    public const MAXIMUM_TERMS = 20000;

    /**
     * Fetches most popular search keys words from the table tx_solr_statistics
     *
     * @throws DBALException
     */
    public function getSearchStatistics(StatisticsFilterDto $statisticsFilterDto): array
    {
        return $this->getPreparedQueryBuilderForSearchStatisticsAndTopKeywords(
            $statisticsFilterDto->getSiteRootPageId(),
            $statisticsFilterDto->getQueriesStartDate(),
            $statisticsFilterDto->getEndDateTimestamp(),
            $statisticsFilterDto->getQueriesLimit(),
        )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Returns prepared QueryBuilder for two purposes:
     * for getSearchStatistics() and getTopKeyWordsWithOrWithoutHits() methods
     *
     * @throws DBALException
     */
    protected function getPreparedQueryBuilderForSearchStatisticsAndTopKeywords(
        int $rootPageId,
        int $timeStart,
        int $timeEnd,
        int $limit,
    ): QueryBuilder {
        $countRows = $this->countByRootPageId($rootPageId);
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select('keywords');

        $queryBuilder->getConcreteQueryBuilder()
            ->addSelect(
                $queryBuilder->expr()->count('keywords', 'count'),
                $queryBuilder->expr()->avg('num_found', 'hits'),
                '(' . $queryBuilder->expr()->count('keywords') . ' * 100 / ' . $countRows . ') AS percent',
            );

        $queryBuilder
            ->from($this->table)
            ->where(
                $queryBuilder->expr()->gt('tstamp', $timeStart),
                $queryBuilder->expr()->lt('tstamp', $timeEnd),
                $queryBuilder->expr()->eq('root_pid', $rootPageId),
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
    public function getTopKeyWordsWithHits(StatisticsFilterDto $filterDto): array
    {
        return $this->getTopKeyWordsWithOrWithoutHits(
            $filterDto->getSiteRootPageId(),
            $filterDto->getTopHitsStartDate(),
            $filterDto->getEndDateTimestamp(),
            $filterDto->getTopHitsLimit(),
        );
    }

    /**
     * Find Top search keywords without results
     *
     * @throws DBALException
     */
    public function getTopKeyWordsWithoutHits(StatisticsFilterDto $filterDto): array
    {
        return $this->getTopKeyWordsWithOrWithoutHits(
            $filterDto->getSiteRootPageId(),
            $filterDto->getNoHitsStartDate(),
            $filterDto->getEndDateTimestamp(),
            $filterDto->getNoHitsLimit(),
            true,
        );
    }

    /**
     * Find Top search keywords with or without results
     *
     * @throws DBALException
     */
    protected function getTopKeyWordsWithOrWithoutHits(
        int $rootPageId,
        int $timeStart,
        int $timeEnd,
        int $limit = 10,
        bool $withoutHits = false,
    ): array {
        $queryBuilder = $this->getPreparedQueryBuilderForSearchStatisticsAndTopKeywords(
            $rootPageId,
            $timeStart,
            $timeEnd,
            $limit,
        );

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
    public function getQueriesOverTime(StatisticsFilterDto $statisticsFilterDto, int $bucketSeconds = 3600): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->addSelectLiteral(
                'FLOOR(tstamp/' . $bucketSeconds . ') AS bucket',
                '(tstamp - (tstamp % 86400)) AS timestamp',
                $queryBuilder->expr()->count('*', 'numQueries'),
            )
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->gt('tstamp', $statisticsFilterDto->getQueriesStartDate()),
                $queryBuilder->expr()->lt('tstamp', $statisticsFilterDto->getEndDateTimestamp()),
                $queryBuilder->expr()->eq('root_pid', $statisticsFilterDto->getSiteRootPageId()),
            );
        $this->restrictToLanguages($queryBuilder, $statisticsFilterDto);

        return $queryBuilder
            ->groupBy('bucket', 'timestamp')
            ->orderBy('bucket', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * One row per stored search term and language in the period, the most
     * searched first. Grouping spellings and limiting is left to PHP, so the
     * result is the same on every database.
     *
     * @return list<array{keywords: string, language: int, count: int, countWithoutHits: int, hitsSum: int}>
     *
     * @throws DBALException
     */
    public function findSearchTermCounts(StatisticsFilterDto $filter, int $startTimestamp, int $maximum = self::MAXIMUM_TERMS): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->select('keywords', 'language')
            ->addSelectLiteral(
                $queryBuilder->expr()->count('*', 'count'),
                'SUM(CASE WHEN ' . $queryBuilder->quoteIdentifier('num_found') . ' = 0 THEN 1 ELSE 0 END) AS ' . $queryBuilder->quoteIdentifier('countWithoutHits'),
                $queryBuilder->expr()->sum('num_found', 'hitsSum'),
            )
            ->from($this->table);
        $this->restrictToPeriodAndSite($queryBuilder, $filter, $startTimestamp);
        $this->restrictToLanguages($queryBuilder, $filter);

        $rows = $queryBuilder
            ->groupBy('keywords', 'language')
            ->orderBy('count', 'DESC')
            ->addOrderBy('keywords', 'ASC')
            ->setMaxResults($maximum)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): array => [
            'keywords' => (string)$row['keywords'],
            'language' => (int)$row['language'],
            'count' => (int)$row['count'],
            'countWithoutHits' => (int)$row['countWithoutHits'],
            'hitsSum' => (int)$row['hitsSum'],
        ], $rows);
    }

    /**
     * All searches in the period and languages, the base of the percentages
     *
     * @throws DBALException
     */
    public function countSearches(StatisticsFilterDto $filter, int $startTimestamp): int
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->count('*')->from($this->table);
        $this->restrictToPeriodAndSite($queryBuilder, $filter, $startTimestamp);
        $this->restrictToLanguages($queryBuilder, $filter);

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    private function restrictToPeriodAndSite(QueryBuilder $queryBuilder, StatisticsFilterDto $filter, int $startTimestamp): void
    {
        $queryBuilder->andWhere(
            $queryBuilder->expr()->gt('tstamp', $queryBuilder->createNamedParameter($startTimestamp, Connection::PARAM_INT)),
            $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($filter->getEndDateTimestamp(), Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('root_pid', $queryBuilder->createNamedParameter($filter->getSiteRootPageId(), Connection::PARAM_INT)),
        );
    }

    /**
     * Without allowed languages (callers that set none) every language counts.
     */
    private function restrictToLanguages(QueryBuilder $queryBuilder, StatisticsFilterDto $filter): void
    {
        $languageIds = $filter->getLanguageIds();
        if ($languageIds !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('language', $queryBuilder->createNamedParameter($languageIds, Connection::PARAM_INT_ARRAY)),
            );
        }
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
                $frequentSearchConfiguration['select.']['SELECT'],
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
