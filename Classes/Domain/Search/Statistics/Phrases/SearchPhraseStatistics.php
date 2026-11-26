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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases;

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsFilterDto;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use Doctrine\DBAL\Exception as DBALException;

/**
 * The lists of the statistics module: top searches with hits, without hits
 * and all searches, each with spellings merged and, if wanted, similar
 * searches grouped into themes.
 */
final class SearchPhraseStatistics
{
    /**
     * Repository results per site, languages and start of the period; the lists often share one
     *
     * @var array<string, array{rows: list<array{keywords: string, language: int, count: int, countWithoutHits: int, hitsSum: int}>, searches: int}>
     */
    private array $periods = [];

    public function __construct(
        private readonly StatisticsRepository $repository,
        private readonly SearchTermGrouper $grouper,
        private readonly SearchTermClusterer $clusterer,
    ) {}

    /**
     * @throws DBALException
     */
    public function getTopWithHits(StatisticsFilterDto $filter): SearchPhraseList
    {
        return $this->build(
            $filter,
            $filter->getTopHitsStartDate(),
            $filter->getTopHitsLimit(),
            static fn(array $row): array => ['count' => $row['count'] - $row['countWithoutHits'], 'hitsSum' => $row['hitsSum']],
        );
    }

    /**
     * Searches are split by hits before grouping: a typo without hits stays
     * visible here even if it belongs to a theme with hits.
     *
     * @throws DBALException
     */
    public function getTopWithoutHits(StatisticsFilterDto $filter): SearchPhraseList
    {
        return $this->build(
            $filter,
            $filter->getNoHitsStartDate(),
            $filter->getNoHitsLimit(),
            static fn(array $row): array => ['count' => $row['countWithoutHits'], 'hitsSum' => 0],
        );
    }

    /**
     * @throws DBALException
     */
    public function getAll(StatisticsFilterDto $filter): SearchPhraseList
    {
        return $this->build(
            $filter,
            $filter->getQueriesStartDate(),
            $filter->getQueriesLimit(),
            static fn(array $row): array => ['count' => $row['count'], 'hitsSum' => $row['hitsSum']],
        );
    }

    /**
     * @param callable(array{keywords: string, language: int, count: int, countWithoutHits: int, hitsSum: int}): array{count: int, hitsSum: int} $counts
     *
     * @throws DBALException
     */
    private function build(StatisticsFilterDto $filter, int $startTimestamp, int $limit, callable $counts): SearchPhraseList
    {
        $period = $this->period($filter, $startTimestamp);
        $rows = [];
        foreach ($period['rows'] as $row) {
            $counted = $counts($row);
            if ($counted['count'] > 0) {
                $rows[] = ['keywords' => $row['keywords'], 'language' => $row['language']] + $counted;
            }
        }

        $groups = $this->grouper->group($rows);
        $themes = $filter->isMergeSimilar()
            ? $this->clusterer->cluster($groups)
            : array_map(static fn(SearchTermGroup $group): array => [$group], $groups);

        $phrases = array_map(fn(array $theme): SearchPhrase => $this->phrase($theme, $period['searches']), $themes);
        usort(
            $phrases,
            static fn(SearchPhrase $a, SearchPhrase $b): int => [$b->count, $b->averageHits, $a->label] <=> [$a->count, $a->averageHits, $b->label],
        );

        return new SearchPhraseList(
            array_slice($phrases, 0, max(0, $limit)),
            count($period['rows']) >= StatisticsRepository::MAXIMUM_TERMS,
        );
    }

    /**
     * @param non-empty-list<SearchTermGroup> $theme lead first
     */
    private function phrase(array $theme, int $searches): SearchPhrase
    {
        $variants = count($theme) > 1
            ? array_map(fn(SearchTermGroup $group): SearchPhrase => $this->phrase([$group], $searches), $theme)
            : [];
        $count = array_sum(array_map(static fn(SearchTermGroup $group): int => $group->count, $theme));
        $hitsSum = array_sum(array_map(static fn(SearchTermGroup $group): int => $group->hitsSum, $theme));

        return new SearchPhrase(
            $theme[0]->label,
            $theme[0]->languageId,
            $count,
            $count > 0 ? $hitsSum / $count : 0.0,
            $searches > 0 ? $count * 100 / $searches : 0.0,
            $variants,
        );
    }

    /**
     * @return array{rows: list<array{keywords: string, language: int, count: int, countWithoutHits: int, hitsSum: int}>, searches: int}
     *
     * @throws DBALException
     */
    private function period(StatisticsFilterDto $filter, int $startTimestamp): array
    {
        $key = $filter->getSiteRootPageId() . '|' . implode(',', $filter->getLanguageIds()) . '|' . $startTimestamp . '|' . $filter->getEndDateTimestamp();
        return $this->periods[$key] ??= [
            'rows' => $this->repository->findSearchTermCounts($filter, $startTimestamp),
            'searches' => $this->repository->countSearches($filter, $startTimestamp),
        ];
    }
}
