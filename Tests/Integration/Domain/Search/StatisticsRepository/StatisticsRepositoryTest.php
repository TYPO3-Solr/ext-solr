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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Search\StatisticsRepository;

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use DateTime;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class StatisticsRepositoryTest extends IntegrationTestBase
{
    #[Test]
    public function canGetTopKeywordsWithHits(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/statistics.csv');
        $fixtureTimestamp = 1471203378;
        $filterDto = self::getFilterDto(1, $fixtureTimestamp);

        /** @var StatisticsRepository $repository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getTopKeyWordsWithHits($filterDto);
        $expectedResult = [
            ['keywords' => 'content', 'count' => 2, 'hits' => '5.0000', 'percent' => '50.0000'],
            ['keywords' => 'typo3', 'count' => 1, 'hits' => '6.0000', 'percent' => '25.0000'],
        ];

        self::assertSame($expectedResult, $topHits);
    }

    #[Test]
    public function canGetTopKeywordsWithoutHits(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/statistics.csv');
        $fixtureTimestamp = 1471203378;
        $filterDto = self::getFilterDto(1, $fixtureTimestamp);

        /** @var StatisticsRepository $repository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getTopKeyWordsWithoutHits($filterDto);

        $expectedResult = [
            ['keywords' => 'cms', 'count' => 1, 'hits' => '0.0000', 'percent' => '25.0000'],
        ];

        self::assertSame($expectedResult, $topHits);
    }

    #[Test]
    public function canGetTopKeywordsWithoutHitsNoResult(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/statistics.csv');
        $fixtureTimestamp = 1480000000;
        $filterDto = self::getFilterDto(1, $fixtureTimestamp);

        /** @var StatisticsRepository $repository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getTopKeyWordsWithoutHits($filterDto);

        $expectedResult = [];

        self::assertSame($expectedResult, $topHits);
    }

    #[Test]
    public function canGetSearchStatisticsNoResult(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/statistics.csv');
        $fixtureTimestamp = 1480000000;
        $filterDto = self::getFilterDto(37, $fixtureTimestamp);

        /** @var StatisticsRepository $repository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);
        $topHits = $repository->getSearchStatistics($filterDto);

        $expectedResult = [];

        self::assertSame($expectedResult, $topHits);
    }

    #[Test]
    public function canSaveStatisticsRecord(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/statistics.csv');
        /** @var StatisticsRepository $repository */
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        self::assertEquals(4, $repository->countByRootPageId(1), 'Does not contain all statistics records from fixtures.');

        $statisticRecord = [
            'pid' => 317,
            'root_pid' => 1,
            'tstamp' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
            'language' => 0,
            'num_found' => 21,
            'suggestions_shown' => 0,
            'time_total' => 13,
            'time_preparation' => 2,
            'time_processing' => 27,
            'feuser_id' => 0,
            'ip' => '192.168.144.1',
            'page' => 0,
            'keywords' => 'inserted record',
            'filters' => 'a:0:{}',
            'sorting' => '',
            'parameters' => '',
        ];
        $repository->saveStatisticsRecord($statisticRecord);

        self::assertEquals(5, $repository->countByRootPageId(1), 'Does not contain shortly inserted statistic record.');
    }

    protected static function getFilterDto(int $rootPageId, int $sinceTimestamp): StatisticsFilterDto
    {
        $startDate = DateTime::createFromFormat('U', (string)$sinceTimestamp);
        $filterDto = new StatisticsFilterDto();
        $filterDto->setSiteRootPageId($rootPageId)
            ->setFromTypoScriptConstants([])
            ->setStartDate($startDate->modify('-1 day'));
        return $filterDto;
    }
}
