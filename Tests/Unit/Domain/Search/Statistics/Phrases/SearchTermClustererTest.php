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

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermClusterer;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermGroup;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SearchTermClustererTest extends SetUpUnitTestCase
{
    private static function group(string $key, int $count, int $languageId = 0): SearchTermGroup
    {
        return new SearchTermGroup($key, $key, $languageId, $count, 0);
    }

    /**
     * @param list<SearchTermGroup> $groups
     * @return list<list<string>>
     */
    private static function themes(array $groups): array
    {
        return array_map(
            static fn(array $theme): array => array_map(static fn(SearchTermGroup $group): string => $group->key . '@' . $group->languageId, $theme),
            (new SearchTermClusterer())->cluster($groups),
        );
    }

    #[Test]
    public function groupsTyposAndLongerFormsUnderTheMostSearchedTerm(): void
    {
        self::assertSame(
            [['kontakt@0', 'kontaktformular@0', 'kontackt@0'], ['formular@0']],
            self::themes([self::group('kontakt', 88), self::group('formular', 74), self::group('kontaktformular', 31), self::group('kontackt', 12)]),
        );
    }

    #[Test]
    public function treatsUmlautsAsTheirBaseLetters(): void
    {
        self::assertSame([['menü@0', 'menu@0']], self::themes([self::group('menü', 5), self::group('menu', 3)]));
    }

    #[Test]
    public function findsTheThemeAsAWholeWord(): void
    {
        self::assertSame([['contact@0', 'contact form@0']], self::themes([self::group('contact', 10), self::group('contact form', 4)]));
    }

    /**
     * A product name in a long question is no theme: the question asks something else.
     */
    #[Test]
    public function findsTheThemeAsAWholeWordOnlyInShortSearches(): void
    {
        self::assertSame(
            [['typo3@0', 'typo3 templates@0'], ['wie kann ich in typo3 eine sitemap erstellen@0']],
            self::themes([self::group('typo3', 26), self::group('wie kann ich in typo3 eine sitemap erstellen', 18), self::group('typo3 templates', 4)]),
        );
    }

    #[Test]
    public function needsFiveCharactersForATheme(): void
    {
        self::assertSame([['news@0'], ['newsletter@0']], self::themes([self::group('news', 10), self::group('newsletter', 3)]));
    }

    #[Test]
    public function keepsLanguagesApart(): void
    {
        self::assertSame([['typo3@0'], ['typo3@1']], self::themes([self::group('typo3', 10), self::group('typo3', 4, 1)]));
    }

    #[Test]
    public function aSingleSearchJoinsAThemeButLeadsNone(): void
    {
        self::assertSame([['gallery@0', 'galery@0']], self::themes([self::group('gallery', 5), self::group('galery', 1)]));
        self::assertSame([['abcde@0'], ['abcdf@0']], self::themes([self::group('abcde', 1), self::group('abcdf', 1)]));
    }

    /**
     * Visitors of AI search type whole questions; keywords hold up to 128 characters.
     */
    #[Test]
    public function clustersLongQuestionsQuicklyAndWithLittleMemory(): void
    {
        mt_srand(11);
        $groups = [];
        for ($i = 0; $i < 2000; $i++) {
            $question = 'wie kann ich in typo3 ' . $i . ' ';
            while (mb_strlen($question) < 126) {
                $question .= ['größe ', 'ändern ', 'seite ', '🙂 ', 'inhalt '][mt_rand(0, 4)];
            }
            $groups[] = self::group(mb_substr($question, 0, 128), 2);
        }

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $start = hrtime(true);
        $themes = (new SearchTermClusterer())->cluster($groups);
        $seconds = (hrtime(true) - $start) / 1e9;

        self::assertLessThan(2.0, $seconds);
        self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $before);
        self::assertSame(2000, array_sum(array_map('count', $themes)));
    }

    #[Test]
    public function clustersTwentyThousandTermsInUnderTwoSeconds(): void
    {
        mt_srand(7);
        $groups = [];
        for ($i = 0; $i < 20000; $i++) {
            $term = '';
            $length = mt_rand(6, 20);
            for ($j = 0; $j < $length; $j++) {
                $term .= chr(mt_rand(97, 122));
            }
            $groups[] = self::group($term . $i, mt_rand(1, 50));
        }
        usort($groups, static fn(SearchTermGroup $a, SearchTermGroup $b): int => $b->count <=> $a->count);

        $start = hrtime(true);
        $themes = (new SearchTermClusterer())->cluster($groups);
        $seconds = (hrtime(true) - $start) / 1e9;

        self::assertLessThan(2.0, $seconds);
        self::assertSame(20000, array_sum(array_map('count', $themes)));
    }
}
