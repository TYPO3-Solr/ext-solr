<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\StatisticsRepository;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class StatisticsRepositoryTest extends IntegrationTest
{
    /**
     * @test
     */
    public function canGetTopKeywordsWithHits()
    {
        $this->importDataSetFromFixture('can_get_statistics.xml');
        $fixtureTimestamp = 1471203378;
        $daysSinceFixture = self::getDaysSinceTimestamp($fixtureTimestamp) + 1;

        /** @var $repository StatisticsRepository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getTopKeyWordsWithHits(1, $daysSinceFixture);
        $expectedResult = [
            ['keywords' => 'content', 'count' => 2, 'hits' => '5.0000', 'percent' => '50.0000'],
            ['keywords' => 'typo3', 'count' => 1, 'hits' => '6.0000', 'percent' => '25.0000']
        ];

        $this->assertSame($expectedResult, $topHits);
    }

    /**
     * @test
     */
    public function canGetTopKeywordsWithoutHits()
    {
        $this->importDataSetFromFixture('can_get_statistics.xml');
        $fixtureTimestamp = 1471203378;
        $daysSinceFixture = self::getDaysSinceTimestamp($fixtureTimestamp) + 1;

            /** @var $repository StatisticsRepository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getTopKeyWordsWithoutHits(1, $daysSinceFixture);

        $expectedResult = [
            ['keywords' => 'cms', 'count' => 1, 'hits' => '0.0000', 'percent' => '25.0000']
        ];

        $this->assertSame($expectedResult, $topHits);
    }

    /**
     * @test
     */
    public function canGetTopKeywordsWithoutHitsNoResult()
    {
        $this->importDataSetFromFixture('can_get_statistics.xml');
        $fixtureTimestamp = 1480000000;
        $daysSinceFixture = self::getDaysSinceTimestamp($fixtureTimestamp) + 1;

        /** @var $repository StatisticsRepository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getTopKeyWordsWithoutHits(1, $daysSinceFixture);

        $expectedResult = [];

        $this->assertSame($expectedResult, $topHits);
    }

    /**
     * @test
     */
    public function canGetSearchStatisticsNoResult()
    {
        $this->importDataSetFromFixture('can_get_statistics.xml');
        $fixtureTimestamp = 1480000000;
        $daysSinceFixture = self::getDaysSinceTimestamp($fixtureTimestamp) + 1;

        /** @var $repository StatisticsRepository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getSearchStatistics(37, $daysSinceFixture);

        $expectedResult = [];

        $this->assertSame($expectedResult, $topHits);
    }

    /**
     * @test
     */
    public function canSaveStatisticsRecord()
    {
        $this->importDataSetFromFixture('can_save_statistics_record.xml');
        /** @var $repository StatisticsRepository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        $this->assertEquals(4, $repository->countByRootPageId(1), 'Does not contain all statistics records from fixtures.');

        $statisticRecord = [
            'pid' => 317,
            'root_pid' => 1,
            'tstamp' => $GLOBALS['EXEC_TIME'],
            'language' => 0,
            'num_found' => 21,
            'suggestions_shown' => 0,
            'time_total' => 13,
            'time_preparation' => 2,
            'time_processing' => 27,
            'feuser_id' => 0,
            'cookie' => '0ad2582d058e2843c9bc3b2273309248s',
            'ip' => '192.168.144.1',
            'page' => 0,
            'keywords' => 'inserted record',
            'filters' => 'a:0:{}',
            'sorting' => '',
            'parameters' => ''
        ];
        $repository->saveStatisticsRecord($statisticRecord);

        $this->assertEquals(5, $repository->countByRootPageId(1), 'Does not contain shortly inserted statistic record.');
    }

    /**
     * Helper method to calculate the number of days from now to a specific timestamp.
     *
     * @param $timestamp
     * @return float
     */
    protected static function getDaysSinceTimestamp($timestamp)
    {
        $secondsUntilNow = time() - $timestamp;
        $days = floor($secondsUntilNow / (60*60*24));
        return $days;
    }
}
