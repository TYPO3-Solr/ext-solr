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

/**
 * Merges the stored spellings of a term per language
 */
final class SearchTermGrouper
{
    public function __construct(
        private readonly SearchTermNormalizer $normalizer,
    ) {}

    /**
     * @param list<array{keywords: string, language: int, count: int, hitsSum: int}> $rows
     * @return list<SearchTermGroup> sorted by count descending, then label
     */
    public function group(array $rows): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $key = $this->normalizer->normalize($row['keywords']);
            if ($key === '') {
                continue;
            }
            $id = $row['language'] . "\n" . $key;
            $total = $totals[$id] ?? ['key' => $key, 'language' => $row['language'], 'count' => 0, 'hitsSum' => 0, 'label' => '', 'labelCount' => 0];
            $total['count'] += $row['count'];
            $total['hitsSum'] += $row['hitsSum'];
            // The most frequent spelling names the term.
            if ($row['count'] > $total['labelCount']) {
                $total['label'] = $this->normalizer->display($row['keywords']);
                $total['labelCount'] = $row['count'];
            }
            $totals[$id] = $total;
        }

        $groups = array_map(
            static fn(array $total): SearchTermGroup => new SearchTermGroup($total['label'], $total['key'], $total['language'], $total['count'], $total['hitsSum']),
            array_values($totals),
        );
        usort($groups, static fn(SearchTermGroup $a, SearchTermGroup $b): int => [$b->count, $a->label] <=> [$a->count, $b->label]);

        return $groups;
    }
}
