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

use ApacheSolrForTypo3\Solr\System\Solr\Schema\Schema;
use stdClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to parse the schema from a solr response.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SchemaParser
{

    /**
     * Parse the solr stopwords response from an json string to an array.
     *
     * @param string $jsonString
     * @return Schema
     */
    public function parseJson($jsonString)
    {
        $decodedResponse = json_decode($jsonString);
        $schemaResponse = $decodedResponse->schema ?? null;

        $schema = GeneralUtility::makeInstance(Schema::class);

        if ($schemaResponse === null) {
            return $schema;
        }

        $language = $this->parseManagedResourceId($schemaResponse);
        $schema->setManagedResourceId($language);

        $name = $this->parseName($schemaResponse);
        $schema->setName($name);

        return $schema;
    }

    /**
     * Extracts the language from a solr schema response.
     *
     * @param stdClass $schema
     * @return ?string
     */
    protected function parseManagedResourceId(stdClass $schema): ?string
    {
        $managedResourceId = null;
        if (!is_object($schema) || !isset($schema->fieldTypes)) {
            return null;
        }

        foreach ($schema->fieldTypes as $fieldType) {
            if ($fieldType->name !== 'text') {
                continue;
            }
            // we have a text field
            foreach ($fieldType->queryAnalyzer->filters as $filter) {
                if ($filter->class === 'solr.ManagedSynonymGraphFilterFactory') {
                    $managedResourceId = $filter->managed;
                }
            }
        }

        return $managedResourceId;
    }

    /**
     * Extracts the schema name from the response.
     *
     * @param stdClass $schemaResponse
     * @return string
     */
    protected function parseName(stdClass $schemaResponse): string
    {
        return $schemaResponse->name ?? '';
    }
}
