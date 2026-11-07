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

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\SearchTermNormalizer;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SearchTermNormalizerTest extends SetUpUnitTestCase
{
    public static function displayDataProvider(): array
    {
        return [
            'HTML entities from the statistics writer' => ['What is the &#039;Record insert&#039; element', "What is the 'Record insert' element"],
            'escaped colon' => ['price\: 10', 'price: 10'],
            'escaped parentheses' => ['TYPO3 \(v14\)', 'TYPO3 (v14)'],
            'escaped operators of the strict mode' => ['a \| b \& c\;', 'a | b & c;'],
            'whitespace' => ['  News   portal ', 'News portal'],
            'non-breaking space' => ["News\u{00A0}portal", 'News portal'],
        ];
    }

    #[Test]
    #[DataProvider('displayDataProvider')]
    public function displayShowsTheTermAsEntered(string $keywords, string $expected): void
    {
        self::assertSame($expected, (new SearchTermNormalizer())->display($keywords));
    }

    public static function normalizeDataProvider(): array
    {
        return [
            'upper case' => ['News', 'news'],
            'lower case' => ['news', 'news'],
            'umlauts stay' => ['Größe', 'größe'],
            'escaped and entity' => ['Price\: 10 &amp; more', 'price: 10 & more'],
            'only a non-breaking space entity' => ['&nbsp;', ''],
            'only a non-breaking space' => ["\u{00A0}", ''],
            'only spaces' => ['   ', ''],
        ];
    }

    #[Test]
    #[DataProvider('normalizeDataProvider')]
    public function normalizeReturnsTheKeyToMatchSpellingsOn(string $keywords, string $expected): void
    {
        self::assertSame($expected, (new SearchTermNormalizer())->normalize($keywords));
    }
}
