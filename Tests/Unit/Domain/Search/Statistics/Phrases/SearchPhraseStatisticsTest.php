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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\Statistics\Phrases;

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchPhrase;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchPhraseStatistics;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermClusterer;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermGrouper;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermNormalizer;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SearchPhraseStatisticsTest extends SetUpUnitTestCase
{
    private const CONTACT_ROWS = [
        ['keywords' => 'kontakt', 'language' => 2, 'count' => 88, 'countWithoutHits' => 0, 'hitsSum' => 352],
        ['keywords' => 'formular', 'language' => 2, 'count' => 74, 'countWithoutHits' => 0, 'hitsSum' => 340],
        ['keywords' => 'kontaktformular', 'language' => 2, 'count' => 31, 'countWithoutHits' => 0, 'hitsSum' => 93],
        ['keywords' => 'kontackt', 'language' => 2, 'count' => 12, 'countWithoutHits' => 12, 'hitsSum' => 0],
    ];

    /**
     * @param list<array{keywords: string, language: int, count: int, countWithoutHits: int, hitsSum: int}> $rows
     */
    private function statistics(array $rows, int $searches): SearchPhraseStatistics
    {
        $repository = $this->createMock(StatisticsRepository::class);
        $repository->method('findSearchTermCounts')->willReturn($rows);
        $repository->method('countSearches')->willReturn($searches);

        return new SearchPhraseStatistics($repository, new SearchTermGrouper(new SearchTermNormalizer()), new SearchTermClusterer());
    }

    private static function filter(): StatisticsFilterDto
    {
        return (new StatisticsFilterDto())->setFromTypoScriptConstants([]);
    }

    /**
     * @param list<SearchPhrase> $phrases
     * @return list<string>
     */
    private static function labels(array $phrases): array
    {
        return array_map(static fn(SearchPhrase $phrase): string => $phrase->label, $phrases);
    }

    #[Test]
    public function showsAThemeWithItsTotalsAndVariants(): void
    {
        $phrases = $this->statistics(self::CONTACT_ROWS, 4520)->getAll(self::filter())->phrases;

        self::assertSame(['kontakt', 'formular'], self::labels($phrases));
        self::assertSame(131, $phrases[0]->count);
        self::assertEqualsWithDelta(3.40, $phrases[0]->averageHits, 0.01);
        self::assertEqualsWithDelta(2.90, $phrases[0]->percent, 0.01);
        self::assertSame(['kontakt', 'kontaktformular', 'kontackt'], self::labels($phrases[0]->variants));
        self::assertSame(12, $phrases[0]->variants[2]->count);
        self::assertFalse($phrases[1]->isTheme());
    }

    #[Test]
    public function keepsATypoWithoutHitsVisibleInTheTopWithoutHits(): void
    {
        $phrases = $this->statistics(self::CONTACT_ROWS, 4520)->getTopWithoutHits(self::filter())->phrases;

        self::assertSame(['kontackt'], self::labels($phrases));
        self::assertSame(12, $phrases[0]->count);
    }

    #[Test]
    public function countsOnlySearchesWithHitsInTheTopWithHits(): void
    {
        $phrases = $this->statistics(self::CONTACT_ROWS, 4520)->getTopWithHits(self::filter())->phrases;

        self::assertSame(119, $phrases[0]->count);
    }

    #[Test]
    public function listsEverySpellingWhenSimilarSearchesAreNotMerged(): void
    {
        $phrases = $this->statistics(self::CONTACT_ROWS, 4520)->getAll(self::filter()->setMergeSimilar(false))->phrases;

        self::assertCount(4, $phrases);
        self::assertSame([], array_filter($phrases, static fn(SearchPhrase $phrase): bool => $phrase->isTheme()));
    }

    #[Test]
    public function cutsTheTopListsToTheChosenLimit(): void
    {
        $rows = [];
        foreach (['apfel', 'birne', 'kirsche', 'dattel', 'erdbeere', 'feige', 'guave', 'himbeere'] as $index => $fruit) {
            $rows[] = ['keywords' => $fruit, 'language' => 0, 'count' => 20 - $index, 'countWithoutHits' => 0, 'hitsSum' => 10];
        }

        $phrases = $this->statistics($rows, 200)->getTopWithHits(self::filter()->setLimit(5))->phrases;

        self::assertSame(['apfel', 'birne', 'kirsche', 'dattel', 'erdbeere'], self::labels($phrases));
    }

    #[Test]
    public function returnsNothingForAPeriodWithoutSearches(): void
    {
        $list = $this->statistics([], 0)->getAll(self::filter());

        self::assertSame([], $list->phrases);
        self::assertFalse($list->truncated);
    }

    #[Test]
    public function saysWhenOnlyTheMostFrequentTermsWereEvaluated(): void
    {
        $rows = [];
        for ($i = 0; $i < StatisticsRepository::MAXIMUM_TERMS; $i++) {
            $rows[] = ['keywords' => 'term ' . $i, 'language' => 0, 'count' => 1, 'countWithoutHits' => 0, 'hitsSum' => 1];
        }

        self::assertTrue($this->statistics($rows, 30000)->getAll(self::filter()->setMergeSimilar(false))->truncated);
    }
}
