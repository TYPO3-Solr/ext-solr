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
            ['mergedrows' => 2, 'count' => 2, 'hits' => 5, 'keywords' => 'content'],
            ['mergedrows' => 1, 'count' => 1, 'hits' => 6, 'keywords' => 'typo3']
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
            ['mergedrows' => 1, 'count' => 1, 'hits' => 0, 'keywords' => 'cms'],
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
