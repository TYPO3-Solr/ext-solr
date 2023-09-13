<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\System\TCA;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class to encapsulate TCA specific logic
 */
class TCAService
{
    protected array $tca = [];

    protected array $visibilityAffectingFields = [];

    public function __construct(array $TCA = null)
    {
        $this->tca = (array)($TCA ?? $GLOBALS['TCA']);
    }

    /**
     * Returns the current time from TYPO3 core aspect "date.timestamp"
     *
     * @throws AspectNotFoundException
     */
    protected function getTime(): int
    {
        return GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('date', 'timestamp') ?? time();
    }

    /**
     * Checks if a record is "enabled"
     *
     * A record is considered "enabled" if
     *  - it is not hidden
     *  - it is not deleted
     *  - as a page it is not set to be excluded from search
     */
    public function isEnabledRecord(string $tableName, array $record): bool
    {
        return !((empty($record))
            ||
            (isset($this->tca[$tableName]['ctrl']['enablecolumns']['disabled']) && !empty($record[$this->tca[$tableName]['ctrl']['enablecolumns']['disabled']]))
            ||
            (isset($this->tca[$tableName]['ctrl']['delete']) && !empty($record[$this->tca[$tableName]['ctrl']['delete']]))
            ||
            ($tableName === 'pages' && !empty($record['no_search'])));
    }

    /**
     * Checks whether an end time field exists for the record's table and if so
     * determines a time is set and whether that time is in the past,
     * making the record invisible on the website.
     *
     * @throws AspectNotFoundException
     */
    public function isEndTimeInPast(string $tableName, array $record): bool
    {
        $endTimeInPast = false;

        if (isset($this->tca[$tableName]['ctrl']['enablecolumns']['endtime'])) {
            $endTimeField = $this->tca[$tableName]['ctrl']['enablecolumns']['endtime'];
            if ($record[$endTimeField] > 0) {
                $endTimeInPast = $record[$endTimeField] < $this->getTime();
            }
        }

        return $endTimeInPast;
    }

    /**
     * This method can be used to check if there is a configured key in
     *
     * $GLOBALS['TCA']['my_table']['ctrl']['enablecolumns']
     *
     * Example:
     *
     * $GLOBALS['TCA']['my_table']['ctrl']['enablecolumns']['fe_group'] = 'mygroupfield'
     *
     * ->isEnableColumn('my_table', 'fe_group') will return true, because 'mygroupfield' is
     * configured as column.
     */
    public function isEnableColumn(string $tableName, string $columnName): bool
    {
        return
            isset($GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns']) &&
            array_key_exists($columnName, $GLOBALS['TCA'][$tableName]['ctrl']['enablecolumns'])
        ;
    }

    /**
     * Checks whether a start time field exists for the record's table and if so
     * determines a time is set and whether that time is in the future,
     * making the record invisible on the website.
     *
     * @throws AspectNotFoundException
     */
    public function isStartTimeInFuture(string $tableName, array $record): bool
    {
        $startTimeInFuture = false;

        if (isset($this->tca[$tableName]['ctrl']['enablecolumns']['starttime'])) {
            $startTimeField = $this->tca[$tableName]['ctrl']['enablecolumns']['starttime'];
            $startTimeInFuture = $record[$startTimeField] > $this->getTime();
        }

        return $startTimeInFuture;
    }

    /**
     * Checks whether a hidden field exists for the current table and if so
     * determines whether it is set on the current record.
     */
    public function isHidden(string $tableName, array $record): bool
    {
        $hidden = false;

        if (isset($this->tca[$tableName]['ctrl']['enablecolumns']['disabled'])) {
            $hiddenField = $this->tca[$tableName]['ctrl']['enablecolumns']['disabled'];
            $hidden = (bool)$record[$hiddenField];
        }

        return $hidden;
    }

    /**
     * Makes sure that "empty" frontend group fields are always the same value and returns cleaned record
     */
    public function normalizeFrontendGroupField(string $tableName, array $record): array
    {
        if (isset($this->tca[$tableName]['ctrl']['enablecolumns']['fe_group'])) {
            $frontendGroupsField = $this->tca[$tableName]['ctrl']['enablecolumns']['fe_group'];

            if (($record[$frontendGroupsField] ?? null) == '') {
                $record[$frontendGroupsField] = '0';
            }
        }

        return $record;
    }

    /**
     * Returns the uid of original record
     */
    public function getTranslationOriginalUid(string $tableName, array $record): ?int
    {
        if (!isset($this->tca[$tableName]['ctrl']['transOrigPointerField'])) {
            return null;
        }
        return $record[$this->tca[$tableName]['ctrl']['transOrigPointerField']] ?? null;
    }

    /**
     * Retrieves the uid that as marked as original if the record is a translation if not it returns the
     * originalUid.
     */
    public function getTranslationOriginalUidIfTranslated(string $tableName, array $record, int $originalUid): ?int
    {
        if (!$this->isLocalizedRecord($tableName, $record)) {
            return $originalUid;
        }

        return $this->getTranslationOriginalUid($tableName, $record);
    }

    /**
     * Checks whether a record is a localization overlay.
     */
    public function isLocalizedRecord(string $tableName, array $record): bool
    {
        $translationUid = $this->getTranslationOriginalUid($tableName, $record);
        if (is_null($translationUid)) {
            return false;
        }

        $hasTranslationReference = $translationUid > 0;
        if (!$hasTranslationReference) {
            return false;
        }

        return true;
    }

    /**
     * Compiles a list of fields affecting the visibility of a record on the website
     * so that it can be used as comma separated list inside IN SQL queries.
     */
    public function getVisibilityAffectingFieldsByTable(string $tableName): string
    {
        if (isset($this->visibilityAffectingFields[$tableName])) {
            return $this->visibilityAffectingFields[$tableName];
        }

        // we always want to get the uid and pid, although they do not affect visibility
        $fields = ['uid', 'pid'];
        if (isset($this->tca[$tableName]['ctrl']['enablecolumns'])) {
            $fields = array_merge($fields, $this->tca[$tableName]['ctrl']['enablecolumns']);
        }

        if (isset($this->tca[$tableName]['ctrl']['delete'])) {
            $fields[] = $this->tca[$tableName]['ctrl']['delete'];
        }

        if ($tableName === 'pages') {
            $fields[] = 'no_search';
            $fields[] = 'doktype';
        }

        $this->visibilityAffectingFields[$tableName] = implode(', ', $fields);

        return $this->visibilityAffectingFields[$tableName];
    }

    /**
     * Checks if TCA is available for column by table
     */
    public function getHasConfigurationForField(string $tableName, string $fieldName): bool
    {
        return isset($this->tca[$tableName]['columns'][$fieldName]);
    }

    /**
     * Returns the tca configuration for a certain field
     */
    public function getConfigurationForField(string $tableName, string $fieldName): array
    {
        return $this->tca[$tableName]['columns'][$fieldName] ?? [];
    }

    /**
     * Returns the TCA configuration for given table
     */
    public function getTableConfiguration(string $tableName): array
    {
        return $this->tca[$tableName] ?? [];
    }
}
