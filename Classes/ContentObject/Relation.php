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

namespace ApacheSolrForTypo3\Solr\ContentObject;

use ApacheSolrForTypo3\Solr\System\Language\FrontendOverlayService;
use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Result;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\RelationHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;

/**
 * A content object (cObj) to resolve relations between database records
 *
 * Configuration options:
 *
 * localField: the record's field to use to resolve relations
 * foreignLabelField: Usually the label field to retrieve from the related records is determined automatically using TCA, using this option the desired field can be specified explicitly
 * foreignLabel: Defines how to build the label for indexing, stdWrap is applied. Can be used to overrule foreignLabelField. Referencing to "field" e.g. inside dataWrap will resolve to resolved record.
 * multiValue: whether to return related records suitable for a multi value field
 * singleValueGlue: when not using multiValue, the related records need to be concatenated using a glue string, by default this is ", ". Using this option a custom glue can be specified. The custom value must be wrapped by pipe (|) characters.
 * relationTableSortingField: field in a mm relation table to sort by, usually "sorting"
 * enableRecursiveValueResolution: if the specified remote table's label field is a relation to another table, the value will be resolved by following the relation recursively.
 * removeEmptyValues: Removes empty values when resolving relations, defaults to TRUE
 * removeDuplicateValues: Removes duplicate values
 */
class Relation extends AbstractContentObject
{
    public const CONTENT_OBJECT_NAME = 'SOLR_RELATION';

    /**
     * Content object configuration
     */
    protected array $configuration = [];

    protected ?FrontendOverlayService $frontendOverlayService = null;

    public function __construct(
        protected readonly TCAService $tcaService,
    ) {
        $this->configuration['enableRecursiveValueResolution'] = 1;
        $this->configuration['removeEmptyValues'] = 1;
    }

    /**
     * Executes the SOLR_RELATION content object.
     *
     * Resolves relations between records. Currently, supported relations are
     * TYPO3-style m:n relations.
     * May resolve single value and multi value relations.
     *
     * @throws ContentRenderingException
     * @throws DBALException
     * @throws AspectNotFoundException
     *
     * @noinspection PhpMissingReturnTypeInspection, because foreign source inheritance See {@link AbstractContentObject::render()}
     */
    public function render($conf = [])
    {
        $this->configuration = array_merge($this->configuration, $conf);

        $relatedItems = $this->getRelatedItems($this->cObj);

        if (!empty($this->configuration['removeDuplicateValues'])) {
            $relatedItems = array_unique($relatedItems);
        }

        if (empty($conf['multiValue'])) {
            // single value, need to concatenate related items
            $singleValueGlue = !empty($conf['singleValueGlue']) ? trim($conf['singleValueGlue'], '|') : ', ';
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
     *
     * @return array Array of related items, values already resolved from related records
     *
     * @throws ContentRenderingException
     * @throws DBALException
     * @throws AspectNotFoundException
     */
    protected function getRelatedItems(ContentObjectRenderer $parentContentObject): array
    {
        [$table, $uid] = explode(':', $parentContentObject->currentRecord);
        $uid = (int)$uid;
        $field = $this->configuration['localField'];

        if (!$this->tcaService->getHasConfigurationForField($table, $field)) {
            return [];
        }

        $overlayUid = $this->getFrontendOverlayService($parentContentObject)->getUidOfOverlay($table, $field, $uid);
        $fieldTCA = $this->tcaService->getConfigurationForField($table, $field);

        if (isset($fieldTCA['config']['MM']) && trim($fieldTCA['config']['MM']) !== '') {
            $relatedItems = $this->getRelatedItemsFromMMTable(
                $table,
                $overlayUid,
                $fieldTCA,
                $parentContentObject,
            );
        } else {
            $relatedItems = $this->getRelatedItemsFromForeignTable($table, $overlayUid, $fieldTCA, $parentContentObject);
        }

        return $relatedItems;
    }

    /**
     * Gets the related items from a table using the n:m relation.
     *
     * @param string $localTableName Local table name
     * @param int $localRecordUid Local record uid
     * @param array $localFieldTca The local table's TCA
     *
     * @return array Array of related items, values already resolved from related records
     *
     * @throws ContentRenderingException
     * @throws DBALException
     * @throws AspectNotFoundException
     */
    protected function getRelatedItemsFromMMTable(
        string $localTableName,
        int $localRecordUid,
        array $localFieldTca,
        ContentObjectRenderer $parentContentObject,
    ): array {
        $relatedItems = [];
        $foreignTableName = $localFieldTca['config']['foreign_table'];
        $foreignTableTca = $this->tcaService->getTableConfiguration($foreignTableName);
        $foreignTableLabelField = $this->resolveForeignTableLabelField($foreignTableTca);
        $mmTableName = $localFieldTca['config']['MM'];

        // Remove the first option of foreignLabelField for recursion
        if (str_contains($this->configuration['foreignLabelField'] ?? '', '.')) {
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

        /** @var ContentObjectRenderer $contentObject */
        $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObject->setRequest($parentContentObject->getRequest());
        $relatedRecords = $this->getRelatedRecords($foreignTableName, ...$selectUids);
        foreach ($relatedRecords as $record) {
            $contentObject->start($record, $foreignTableName);

            if (isset($foreignTableTca['columns'][$foreignTableLabelField]['config']['foreign_table'])
                && !empty($this->configuration['enableRecursiveValueResolution'])
            ) {
                $this->configuration['localField'] = $foreignTableLabelField;
                if (str_contains($this->configuration['foreignLabelField'] ?? '', '.')) {
                    $foreignTableLabelFieldArr = explode('.', $this->configuration['foreignLabelField']);
                    unset($foreignTableLabelFieldArr[0]);
                    $this->configuration['foreignLabelField'] = implode('.', $foreignTableLabelFieldArr);
                } else {
                    unset($this->configuration['foreignLabelField']);
                }

                $relatedItems = array_merge($relatedItems, $this->getRelatedItems($contentObject));
                continue;
            }
            if ($this->getLanguageUid($parentContentObject) > 0) {
                $record = $this->getFrontendOverlayService($parentContentObject)
                    ->getOverlay($foreignTableName, $record);
            }

            $relatedItems[] = $contentObject->stdWrapValue(
                'foreignLabel',
                $this->configuration,
                $contentObject->stdWrap($record[$foreignTableLabelField] ?? '', $this->configuration) ?? '',
            );
        }

        return $relatedItems;
    }

    /**
     * Resolves the field to use as the related item's label depending on TCA
     * and TypoScript configuration
     *
     * @param array $foreignTableTca The foreign table's TCA
     *
     * @return string|null The field to use for the related item's label
     */
    protected function resolveForeignTableLabelField(array $foreignTableTca): ?string
    {
        $foreignTableLabelField = $foreignTableTca['ctrl']['label'] ?? null;

        // when foreignLabelField is not enabled we can return directly
        if (empty($this->configuration['foreignLabelField'])) {
            return $foreignTableLabelField;
        }

        if (str_contains($this->configuration['foreignLabelField'] ?? '', '.')) {
            [$foreignTableLabelField] = explode('.', $this->configuration['foreignLabelField'], 2);
        } else {
            $foreignTableLabelField = $this->configuration['foreignLabelField'];
        }

        return $foreignTableLabelField;
    }

    /**
     * Gets the related items from a table using a 1:n relation.
     *
     * @param string $localTableName Local table name
     * @param int $localRecordUid Local record uid
     * @param array $localFieldTca The local table's TCA
     * @param ContentObjectRenderer $parentContentObject parent content object
     *
     * @return array Array of related items, values already resolved from related records
     *
     * @throws ContentRenderingException
     * @throws DBALException
     */
    protected function getRelatedItemsFromForeignTable(
        string $localTableName,
        int $localRecordUid,
        array $localFieldTca,
        ContentObjectRenderer $parentContentObject,
    ): array {
        $relatedItems = [];
        $foreignTableName = $localFieldTca['config']['foreign_table'];
        $foreignTableTca = $this->tcaService->getTableConfiguration($foreignTableName);
        $foreignTableLabelField = $this->resolveForeignTableLabelField($foreignTableTca);
        $localField = $this->configuration['localField'];

        /** @var RelationHandler $relationHandler */
        $relationHandler = GeneralUtility::makeInstance(RelationHandler::class);
        if (!empty($localFieldTca['config']['MM'] ?? '')) {
            $relationHandler->start(
                '',
                $foreignTableName,
                $localFieldTca['config']['MM'],
                $localRecordUid,
                $localTableName,
                $localFieldTca['config'],
            );
        } else {
            $itemList = $parentContentObject->data[$localField] ?? '';
            $relationHandler->start($itemList, $foreignTableName, '', $localRecordUid, $localTableName, $localFieldTca['config']);
        }

        $selectUids = $relationHandler->tableArray[$foreignTableName];
        if (!is_array($selectUids) || count($selectUids) <= 0) {
            return $relatedItems;
        }

        $relatedRecords = $this->getRelatedRecords($foreignTableName, ...$selectUids);
        foreach ($relatedRecords as $relatedRecord) {
            $resolveRelatedValues = $this->resolveRelatedValue(
                $relatedRecord,
                $foreignTableTca,
                $foreignTableLabelField,
                $parentContentObject,
                $foreignTableName,
            );

            foreach ($resolveRelatedValues as $resolveRelatedValue) {
                if (!empty($resolveRelatedValue) || !$this->configuration['removeEmptyValues']) {
                    $relatedItems[] = $resolveRelatedValue;
                }
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
     * @throws DBALException
     * @throws ContentRenderingException
     */
    protected function resolveRelatedValue(
        array $relatedRecord,
        array $foreignTableTca,
        string $foreignTableLabelField,
        ContentObjectRenderer $parentContentObject,
        string $foreignTableName = '',
    ): array {
        if ($this->getLanguageUid($parentContentObject) > 0 && !empty($foreignTableName)) {
            $overlayRelatedRecord = $this->getFrontendOverlayService($parentContentObject)->getOverlay($foreignTableName, $relatedRecord);
            // sys_page->getLanguageOverlay() may return NULL if overlays were activated but no overlay
            // was found and LanguageAspect was NOT set to MIXED
            //
            // If so rely on original record data
            $relatedRecord = is_array($overlayRelatedRecord) ? $overlayRelatedRecord : $relatedRecord;
        }

        $contentObject = clone $parentContentObject;
        $contentObject->start($relatedRecord, $foreignTableName);
        $values = [$contentObject->stdWrapValue('foreignLabel', $this->configuration, $relatedRecord[$foreignTableLabelField] ?? '')];

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
            if (str_contains($this->configuration['foreignLabelField'] ?? '', '.')) {
                [, $this->configuration['foreignLabelField']] = explode(
                    '.',
                    $this->configuration['foreignLabelField'],
                    2,
                );
            } else {
                $this->configuration['foreignLabelField'] = '';
            }

            // recursion
            $relatedItemsFromForeignTable = $this->getRelatedItemsFromForeignTable(
                $foreignTableName,
                $relatedRecord['uid'],
                $foreignTableTca['columns'][$foreignTableLabelField],
                $parentContentObject,
            );
            $values = $relatedItemsFromForeignTable;

            // restore
            $this->configuration = $backupConfiguration;
            $parentContentObject->data = $backupRecord;
        }
        foreach ($values as &$value) {
            $value = $parentContentObject->stdWrap($value, $this->configuration) ?? '';
        }

        return $values;
    }

    /**
     * Return records via relation.
     *
     * @param string $foreignTable The table to fetch records from.
     * @param int ...$uids The uids to fetch from table.
     *
     * @throws DBALException
     */
    protected function getRelatedRecords(string $foreignTable, int ...$uids): array
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($foreignTable);
        $queryBuilder->select('*')
            ->from($foreignTable)
            ->where($queryBuilder->expr()->in('uid', $uids));
        if (isset($this->configuration['additionalWhereClause'])) {
            $queryBuilder->andWhere($this->configuration['additionalWhereClause']);
        }
        $queryResult = $queryBuilder->executeQuery();

        return $this->sortByKeyInIN($queryResult, 'uid', ...$uids);
    }

    /**
     * Sorts the result set by key in array for IN values.
     *   Simulates MySqls ORDER BY FIELD(fieldname, COPY_OF_IN_FOR_WHERE)
     *   Example: SELECT * FROM a_table WHERE field_name IN (2, 3, 4) SORT BY FIELD(field_name, 2, 3, 4)
     *
     * @throws DBALException
     */
    protected function sortByKeyInIN(Result $statement, string $columnName, ...$arrayWithValuesForIN): array
    {
        $records = [];
        while ($record = $statement->fetchAssociative()) {
            $indexNumber = array_search($record[$columnName], $arrayWithValuesForIN);
            $records[$indexNumber] = $record;
        }
        ksort($records);
        return $records;
    }

    /**
     * Returns current language id fetched from the Context
     *
     * @throws ContentRenderingException
     */
    protected function getLanguageUid(ContentObjectRenderer $parentContentObject): int
    {
        return $parentContentObject
            ->getRequest()
            ->getAttribute('language')
            ->getLanguageId();
    }

    /**
     * Returns and sets FrontendOverlayService instance to this object.
     *
     * @throws ContentRenderingException
     */
    protected function getFrontendOverlayService(ContentObjectRenderer $parentContentObject): FrontendOverlayService
    {
        if ($this->frontendOverlayService !== null) {
            return $this->frontendOverlayService;
        }

        $siteLanguage = $parentContentObject->getRequest()->getAttribute('language');

        /** @var Context $coreContext */
        $coreContext = clone GeneralUtility::makeInstance(Context::class);
        $coreContext->setAspect('language', LanguageAspectFactory::createFromSiteLanguage($siteLanguage));
        return $this->frontendOverlayService = GeneralUtility::makeInstance(
            FrontendOverlayService::class,
            $this->tcaService,
            $coreContext,
        );
    }
}
