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

        $statisticsRows = $this->getPreparedQueryBuilderForSearchStatisticsAndTopKeywords($rootPageId, $timeStart, $limit)
            ->execute()->fetchAll();

        $statisticsRows = $this->mergeRowsWithSameKeyword($statisticsRows);

        $sumCount = $statisticsRows['sumCount'];
        foreach ($statisticsRows as $statisticsRow) {
            $sumCount += $statisticsRow['count'];
        }

        $statisticsRows = array_map(function($row) use ($sumCount) {
            $row['percent'] = $row['count'] * 100 / $sumCount;
            return $row;
        }, $statisticsRows);

        $statisticsRows = array_slice($statisticsRows,0,$limit);

        return $statisticsRows;
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
        $queryBuilder = $this->getQueryBuilder();
        $statisticsQueryBuilder = $queryBuilder
            ->select('keywords', 'num_found AS hits')
            ->add('select', $queryBuilder->expr()->count('keywords', 'count'), true)
            ->from($this->table)
            ->andWhere(
                $queryBuilder->expr()->gt('tstamp', $timeStart),
                $queryBuilder->expr()->eq('root_pid', $rootPageId)
            )
            ->groupBy('keywords', 'num_found')
            ->orderBy('count', 'DESC')
            ->addOrderBy('hits', 'DESC')
            ->addOrderBy('keywords', 'ASC');

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

        $statisticsRows = $queryBuilder->execute()->fetchAll();
        $statisticsRows = $this->mergeRowsWithSameKeyword($statisticsRows);

        $statisticsRows = array_slice($statisticsRows,0,$limit);

        return $statisticsRows;
    }

    /**
     * This method groups rows with the same term and different count and hits
     * and calculates the average.
     *
     * @param array $statisticsRows
     * @return array
     */
    protected function mergeRowsWithSameKeyword(array $statisticsRows) : array
    {
        $result = [];
        foreach ($statisticsRows as $statisticsRow) {
            $term = html_entity_decode($statisticsRow['keywords'], ENT_QUOTES);

            $mergedRow = isset($result[$term]) ? $result[$term] : ['mergedrows' => 0, 'count' => 0];
            $mergedRow['mergedrows']++;

            // for the hits we need to take the average
            $avgHits = $this->getAverageFromField($mergedRow, $statisticsRow, 'hits');
            $mergedRow['hits'] = (int)$avgHits;

            // for the count we need to take the sum, because it's the sum of searches
            $mergedRow['count'] = $mergedRow['count'] + $statisticsRow['count'];

            $mergedRow['keywords'] = $term;
            $result[$term] = $mergedRow;
        }

        $result = $this->sortStatisticsRowsByCount($result);

        return array_values($result);
    }

    /**
     * Sort the $statisticsRows by count
     * @param array $statisticsRows
     * @return array
     */
    protected function sortStatisticsRowsByCount(array $statisticsRows) : array
    {
        $numbers = [];
        foreach ($statisticsRows as $key => $row) {
            $numbers[$key] = $row['count'];
        }
        array_multisort($numbers, SORT_DESC, $statisticsRows);

        return $statisticsRows;
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
                'FLOOR(`tstamp`/' . $bucketSeconds . ') AS `bucket`',
                // @todo: Works only with MySQL. Add own column with Date type to prevent converting DateTime to Date
                'unix_timestamp(from_unixtime(`tstamp`, "%y-%m-%d")) AS `timestamp`',
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
     * This method is used to get an average value from merged statistic rows.
     *
     * @param array $mergedRow
     * @param array $statisticsRow
     * @param string $fieldName
     * @return float|int
     */
    protected function getAverageFromField(array &$mergedRow, array $statisticsRow, string $fieldName)
    {
        // when this is the first row we can take it.
        if ($mergedRow['mergedrows'] === 1) {
            $avgCount = $statisticsRow[$fieldName];
            return $avgCount;
        }

        $oldAverage = $mergedRow[$fieldName];
        $oldMergeRows = $mergedRow['mergedrows'] - 1;
        $oldCount = $oldAverage * $oldMergeRows;
        $avgCount = (($oldCount + $statisticsRow[$fieldName]) / $mergedRow['mergedrows']);
        return $avgCount;
    }
}
