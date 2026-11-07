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
 * Groups the search terms of one language into themes: typos and longer forms
 * join the most searched term they match. Terms come in sorted by count, so
 * every term joins the earliest matching theme and the result is stable.
 *
 * Matches, compared with umlauts as their base letters:
 * - the same term;
 * - a typo: one edit from 5 characters on, two from 9 on, none above 20 (the
 *   shorter term counts): typos matter in keywords, and the deletion variants of
 *   long questions would fill the memory;
 * - a theme of 5 or more characters at the start of the term or as a whole word
 *   in it, if the term has at most three words: in a longer question a word like
 *   "typo3" names the product, not what is asked.
 */
final class SearchTermClusterer
{
    private const MINIMUM_THEME_LENGTH = 5;
    private const MAXIMUM_WORDS_FOR_LONGER_FORMS = 3;
    private const ONE_TYPO_LENGTH = 5;
    private const TWO_TYPOS_LENGTH = 9;
    private const MAXIMUM_TYPO_LENGTH = 20;
    /**
     * Only terms searched at least this often lead a theme
     */
    private const MINIMUM_LEAD_COUNT = 2;
    /**
     * Leads per language that later terms are compared with
     */
    private const MAXIMUM_LEADS = 2000;

    /**
     * @param list<SearchTermGroup> $groups sorted by count descending
     * @return list<non-empty-list<SearchTermGroup>> themes in the order of their leads, lead first
     */
    public function cluster(array $groups): array
    {
        $themes = [];
        // Per language: folded lead => theme index, and deletion variant => theme indexes
        $leads = [];
        $variants = [];
        foreach ($groups as $group) {
            $language = $group->languageId;
            $term = self::fold($group->key);
            $theme = $this->findTheme($term, $leads[$language] ?? [], $variants[$language] ?? [], $themes);
            if ($theme !== null) {
                $themes[$theme][] = $group;
                continue;
            }

            $themes[] = [$group];
            if ($group->count >= self::MINIMUM_LEAD_COUNT && count($leads[$language] ?? []) < self::MAXIMUM_LEADS) {
                $index = count($themes) - 1;
                $leads[$language][$term] = $index;
                foreach (self::deletions($term, self::maximumTypos(mb_strlen($term, 'UTF-8'))) as $variant) {
                    $variants[$language][$variant][] = $index;
                }
            }
        }

        return $themes;
    }

    /**
     * @param array<string, int> $leads
     * @param array<string, list<int>> $variants
     * @param list<non-empty-list<SearchTermGroup>> $themes
     */
    private function findTheme(string $term, array $leads, array $variants, array $themes): ?int
    {
        $candidates = [];
        if (isset($leads[$term])) {
            $candidates[] = $leads[$term];
        }

        $characters = mb_str_split($term, 1, 'UTF-8');
        $words = preg_split('/[\s\-]+/u', $term) ?: [];
        if (count($words) <= self::MAXIMUM_WORDS_FOR_LONGER_FORMS) {
            for ($length = self::MINIMUM_THEME_LENGTH; $length < count($characters); $length++) {
                $prefix = implode('', array_slice($characters, 0, $length));
                if (isset($leads[$prefix])) {
                    $candidates[] = $leads[$prefix];
                }
            }
            foreach ($words as $word) {
                if (mb_strlen($word, 'UTF-8') >= self::MINIMUM_THEME_LENGTH && isset($leads[$word])) {
                    $candidates[] = $leads[$word];
                }
            }
        }

        $termLength = count($characters);
        foreach (self::deletions($term, self::maximumTypos($termLength)) as $variant) {
            foreach ($variants[$variant] ?? [] as $index) {
                $lead = self::fold($themes[$index][0]->key);
                $maximum = self::maximumTypos(min($termLength, mb_strlen($lead, 'UTF-8')));
                if ($maximum > 0 && TypoDistance::within($term, $lead, $maximum)) {
                    $candidates[] = $index;
                }
            }
        }

        return $candidates === [] ? null : min($candidates);
    }

    private static function maximumTypos(int $length): int
    {
        return match (true) {
            $length > self::MAXIMUM_TYPO_LENGTH => 0,
            $length >= self::TWO_TYPOS_LENGTH => 2,
            $length >= self::ONE_TYPO_LENGTH => 1,
            default => 0,
        };
    }

    /**
     * The term with up to $maximum characters left out, the term itself
     * included: two terms within a distance share such a variant.
     *
     * @return list<string>
     */
    private static function deletions(string $term, int $maximum): array
    {
        $variants = [$term => true];
        $current = [$term];
        for ($round = 0; $round < $maximum; $round++) {
            $next = [];
            foreach ($current as $variant) {
                foreach (self::withOneCharacterLess($variant) as $shorter) {
                    if (!isset($variants[$shorter])) {
                        $variants[$shorter] = true;
                        $next[] = $shorter;
                    }
                }
            }
            $current = $next;
        }

        return array_map('strval', array_keys($variants));
    }

    /**
     * @return list<string>
     */
    private static function withOneCharacterLess(string $term): array
    {
        $shorter = [];
        // Most search terms are ASCII; byte operations are much faster there.
        if (!preg_match('/[^\x00-\x7F]/', $term)) {
            for ($position = strlen($term) - 1; $position >= 0; $position--) {
                $shorter[] = substr_replace($term, '', $position, 1);
            }
            return $shorter;
        }

        $characters = mb_str_split($term, 1, 'UTF-8');
        foreach (array_keys($characters) as $position) {
            $remaining = $characters;
            unset($remaining[$position]);
            $shorter[] = implode('', $remaining);
        }
        return $shorter;
    }

    private static function fold(string $key): string
    {
        return strtr($key, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss']);
    }
}
