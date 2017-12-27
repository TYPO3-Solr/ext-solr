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
 * The EscpaeService is responsible to escape the querystring as ecpected for Apache Solr.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class EscapeService {

    /**
     * Quote and escape search strings
     *
     * @param string|int|double $string String to escape
     * @return string|int|double The escaped/quoted string
     */
    public function escape($string)
    {
        // when we have a numeric string only, nothing needs to be done
        if (is_numeric($string)) {
            return $string;
        }

        // when no whitespaces are in the query we can also just escape the special characters
        if (preg_match('/\W/', $string) != 1) {
            return $this->escapeSpecialCharacters($string);
        }

        // when there are no quotes inside the query string we can also just escape the whole string
        $hasQuotes = strrpos($string, '"') !== false;
        if (!$hasQuotes) {
            return $this->escapeSpecialCharacters($string);
        }

        $result = $this->tokenizeByQuotesAndEscapeDependingOnContext($string);

        return $result;
    }

    /**
     * This method is used to escape the content in the query string surrounded by quotes
     * different then when it is not in a quoted context.
     *
     * @param string $string
     * @return string
     */
    protected function tokenizeByQuotesAndEscapeDependingOnContext($string)
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
                $result .= $this->escapePhrase($segment);
            } else {
                $result .= $this->escapeSpecialCharacters($segment);
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
    protected function escapePhrase($value)
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
    protected function escapeSpecialCharacters($value)
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