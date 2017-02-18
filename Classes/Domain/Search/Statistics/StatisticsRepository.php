<?php

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

/**
 * Calculates the SearchQueryStatistics
 *
 * @author Thomas Hohn <tho@systime.dk>
 */
class StatisticsRepository
{
    /**
     * Fetches must popular search keys words from the table tx_solr_statistics
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $limit
     * @return mixed
     */
    public function getSearchStatistics($rootPageId, $days = 30, $limit = 10)
    {
        $now = time();
        $timeStart = (int) ($now - 86400 * intval($days)); // 86400 seconds/day
        $rootPageId = (int) $rootPageId;
        $limit = (int) $limit;

        $statisticsRows = (array)$this->getDatabase()->exec_SELECTgetRows(
            'keywords, count(keywords) as count, num_found as hits',
            'tx_solr_statistics',
            'tstamp > ' . $timeStart . ' AND root_pid = ' . $rootPageId,
            'keywords, num_found',
            'count DESC, hits DESC, keywords ASC',
            $limit
        );

        $statisticsRows = $this->mergeRowsWithSameKeyword($statisticsRows);

        $sumCount = $statisticsRows['sumCount'];
        foreach ($statisticsRows as $statisticsRow) {
            $sumCount += $statisticsRow['count'];
        }

        $statisticsRows = array_map(function ($row) use ($sumCount) {
            $row['percent'] = $row['count'] * 100 / $sumCount;

            return $row;
        }, $statisticsRows);

        return $statisticsRows;
    }

    /**
     * Find Top search keywords with results
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $limit
     * @return array
     */
    public function getTopKeyWordsWithHits($rootPageId, $days = 30, $limit = 10)
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
    public function getTopKeyWordsWithoutHits($rootPageId, $days = 30, $limit = 10)
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
    protected function getTopKeyWordsWithOrWithoutHits($rootPageId, $days = 30, $limit, $withoutHits)
    {
        $rootPageId = (int) $rootPageId;
        $limit = (int) $limit;
        $withoutHits = (bool) $withoutHits;

        $now = time();
        $timeStart = $now - 86400 * intval($days); // 86400 seconds/day

        // Check if we want without or with hits
        if ($withoutHits === true) {
            $comparisonOperator = '=';
        } else {
            $comparisonOperator = '>';
        }

        $statisticsRows = (array)$this->getDatabase()->exec_SELECTgetRows(
            'keywords, count(keywords) as count, num_found as hits',
            'tx_solr_statistics',
            'tstamp > ' . $timeStart . ' AND root_pid = ' . $rootPageId . ' AND num_found ' . $comparisonOperator . ' 0',
            'keywords, num_found',
            'count DESC, hits DESC, keywords ASC',
            $limit
        );

        $statisticsRows = $this->mergeRowsWithSameKeyword($statisticsRows);

        return $statisticsRows;
    }

    /**
     * This method groups rows with the same term and different count and hits
     * and calculates the average.
     *
     * @param array $statisticsRows
     * @return array
     */
    protected function mergeRowsWithSameKeyword(array $statisticsRows)
    {
        $result = [];
        foreach ($statisticsRows as $statisticsRow) {
            $term = html_entity_decode($statisticsRow['keywords'], ENT_QUOTES);

            $mergedRow = isset($result[$term]) ? $result[$term] : ['mergedrows' => 0, 'count' => 0];
            $mergedRow['mergedrows']++;

                // for the hits we need to take the average
            $avgHits = $this->getAverageFromField($mergedRow, $statisticsRow, 'hits');
            $mergedRow['hits'] = (int) $avgHits;

                // for the count we need to take the sum, because it's the sum of searches
            $mergedRow['count'] = $mergedRow['count'] + $statisticsRow['count'];

            $mergedRow['keywords'] = $term;
            $result[$term] = $mergedRow;
        }

        return array_values($result);
    }

    /**
     * Get number of queries over time
     *
     * @param int $rootPageId
     * @param int $days number of days of history to query
     * @param int $bucketSeconds Seconds per bucket
     * @return array [labels, data]
     */
    public function getQueriesOverTime($rootPageId, $days = 30, $bucketSeconds = 3600)
    {
        $now = time();
        $timeStart = $now - 86400 * intval($days); // 86400 seconds/day

        $queries = $this->getDatabase()->exec_SELECTgetRows(
            'FLOOR(tstamp/' . $bucketSeconds . ') AS bucket, unix_timestamp(from_unixtime(tstamp, "%y-%m-%d")) as timestamp, COUNT(*) AS numQueries',
            'tx_solr_statistics',
            'tstamp > ' . $timeStart . ' AND root_pid = ' . $rootPageId,
            'bucket, timestamp',
            'bucket ASC'
        );

        return $queries;
    }

    /**
     * This method is used to get an average value from merged statistic rows.
     *
     * @param array $mergedRow
     * @param array $statisticsRow
     * @param string $fieldName
     * @return float|int
     */
    protected function getAverageFromField(array &$mergedRow, array $statisticsRow,  $fieldName)
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

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabase()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
