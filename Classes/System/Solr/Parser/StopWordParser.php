<?php

namespace ApacheSolrForTypo3\Solr\System\Solr\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2016 Timo Hund <timo.hund@dkd.de
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
    public function parseJson($jsonString)
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
     * @throws \Apache_Solr_InvalidArgumentException
     */
    public function toJson($stopWords)
    {
        if (empty($stopWords)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide stop word.');
        }

        if (is_string($stopWords)) {
            $stopWords = [$stopWords];
        }

        $stopWords = array_values($stopWords);
        return json_encode($stopWords);
    }
}
