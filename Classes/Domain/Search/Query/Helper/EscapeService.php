<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\Helper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * The EscpaeService is responsible to escape the querystring as expected for Apache Solr.
 *
 * This class should have no dependencies since it only contains static functions
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class EscapeService
{

    /**
     * Escapes Lucene syntax in $string. $allowOperatorSyntax=false also
     * escapes the SolrJ-special chars `| & ;`.
     *
     * @param string|int|float $string String to escape
     * @return string|int|float The escaped/quoted string
     */
    public static function escape($string, bool $allowOperatorSyntax = true)
    {
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

        $result = static::tokenizeByQuotesAndEscapeDependingOnContext($string, $allowOperatorSyntax);

        return $result;
    }

    /**
     * Applies trim and htmlspecialchars on the querystring to use it as output.
     *
     * @param mixed $string
     * @return string
     */
    public static function clean($string): string
    {
        $string = trim($string);
        $string = htmlspecialchars($string);
        return $string;
    }

    /**
     * This method is used to escape the content in the query string surrounded by quotes
     * different then when it is not in a quoted context.
     *
     * @param string $string
     * @return string
     */
    protected static function tokenizeByQuotesAndEscapeDependingOnContext($string, bool $allowOperatorSyntax = true)
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
                $result .= static::escapeSpecialCharacters($segment, $allowOperatorSyntax);
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
    protected static function escapePhrase($value)
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
    protected static function escapeSpecialCharacters($value, bool $allowOperatorSyntax = true)
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
            // @TODO: With only PHP 8 support, replace this with str_contains()
            if (strpos($escapeChars, $char) !== false) {
                $result .= '\\';
            }
            $result .= $char;
        }
        return $result;
    }
}
