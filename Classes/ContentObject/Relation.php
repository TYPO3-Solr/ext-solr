<?php
namespace ApacheSolrForTypo3\Solr\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Driver\Statement;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * A content object (cObj) to resolve relations between database records
 *
 * Configuration options:
 *
 * localField: the record's field to use to resolve relations
 * foreignLabelField: Usually the label field to retrieve from the related records is determined automatically using TCA, using this option the desired field can be specified explicitly
 * multiValue: whether to return related records suitable for a multi value field
 * singleValueGlue: when not using multiValue, the related records need to be concatenated using a glue string, by default this is ", ". Using this option a custom glue can be specified. The custom value must be wrapped by pipe (|) characters.
 * relationTableSortingField: field in an mm relation table to sort by, usually "sorting"
 * enableRecursiveValueResolution: if the specified remote table's label field is a relation to another table, the value will be resolve by following the relation recursively.
 * removeEmptyValues: Removes empty values when resolving relations, defaults to TRUE
 * removeDuplicateValues: Removes duplicate values
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Relation
{
    const CONTENT_OBJECT_NAME = 'SOLR_RELATION';

    /**
     * Content object configuration
     *
     * @var array
     */
    protected $configuration = [];

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $this->configuration['enableRecursiveValueResolution'] = 1;
        $this->configuration['removeEmptyValues'] = 1;
    }

    /**
     * Executes the SOLR_RELATION content object.
     *
     * Resolves relations between records. Currently supported relations are
     * TYPO3-style m:n relations.
     * May resolve single value and multi value relations.
     *
     * @param string $name content object name 'SOLR_RELATION'
     * @param array $configuration for the content object
     * @param string $TyposcriptKey not used
     * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $parentContentObject parent content object
     * @return string serialized array representation of the given list
     */
    public function cObjGetSingleExt(
        /** @noinspection PhpUnusedParameterInspection */ $name,
        array $configuration,
        /** @noinspection PhpUnusedParameterInspection */ $TyposcriptKey,
        $parentContentObject
    ) {
        $this->configuration = array_merge($this->configuration, $configuration);

        $relatedItems = $this->getRelatedItems($parentContentObject);

        if (!empty($this->configuration['removeDuplicateValues'])) {
            $relatedItems = array_unique($relatedItems);
        }

        if (empty($configuration['multiValue'])) {
            // single value, need to concatenate related items
            $singleValueGlue = ', ';

            if (!empty($configuration['singleValueGlue'])) {
                $singleValueGlue = trim($configuration['singleValueGlue'], '|');
            }

            $result = implode($singleValueGlue, $relatedItems);
        } else {
            // multi value, need to serialize as content objects must return strings
            $result = serialize($relatedItems);
        }

        return $result;
    }

    /**
     * Gets the related items of the current record's configured field.
     *
     * @param ContentObjectRenderer $parentContentObject parent content object
     * @return array Array of related items, values already resolved from related records
     */
    protected function getRelatedItems(
        ContentObjectRenderer $parentContentObject
    ) {
        $relatedItems = [];

        list($localTableName, $localRecordUid) = explode(':',
            $parentContentObject->currentRecord);

        $localTableNameOrg = $localTableName;

        // pages has a special overlay table constriction
        // @todo this check can be removed when TYPO3 8 support is dropped since pages translations are in pages then as well
        if ($GLOBALS['TSFE']->sys_language_uid > 0 && $localTableName === 'pages' && Util::getIsTYPO3VersionBelow9()) {
            $localTableName = 'pages_language_overlay';
        }

        $localTableTca = $GLOBALS['TCA'][$localTableName];
        $localFieldName = $this->configuration['localField'];

        if (isset($localTableTca['columns'][$localFieldName])) {
            $localFieldTca = $localTableTca['columns'][$localFieldName];
            $localRecordUid = $this->getUidOfRecordOverlay($localTableNameOrg, $localRecordUid);
            if (isset($localFieldTca['config']['MM']) && trim($localFieldTca['config']['MM']) !== '') {
                $relatedItems = $this->getRelatedItemsFromMMTable($localTableName,
                    $localRecordUid, $localFieldTca);
            } else {
                $relatedItems = $this->getRelatedItemsFromForeignTable($localTableName,
                    $localRecordUid, $localFieldTca, $parentContentObject);
            }
        }

        return $relatedItems;
    }

    /**
     * Gets the related items from a table using a n:m relation.
     *
     * @param string $localTableName Local table name
     * @param int $localRecordUid Local record uid
     * @param array $localFieldTca The local table's TCA
     * @return array Array of related items, values already resolved from related records
     */
    protected function getRelatedItemsFromMMTable($localTableName, $localRecordUid, array $localFieldTca) {
        $relatedItems = [];
        $foreignTableName = $localFieldTca['config']['foreign_table'];
        $foreignTableTca = $GLOBALS['TCA'][$foreignTableName];
        $foreignTableLabelField = $this->resolveForeignTableLabelField($foreignTableTca);
        $mmTableName = $localFieldTca['config']['MM'];

        // Remove the first option of foreignLabelField for recursion
        if (strpos($this->configuration['foreignLabelField'], '.') !== false) {
            $foreignTableLabelFieldArr = explode('.', $this->configuration['foreignLabelField']);
            unset($foreignTableLabelFieldArr[0]);
            $this->configuration['foreignLabelField'] = implode('.', $foreignTableLabelFieldArr);
        }

        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);
        $relationHandler->start('', $foreignTableName, $mmTableName, $localRecordUid, $localTableName, $localFieldTca['config']);
        $selectUids = $relationHandler->tableArray[$foreignTableName];
        if (!is_array($selectUids) || count($selectUids) <= 0) {
            return $relatedItems;
        }

        $relatedRecords = $this->getRelatedRecords($foreignTableName, ...$selectUids);
        foreach ($relatedRecords as $record) {
            if (isset($foreignTableTca['columns'][$foreignTableLabelField]['config']['foreign_table'])
                && $this->configuration['enableRecursiveValueResolution']
            ) {
                if (strpos($this->configuration['foreignLabelField'], '.') !== false) {
                    $foreignLabelFieldArr = explode('.', $this->configuration['foreignLabelField']);
                    unset($foreignLabelFieldArr[0]);
                    $this->configuration['foreignLabelField'] = implode('.', $foreignLabelFieldArr);
                }

                $this->configuration['localField'] = $foreignTableLabelField;

                $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
                $contentObject->start($record, $foreignTableName);

                return $this->getRelatedItems($contentObject);
            } else {
                if ($GLOBALS['TSFE']->sys_language_uid > 0) {
                    $record = $this->getTranslationOverlay($foreignTableName, $record);
                }
                $relatedItems[] = $record[$foreignTableLabelField];
            }
        }

        return $relatedItems;
    }

    /**
     * Resolves the field to use as the related item's label depending on TCA
     * and TypoScript configuration
     *
     * @param array $foreignTableTca The foreign table's TCA
     * @return string The field to use for the related item's label
     */
    protected function resolveForeignTableLabelField(array $foreignTableTca)
    {
        $foreignTableLabelField = $foreignTableTca['ctrl']['label'];

        // when foreignLabelField is not enabled we can return directly
        if (empty($this->configuration['foreignLabelField'])) {
            return $foreignTableLabelField;
        }

        if (strpos($this->configuration['foreignLabelField'], '.') !== false) {
            list($foreignTableLabelField) = explode('.', $this->configuration['foreignLabelField'], 2);
        } else {
            $foreignTableLabelField = $this->configuration['foreignLabelField'];
        }

        return $foreignTableLabelField;
    }

    /**
     * Return the translated record
     *
     * @param string $tableName
     * @param array $record
     * @return array
     */
    protected function getTranslationOverlay($tableName, $record)
    {
        if ($tableName === 'pages') {
            return $GLOBALS['TSFE']->sys_page->getPageOverlay($record, $GLOBALS['TSFE']->sys_language_uid);
        }

        return $GLOBALS['TSFE']->sys_page->getRecordOverlay($tableName, $record, $GLOBALS['TSFE']->sys_language_uid);
    }

    /**
     * Gets the related items from a table using a 1:n relation.
     *
     * @param string $localTableName Local table name
     * @param int $localRecordUid Local record uid
     * @param array $localFieldTca The local table's TCA
     * @param ContentObjectRenderer $parentContentObject parent content object
     * @return array Array of related items, values already resolved from related records
     */
    protected function getRelatedItemsFromForeignTable(
        $localTableName,
        $localRecordUid,
        array $localFieldTca,
        ContentObjectRenderer $parentContentObject
    ) {
        $relatedItems = [];
        $foreignTableName = $localFieldTca['config']['foreign_table'];
        $foreignTableTca = $GLOBALS['TCA'][$foreignTableName];
        $foreignTableLabelField = $this->resolveForeignTableLabelField($foreignTableTca);

            /** @var $relationHandler RelationHandler */
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);

        $itemList = isset($parentContentObject->data[$this->configuration['localField']]) ?
                        $parentContentObject->data[$this->configuration['localField']] : '';

        $relationHandler->start($itemList, $foreignTableName, '', $localRecordUid, $localTableName, $localFieldTca['config']);
        $selectUids = $relationHandler->tableArray[$foreignTableName];

        if (!is_array($selectUids) || count($selectUids) <= 0) {
            return $relatedItems;
        }

        $relatedRecords = $this->getRelatedRecords($foreignTableName, ...$selectUids);

        foreach ($relatedRecords as $relatedRecord) {
            $resolveRelatedValue = $this->resolveRelatedValue(
                $relatedRecord,
                $foreignTableTca,
                $foreignTableLabelField,
                $parentContentObject,
                $foreignTableName
            );
            if (!empty($resolveRelatedValue) || !$this->configuration['removeEmptyValues']) {
                $relatedItems[] = $resolveRelatedValue;
            }
        }

        return $relatedItems;
    }

    /**
     * Resolves the value of the related field. If the related field's value is
     * a relation itself, this method takes care of resolving it recursively.
     *
     * @param array $relatedRecord Related record as array
     * @param array $foreignTableTca TCA of the related table
     * @param string $foreignTableLabelField Field name of the foreign label field
     * @param ContentObjectRenderer $parentContentObject cObject
     * @param string $foreignTableName Related record table name
     *
     * @return string
     */
    protected function resolveRelatedValue(
        array $relatedRecord,
        $foreignTableTca,
        $foreignTableLabelField,
        ContentObjectRenderer $parentContentObject,
        $foreignTableName = ''
    ) {
        if ($GLOBALS['TSFE']->sys_language_uid > 0 && !empty($foreignTableName)) {
            $relatedRecord = $this->getTranslationOverlay($foreignTableName, $relatedRecord);
        }

        $value = $relatedRecord[$foreignTableLabelField];

        if (
            !empty($foreignTableName)
            && isset($foreignTableTca['columns'][$foreignTableLabelField]['config']['foreign_table'])
            && $this->configuration['enableRecursiveValueResolution']
        ) {
            // backup
            $backupRecord = $parentContentObject->data;
            $backupConfiguration = $this->configuration;

            // adjust configuration for next level
            $this->configuration['localField'] = $foreignTableLabelField;
            $parentContentObject->data = $relatedRecord;
            if (strpos($this->configuration['foreignLabelField'], '.') !== false) {
                list(, $this->configuration['foreignLabelField']) = explode('.',
                    $this->configuration['foreignLabelField'], 2);
            } else {
                $this->configuration['foreignLabelField'] = '';
            }

            // recursion
            $relatedItemsFromForeignTable = $this->getRelatedItemsFromForeignTable(
                $foreignTableName,
                $relatedRecord['uid'],
                $foreignTableTca['columns'][$foreignTableLabelField],
                $parentContentObject
            );
            $value = array_pop($relatedItemsFromForeignTable);

            // restore
            $this->configuration = $backupConfiguration;
            $parentContentObject->data = $backupRecord;
        }

        return $parentContentObject->stdWrap($value, $this->configuration);
    }

    /**
     * When the record has an overlay we retrieve the uid of the translated record,
     * to resolve the relations from the translation.
     *
     * @param string $localTableName
     * @param int $localRecordUid
     * @return int
     */
    protected function getUidOfRecordOverlay($localTableName, $localRecordUid)
    {
        // when no language is set at all we do not need to overlay
        if (!isset($GLOBALS['TSFE']->sys_language_uid)) {
            return $localRecordUid;
        }
        // when no language is set we can return the passed recordUid
        if (!$GLOBALS['TSFE']->sys_language_uid > 0) {
            return $localRecordUid;
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($localTableName);

        $record = $queryBuilder
            ->select('*')
            ->from($localTableName)
            ->where(
                $queryBuilder->expr()->eq('uid', $localRecordUid)
            )
            ->execute()
            ->fetch();

        // when the overlay is not an array, we return the localRecordUid
        if (!is_array($record)) {
            return $localRecordUid;
        }

        $overlayUid = $this->getLocalRecordUidFromOverlay($localTableName, $record);
        $localRecordUid = ($overlayUid !== 0) ? $overlayUid : $localRecordUid;
        return $localRecordUid;
    }

    /**
     * This method retrieves the _PAGES_OVERLAY_UID or _LOCALIZED_UID from the localized record.
     *
     * @param string $localTableName
     * @param array $overlayRecord
     * @return int
     */
    protected function getLocalRecordUidFromOverlay($localTableName, $overlayRecord)
    {
        $overlayRecord = $this->getTranslationOverlay($localTableName, $overlayRecord);

        // when there is a _PAGES_OVERLAY_UID | _LOCALIZED_UID in the overlay, we return it
        if ($localTableName === 'pages' && isset($overlayRecord['_PAGES_OVERLAY_UID'])) {
            return (int)$overlayRecord['_PAGES_OVERLAY_UID'];
        } elseif (isset($overlayRecord['_LOCALIZED_UID'])) {
            return (int)$overlayRecord['_LOCALIZED_UID'];
        }

        return 0;
    }

    /**
     * Return records via relation.
     *
     * @param string $foreignTable The table to fetch records from.
     * @param int[] ...$uids The uids to fetch from table.
     * @return array
     */
    protected function getRelatedRecords($foreignTable, int ...$uids): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($foreignTable);
        $queryBuilder->select('*')
            ->from($foreignTable)
            ->where($queryBuilder->expr()->in('uid', $uids));
        if (isset($this->configuration['additionalWhereClause'])) {
            $queryBuilder->andWhere($this->configuration['additionalWhereClause']);
        }
        $statement = $queryBuilder->execute();

        return $this->sortByKeyInIN($statement, 'uid', ...$uids);
    }

    /**
     * Sorts the result set by key in array for IN values.
     *   Simulates MySqls ORDER BY FIELD(fieldname, COPY_OF_IN_FOR_WHERE)
     *   Example: SELECT * FROM a_table WHERE field_name IN (2, 3, 4) SORT BY FIELD(field_name, 2, 3, 4)
     *
     *
     * @param Statement $statement
     * @param string $columnName
     * @param array $arrayWithValuesForIN
     * @return array
     */
    protected function sortByKeyInIN(Statement $statement, string $columnName, ...$arrayWithValuesForIN) : array
    {
        $records = [];
        while ($record = $statement->fetch()) {
            $indexNumber = array_search($record[$columnName], $arrayWithValuesForIN);
            $records[$indexNumber] = $record;
        }
        ksort($records);
        return $records;
    }
}
