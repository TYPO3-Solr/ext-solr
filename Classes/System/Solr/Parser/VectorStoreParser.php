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

/**
 * Class to parse the vector store data from a solr response.
 */
class VectorStoreParser
{
    public function parseJson(string $jsonString): array
    {
        $decodedResponse = json_decode($jsonString);
        $models = [];

        if (isset($decodedResponse->models)) {
            foreach ((array)$decodedResponse->models as $model) {
                $models[$model->name] = (array)$model;
            }
        }

        return $models;
    }
}
