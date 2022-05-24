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
    public function parseJson(string $jsonString): Schema
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
