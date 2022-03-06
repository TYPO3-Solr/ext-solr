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
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class EscapeService
{
    /**
     * Quote and escape search strings
     *
     * @param string|int|float $string String to escape
     * @return string|int|float The escaped/quoted string
     */
    public static function escape($string)
    {
        // when we have a numeric string only, nothing needs to be done
        if (is_numeric($string)) {
            return $string;
        }

        // when no whitespaces are in the query we can also just escape the special characters
        if (preg_match('/\W/', $string) != 1) {
            return static::escapeSpecialCharacters($string);
        }

        // when there are no quotes inside the query string we can also just escape the whole string
        $hasQuotes = strrpos($string, '"') !== false;
        if (!$hasQuotes) {
            return static::escapeSpecialCharacters($string);
        }

        $result = static::tokenizeByQuotesAndEscapeDependingOnContext($string);

        return $result;
    }

    /**
     * Applies trim and htmlspecialchars on the querystring to use it as output.
     *
     * @param string $string
     * @return string
     */
    public static function clean(string $string): string
    {
        $string = trim($string);
        return htmlspecialchars($string);
    }

    /**
     * This method is used to escape the content in the query string surrounded by quotes
     * different, then when it is not in a quoted context.
     *
     * @param string $string
     * @return string
     */
    protected static function tokenizeByQuotesAndEscapeDependingOnContext(string $string): string
    {
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
                $result .= static::escapeSpecialCharacters($segment);
            }

            $segmentsIndex++;
        }

        return $result;
    }

    /**
     * Escapes a value meant to be contained in a phrase with characters with
     * special meanings in Lucene query syntax.
     *
     * @param string $value Unescaped - "dirty" - string
     * @return string Escaped - "clean" - string
     */
    protected static function escapePhrase(string $value): string
    {
        $pattern = '/("|\\\)/';
        $replace = '\\\$1';

        return '"' . preg_replace($pattern, $replace, $value) . '"';
    }

    /**
     * Escapes characters with special meanings in Lucene query syntax.
     *
     * @param string $value Unescaped - "dirty" - string
     * @return string Escaped - "clean" - string
     */
    protected static function escapeSpecialCharacters(string $value): string
    {
        // list taken from http://lucene.apache.org/core/4_4_0/queryparser/org/apache/lucene/queryparser/classic/package-summary.html#package_description
        // which mentions: + - && || ! ( ) { } [ ] ^ " ~ * ? : \ /
        // of which we escape: ( ) { } [ ] ^ " ~ : \ /
        // and explicitly don't escape: + - && || ! * ?
        $pattern = '/(\\(|\\)|\\{|\\}|\\[|\\]|\\^|"|~|\:|\\\\|\\/)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
    }
}
