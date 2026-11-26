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

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermGroup;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermGrouper;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermNormalizer;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SearchTermGrouperTest extends SetUpUnitTestCase
{
    /**
     * @param list<array{keywords: string, language: int, count: int, hitsSum: int}> $rows
     * @return list<SearchTermGroup>
     */
    private static function group(array $rows): array
    {
        return (new SearchTermGrouper(new SearchTermNormalizer()))->group($rows);
    }

    #[Test]
    public function mergesSpellingsWithinALanguageAndKeepsLanguagesApart(): void
    {
        $groups = self::group([
            ['keywords' => 'News', 'language' => 0, 'count' => 5, 'hitsSum' => 40],
            ['keywords' => 'news', 'language' => 0, 'count' => 2, 'hitsSum' => 10],
            ['keywords' => 'news', 'language' => 1, 'count' => 3, 'hitsSum' => 0],
        ]);

        self::assertEquals([
            new SearchTermGroup('News', 'news', 0, 7, 50),
            new SearchTermGroup('news', 'news', 1, 3, 0),
        ], $groups);
    }

    #[Test]
    public function mergesEscapedAndUnescapedSearchesAndShowsTheTermAsEntered(): void
    {
        $groups = self::group([
            ['keywords' => 'price\: 10', 'language' => 0, 'count' => 4, 'hitsSum' => 8],
            ['keywords' => 'price: 10', 'language' => 0, 'count' => 1, 'hitsSum' => 2],
        ]);

        self::assertEquals([new SearchTermGroup('price: 10', 'price: 10', 0, 5, 10)], $groups);
    }

    #[Test]
    public function dropsTermsWithoutText(): void
    {
        self::assertSame([], self::group([['keywords' => '&nbsp;', 'language' => 0, 'count' => 3, 'hitsSum' => 0]]));
    }

    #[Test]
    public function sortsByCountThenLabel(): void
    {
        $groups = self::group([
            ['keywords' => 'beta', 'language' => 0, 'count' => 2, 'hitsSum' => 0],
            ['keywords' => 'gamma', 'language' => 0, 'count' => 9, 'hitsSum' => 0],
            ['keywords' => 'alpha', 'language' => 0, 'count' => 2, 'hitsSum' => 0],
        ]);

        self::assertSame(['gamma', 'alpha', 'beta'], array_map(static fn(SearchTermGroup $group): string => $group->label, $groups));
    }
}
