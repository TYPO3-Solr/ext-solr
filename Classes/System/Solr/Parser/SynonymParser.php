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
 * Class to parse the synonyms from a solr response.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SynonymParser
{

    /**
     * Parse the solr synonyms response from an json string to an array.
     *
     * @param string $baseWord
     * @param string $jsonString
     * @return array
     */
    public function parseJson($baseWord, $jsonString)
    {
        $decodedResponse = json_decode($jsonString);
        $synonyms = [];
        if (!empty($baseWord)) {
            if (is_array($decodedResponse->{$baseWord})) {
                $synonyms = $decodedResponse->{$baseWord};
            }
        } else {
            if (isset($decodedResponse->synonymMappings->managedMap)) {
                $synonyms = (array)$decodedResponse->synonymMappings->managedMap;
            }
        }

        return $synonyms;
    }

    /**
     * @param string $baseWord
     * @param array $synonyms
     * @return string
     * @throws \Apache_Solr_InvalidArgumentException
     */
    public function toJson($baseWord, $synonyms)
    {
        if (empty($baseWord) || empty($synonyms)) {
            throw new \Apache_Solr_InvalidArgumentException('Must provide base word and synonyms.');
        }

        return json_encode([$baseWord => $synonyms]);
    }
}
