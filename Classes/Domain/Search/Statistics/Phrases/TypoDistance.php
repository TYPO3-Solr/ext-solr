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
 * Optimal string alignment distance (insert, delete, substitute, swap of
 * neighbours) per character, bounded: it only answers whether two terms are
 * within a maximum, and stops as soon as they cannot be.
 */
final class TypoDistance
{
    public static function within(string $a, string $b, int $maximum): bool
    {
        $first = mb_str_split($a, 1, 'UTF-8');
        $second = mb_str_split($b, 1, 'UTF-8');
        $firstLength = count($first);
        $secondLength = count($second);
        if (abs($firstLength - $secondLength) > $maximum) {
            return false;
        }

        $beforePrevious = [];
        $previous = range(0, $secondLength);
        $previousMinimum = 0;
        for ($i = 1; $i <= $firstLength; $i++) {
            $current = [$i];
            $currentMinimum = $i;
            for ($j = 1; $j <= $secondLength; $j++) {
                $cost = $first[$i - 1] === $second[$j - 1] ? 0 : 1;
                $value = min($previous[$j] + 1, $current[$j - 1] + 1, $previous[$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $first[$i - 1] === $second[$j - 2] && $first[$i - 2] === $second[$j - 1]) {
                    $value = min($value, $beforePrevious[$j - 2] + 1);
                }
                $current[$j] = $value;
                $currentMinimum = min($currentMinimum, $value);
            }
            // A swap reaches back two rows, so only two rows above the
            // maximum rule the terms out.
            if ($currentMinimum > $maximum && $previousMinimum > $maximum) {
                return false;
            }
            $beforePrevious = $previous;
            $previous = $current;
            $previousMinimum = $currentMinimum;
        }

        return $previous[$secondLength] <= $maximum;
    }
}
