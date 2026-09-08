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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper;

/**
 * The EscapeService is responsible to escape the querystring as expected for Apache Solr.
 *
 * This class should have no dependencies since it only contains static functions
 */
class EscapeService
{
    /**
     * Escapes Lucene syntax in $string. $allowOperatorSyntax=false also
     * escapes the SolrJ-special chars `| & ;`.
     */
    public static function escape(
        float|int|string $string,
        bool $allowOperatorSyntax = true,
    ): float|int|string {
        // when we have a numeric string only, nothing needs to be done
        if (is_numeric($string)) {
            return $string;
        }

        // when no whitespaces are in the query we can also just escape the special characters
        if (preg_match('/\W/', $string) != 1) {
            return static::escapeSpecialCharacters($string, $allowOperatorSyntax);
        }

        // when there are no quotes inside the query string we can also just escape the whole string
        $hasQuotes = strrpos($string, '"') !== false;
        if (!$hasQuotes) {
            return static::escapeSpecialCharacters($string, $allowOperatorSyntax);
        }

        return static::tokenizeByQuotesAndEscapeDependingOnContext($string, $allowOperatorSyntax);
    }

    /**
     * Applies trim and htmlspecialchars on the querystring to use it as output.
     */
    public static function clean(string $string): string
    {
        $string = trim($string);
        return htmlspecialchars($string);
    }

    /**
     * This method is used to escape the content in the query string surrounded by quotes
     * different, then when it is not in a quoted context.
     */
    protected static function tokenizeByQuotesAndEscapeDependingOnContext(
        string $string,
        bool $allowOperatorSyntax = true,
    ): string {
        $result = '';
        $quotesCount = substr_count($string, '"');
        $isEvenAmountOfQuotes = $quotesCount % 2 === 0;

        // go over all quote segments and apply escapePhrase inside a quoted
        // context and escapeSpecialCharacters outside the quoted context.
        $segments = explode('"', $string);
        $segmentsIndex = 0;
        foreach ($segments as $segment) {
            $isInQuote = $segmentsIndex % 2 !== 0;
            $isLastQuote = $segmentsIndex === $quotesCount;

            if ($isLastQuote && !$isEvenAmountOfQuotes) {
                $result .= '\"';
            }

            if ($isInQuote && !$isLastQuote) {
                $result .= static::escapePhrase($segment);
            } else {
                $result .= static::escapeSpecialCharacters($segment, $allowOperatorSyntax);
            }

            $segmentsIndex++;
        }

        return $result;
    }

    /**
     * Escapes a value meant to be contained in a phrase with characters with
     * special meanings in Lucene query syntax.
     */
    protected static function escapePhrase(string $value): string
    {
        $pattern = '/("|\\\)/';
        $replace = '\\\$1';

        return '"' . preg_replace($pattern, $replace, $value) . '"';
    }

    /**
     * Escapes Lucene special chars. Legacy mode keeps `+ - && || ! * ?`;
     * strict mode additionally escapes `| & ;`. `+ - ! * ?` and whitespace
     * stay literal in both modes.
     */
    protected static function escapeSpecialCharacters(string $value, bool $allowOperatorSyntax = true): string
    {
        if ($allowOperatorSyntax) {
            // list taken from https://lucene.apache.org/core/9_10_0/queryparser/org/apache/lucene/queryparser/classic/package-summary.html#Escaping_Special_Characters
            // which mentions: + - && || ! ( ) { } [ ] ^ " ~ * ? : \ /
            // of which we escape: ( ) { } [ ] ^ " ~ : \ /
            // and explicitly don't escape: + - && || ! * ?
            $pattern = '/(\\(|\\)|\\{|\\}|\\[|\\]|\\^|"|~|\:|\\\\|\\/)/';
            return preg_replace($pattern, '\\\$1', $value);
        }

        $escapeChars = '\\():^[]"{}~|&;/';
        $result = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if (str_contains($escapeChars, $char)) {
                $result .= '\\';
            }
            $result .= $char;
        }
        return $result;
    }
}
