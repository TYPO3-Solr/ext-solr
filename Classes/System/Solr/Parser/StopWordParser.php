<?php

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

namespace ApacheSolrForTypo3\Solr\System\Solr\Parser;

use InvalidArgumentException;

/**
 * Class to parse the stopwords from a solr response.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class StopWordParser
{
    /**
     * Parse the solr stopwords response from an json string to an array.
     *
     * @param string $jsonString
     * @return array
     */
    public function parseJson(string $jsonString): array
    {
        $stopWords = [];

        $decodedResponse = json_decode($jsonString);

        if (isset($decodedResponse->wordSet->managedList)) {
            $stopWords = (array)$decodedResponse->wordSet->managedList;
        }

        return $stopWords;
    }

    /**
     * @param string|array $stopWords
     * @return string
     * @throws InvalidArgumentException
     */
    public function toJson($stopWords): string
    {
        if (empty($stopWords)) {
            throw new InvalidArgumentException('Must provide stop word.', 1642968688);
        }

        if (is_string($stopWords)) {
            $stopWords = [$stopWords];
        }

        $stopWords = array_values($stopWords);
        return json_encode($stopWords);
    }
}
