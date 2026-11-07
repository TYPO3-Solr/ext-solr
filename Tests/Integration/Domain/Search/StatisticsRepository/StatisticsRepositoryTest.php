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

final class StatisticsRepositoryTest extends IntegrationTestBase
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

    /**
     * @param list<int> $languageIds
     */
    private function languageFilter(array $languageIds): StatisticsFilterDto
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/statistics_languages.csv');
        return self::getFilterDto(1, 1471203378)->setAllowedLanguageIds($languageIds);
    }

    /**
     * Case-insensitive collations group "News" and "news", others do not:
     * only the sums are the same on every database.
     */
    #[Test]
    public function findSearchTermCountsSumsSearchesPerTermAndLanguage(): void
    {
        $filter = $this->languageFilter([0, 2]);
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        $rows = $repository->findSearchTermCounts($filter, $filter->getQueriesStartDate());

        self::assertSame(7, array_sum(array_column($rows, 'count')));
        self::assertSame(2, array_sum(array_column($rows, 'countWithoutHits')));
        self::assertSame(22, array_sum(array_column($rows, 'hitsSum')));
        $languages = array_values(array_unique(array_column($rows, 'language')));
        sort($languages);
        self::assertSame([0, 2], $languages);
    }

    #[Test]
    public function findSearchTermCountsOnlyCountsTheGivenLanguages(): void
    {
        $filter = $this->languageFilter([0, 2])->setLanguageId(2);
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        $rows = $repository->findSearchTermCounts($filter, $filter->getQueriesStartDate());

        self::assertSame(3, array_sum(array_column($rows, 'count')));
        self::assertSame([2], array_values(array_unique(array_column($rows, 'language'))));
    }

    #[Test]
    public function findSearchTermCountsReturnsTheMostFrequentTermsUpToTheMaximum(): void
    {
        $filter = $this->languageFilter([0, 2])->setLanguageId(0);
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        $rows = $repository->findSearchTermCounts($filter, $filter->getQueriesStartDate(), 1);

        self::assertCount(1, $rows);
        self::assertGreaterThanOrEqual(3, $rows[0]['count']);
    }

    #[Test]
    public function countSearchesCountsThePeriodSiteAndLanguages(): void
    {
        $filter = $this->languageFilter([0, 2]);
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        self::assertSame(7, $repository->countSearches($filter, $filter->getQueriesStartDate()));
        self::assertSame(4, $repository->countSearches($filter->setLanguageId(0), $filter->getQueriesStartDate()));
    }

    #[Test]
    public function queriesOverTimeOnlyCountTheGivenLanguages(): void
    {
        $filter = $this->languageFilter([0, 2])->setLanguageId(2);
        $repository = GeneralUtility::makeInstance(StatisticsRepository::class);

        self::assertSame(3, array_sum(array_map('intval', array_column($repository->getQueriesOverTime($filter, 86400), 'numQueries'))));
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
