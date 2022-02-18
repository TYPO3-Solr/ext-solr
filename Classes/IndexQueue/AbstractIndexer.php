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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\ContentObject\Classification;
use ApacheSolrForTypo3\Solr\ContentObject\Multivalue;
use ApacheSolrForTypo3\Solr\ContentObject\Relation;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use UnexpectedValueException;

/**
 * An abstract indexer class to collect a few common methods shared with other
 * indexers.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractIndexer
{

    /**
     * Holds the type of the data to be indexed, usually that is the table name.
     *
     * @var string
     */
    protected string $type = '';

    /**
     * Holds field names that are denied to overwrite in thy indexing configuration.
     *
     * @var array
     */
    protected static array $unAllowedOverrideFields = ['type'];

    /**
     * @param string $solrFieldName
     * @return bool
     */
    public static function isAllowedToOverrideField(string $solrFieldName): bool
    {
        return !in_array($solrFieldName, static::$unAllowedOverrideFields);
    }

    /**
     * Adds fields to the document as defined in $indexingConfiguration
     *
     * @param Document $document base document to add fields to
     * @param array $indexingConfiguration Indexing configuration / mapping
     * @param array $data Record data
     * @return Document Modified document with added fields
     */
    protected function addDocumentFieldsFromTyposcript(Document $document, array $indexingConfiguration, array $data, TypoScriptFrontendController $tsfe): Document
    {
        $data = static::addVirtualContentFieldToRecord($document, $data);

        // mapping of record fields => solr document fields, resolving cObj
        foreach ($indexingConfiguration as $solrFieldName => $recordFieldName) {
            if (is_array($recordFieldName)) {
                // configuration for a content object, skipping
                continue;
            }

            if (!static::isAllowedToOverrideField($solrFieldName)) {
                throw new InvalidFieldNameException(
                    'Must not overwrite field .' . $solrFieldName,
                    1435441863
                );
            }

            $fieldValue = $this->resolveFieldValue($indexingConfiguration, $solrFieldName, $data, $tsfe);

            if (is_array($fieldValue)) {
                // multi value
                $document->setField($solrFieldName, $fieldValue);
            } else {
                if ($fieldValue !== '' && $fieldValue !== null) {
                    $document->setField($solrFieldName, $fieldValue);
                }
            }
        }

        return $document;
    }


    /**
     * Add's the content of the field 'content' from the solr document as virtual field __solr_content in the record,
     * to have it available in typoscript.
     *
     * @param Document $document
     * @param array $data
     * @return array
     */
    public static function addVirtualContentFieldToRecord(Document $document, array $data): array
    {
        if (isset($document['content'])) {
            $data['__solr_content'] = $document['content'];
            return $data;
        }
        return $data;
    }

    /**
     * Resolves a field to its value depending on its configuration.
     *
     * This enables you to configure the indexer to put the item/record through
     * cObj processing if wanted/needed. Otherwise the plain item/record value
     * is taken.
     *
     * @param array $indexingConfiguration Indexing configuration as defined in plugin.tx_solr_index.queue.[indexingConfigurationName].fields
     * @param string $solrFieldName A Solr field name that is configured in the indexing configuration
     * @param array $data A record or item's data
     * @param TypoScriptFrontendController $tsfe
     * @return string The resolved string value to be indexed
     */
    protected function resolveFieldValue(
        array  $indexingConfiguration,
        string $solrFieldName,
        array  $data,
        TypoScriptFrontendController $tsfe
    ) {
        if (isset($indexingConfiguration[$solrFieldName . '.'])) {
            // configuration found => need to resolve a cObj

            // need to change directory to make IMAGE content objects work in BE context
            // see http://blog.netzelf.de/lang/de/tipps-und-tricks/tslib_cobj-image-im-backend
            $backupWorkingDirectory = getcwd();
            chdir(Environment::getPublicPath() . '/');

            $tsfe->cObj->start($data, $this->type);
            $fieldValue = $tsfe->cObj->cObjGetSingle(
                $indexingConfiguration[$solrFieldName],
                $indexingConfiguration[$solrFieldName . '.']
            );

            chdir($backupWorkingDirectory);

            if ($this->isSerializedValue($indexingConfiguration,
                $solrFieldName)
            ) {
                $fieldValue = unserialize($fieldValue);
            }
        } elseif (
            substr($indexingConfiguration[$solrFieldName], 0, 1) === '<'
        ) {
            $referencedTsPath = trim(substr($indexingConfiguration[$solrFieldName],
                1));
            $typoScriptParser = GeneralUtility::makeInstance(TypoScriptParser::class);
            // $name and $conf is loaded with the referenced values.
            list($name, $conf) = $typoScriptParser->getVal($referencedTsPath, $GLOBALS['TSFE']->tmpl->setup);

            // need to change directory to make IMAGE content objects work in BE context
            // see http://blog.netzelf.de/lang/de/tipps-und-tricks/tslib_cobj-image-im-backend
            $backupWorkingDirectory = getcwd();
            chdir(Environment::getPublicPath() . '/');

            $tsfe->start($data, $this->type);
            $fieldValue = $tsfe->cObjGetSingle($name, $conf);

            chdir($backupWorkingDirectory);

            if ($this->isSerializedValue($indexingConfiguration,
                $solrFieldName)
            ) {
                $fieldValue = unserialize($fieldValue);
            }
        } else {
            $fieldValue = $data[$indexingConfiguration[$solrFieldName]];
        }

        // detect and correct type for dynamic fields

        // find last underscore, substr from there, cut off last character (S/M)
        $fieldType = substr($solrFieldName, strrpos($solrFieldName, '_') + 1,
            -1);
        if (is_array($fieldValue)) {
            foreach ($fieldValue as $key => $value) {
                $fieldValue[$key] = $this->ensureFieldValueType($value,
                    $fieldType);
            }
        } else {
            $fieldValue = $this->ensureFieldValueType($fieldValue, $fieldType);
        }

        return $fieldValue;
    }

    // Utility methods

    /**
     * Uses a field's configuration to detect whether its value returned by a
     * content object is expected to be serialized and thus needs to be
     * unserialized.
     *
     * @param array $indexingConfiguration Current item's indexing configuration
     * @param string $solrFieldName Current field being indexed
     * @return bool TRUE if the value is expected to be serialized, FALSE otherwise
     */
    public static function isSerializedValue(array $indexingConfiguration, string $solrFieldName): bool
    {
        return static::isSerializedResultFromRegisteredHook($indexingConfiguration, $solrFieldName)
            || static::isSerializedResultFromCustomContentElement($indexingConfiguration, $solrFieldName);
    }

    /**
     * Checks if the response comes from a custom content element that returns a serialized value.
     *
     * @param array $indexingConfiguration
     * @param string $solrFieldName
     * @return bool
     */
    protected static function isSerializedResultFromCustomContentElement(array $indexingConfiguration, string $solrFieldName): bool
    {
        $isSerialized = false;

        // SOLR_CLASSIFICATION - always returns serialized array
        if (($indexingConfiguration[$solrFieldName] ?? null) == Classification::CONTENT_OBJECT_NAME) {
            $isSerialized = true;
        }

        // SOLR_MULTIVALUE - always returns serialized array
        if (($indexingConfiguration[$solrFieldName] ?? null) == Multivalue::CONTENT_OBJECT_NAME) {
            $isSerialized = true;
        }

        // SOLR_RELATION - returns serialized array if multiValue option is set
        if (($indexingConfiguration[$solrFieldName] ?? null) == Relation::CONTENT_OBJECT_NAME && !empty($indexingConfiguration[$solrFieldName . '.']['multiValue'])) {
            $isSerialized = true;
        }

        return $isSerialized;
    }

    /**
     * Checks registered hooks if a SerializedValueDetector detects a serialized response.
     *
     * @param array $indexingConfiguration
     * @param string $solrFieldName
     * @return bool
     */
    protected static function isSerializedResultFromRegisteredHook(array $indexingConfiguration, string $solrFieldName): bool
    {
        if (!is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] ?? null)) {
            return false;
        }

        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue'] as $classReference) {
            $serializedValueDetector = GeneralUtility::makeInstance($classReference);
            if (!$serializedValueDetector instanceof SerializedValueDetector) {
                $message = get_class($serializedValueDetector) . ' must implement interface ' . SerializedValueDetector::class;
                throw new UnexpectedValueException($message, 1404471741);
            }

            $isSerialized = (boolean)$serializedValueDetector->isSerializedValue($indexingConfiguration, $solrFieldName);
            if ($isSerialized) {
                return true;
            }
        }
        return false;
    }

    /**
     * Makes sure a field's value matches a (dynamic) field's type.
     *
     * @param mixed $value Value to be added to a document
     * @param string $fieldType The dynamic field's type
     * @return mixed Returns the value in the correct format for the field type
     */
    protected function ensureFieldValueType($value, $fieldType)
    {
        switch ($fieldType) {
            case 'int':
            case 'tInt':
                $value = intval($value);
                break;

            case 'float':
            case 'tFloat':
                $value = floatval($value);
                break;

            // long and double do not exist in PHP
            // simply make sure it somehow looks like a number
            // <insert PHP rant here>
            case 'long':
            case 'tLong':
                // remove anything that's not a number or negative/minus sign
                $value = preg_replace('/[^0-9\\-]/', '', $value);
                if (trim($value) === '') {
                    $value = 0;
                }
                break;
            case 'double':
            case 'tDouble':
            case 'tDouble4':
                // as long as it's numeric we'll take it, int or float doesn't matter
                if (!is_numeric($value)) {
                    $value = 0;
                }
                break;

            default:
                // assume things are correct for non-dynamic fields
        }

        return $value;
    }
}
