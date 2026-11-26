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

use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases\TypoDistance;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class TypoDistanceTest extends SetUpUnitTestCase
{
    public static function distanceDataProvider(): array
    {
        return [
            'one inserted letter' => ['kontakt', 'kontackt', 1, true],
            'swapped neighbours' => ['imgaes', 'images', 1, true],
            'one inserted letter in the middle' => ['kontakt', 'kontrakt', 1, true],
            'swap at the start' => ['form', 'from', 1, true],
            'different words' => ['menu', 'news', 1, false],
            'two edits within two' => ['größe', 'grösse', 2, true],
            'two edits not within one' => ['größe', 'grösse', 1, false],
            'multibyte counted per character' => [str_repeat('ä', 128), str_repeat('ä', 127) . 'a', 1, true],
            'length difference above the maximum' => ['abc', 'abcdef', 2, false],
        ];
    }

    #[Test]
    #[DataProvider('distanceDataProvider')]
    public function decidesWhetherTwoTermsAreWithinTheDistance(string $a, string $b, int $maximum, bool $expected): void
    {
        self::assertSame($expected, TypoDistance::within($a, $b, $maximum));
        self::assertSame($expected, TypoDistance::within($b, $a, $maximum));
    }
}
