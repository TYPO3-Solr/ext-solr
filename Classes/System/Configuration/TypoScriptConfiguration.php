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

namespace ApacheSolrForTypo3\Solr\System\Configuration;

use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Initializer\Record;
use ApacheSolrForTypo3\Solr\System\ContentObject\ContentObjectService;
use ApacheSolrForTypo3\Solr\System\Util\ArrayAccessor;
use InvalidArgumentException;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TypoScript configuration object, used to read all TypoScript configuration.
 *
 * The TypoScriptConfiguration was introduced in order to be able to replace the old,
 * array based configuration with one configuration object.
 *
 * To read the configuration, you should use
 *
 * $configuration->getValueByPath
 *
 * or
 *
 * $configuration->isValidPath
 *
 * to check if a configuration path exists.
 *
 * To ensure Backwards compatibility the TypoScriptConfiguration object implements the
 * ArrayAccess interface (offsetGet,offsetExists,offsetUnset and offsetSet)
 *
 * This was only introduced to be backwards compatible in long term only "getValueByPath", "isValidPath" or
 * speaking methods for configuration settings should be used!
 *
 * @author Marc Bastian Heinrichs <mbh@mbh-software.de>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @copyright (c) 2016 Timo Schmidt <timo.schmidt@dkd.de>
 */
class TypoScriptConfiguration
{
    /**
     * @var ArrayAccessor
     */
    protected ArrayAccessor $configurationAccess;

    /**
     * Holds the pageId in which context the configuration was parsed
     * (normally $GLOBALS['TSFE']->id)
     * @var int
     */
    protected int $contextPageId = 0;

    /**
     * @var ContentObjectService
     */
    protected ContentObjectService $contentObjectService;

    /**
     * @param array $configuration
     * @param int|null $contextPageId
     * @param ContentObjectService|null $contentObjectService
     */
    public function __construct(
        array $configuration,
        int $contextPageId = null,
        ContentObjectService $contentObjectService = null
    ) {
        $this->configurationAccess = new ArrayAccessor($configuration, '.', true);
        $this->contextPageId = $contextPageId ?? 0;
        $this->contentObjectService = $contentObjectService ?? GeneralUtility::makeInstance(ContentObjectService::class);
    }

    /**
     * Checks if a value is 1, '1', 'true'
     * @param mixed $value
     * @return bool
     */
    protected function getBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * This method can be used to only retrieve array keys where the value is not an array.
     *
     * This can be very handy in the configuration when only keys should be taken into account
     * where the value is not a subconfiguration (typically a typoscript object path).
     *
     * @param $inputArray
     * @return array
     */
    protected function getOnlyArrayKeysWhereValueIsNotAnArray($inputArray): array
    {
        $keysWithNonArrayValue = [];

        foreach ($inputArray as $key => $value) {
            if (is_array($value)) {
                // configuration for a content object, skipping
                continue;
            }

            $keysWithNonArrayValue[] = $key;
        }

        return $keysWithNonArrayValue;
    }

    /**
     * Gets the value from a given TypoScript path.
     *
     * In the context of a frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * Example: plugin.tx_solr.search.targetPage
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage']
     *
     * @param string $path TypoScript path
     * @return mixed The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     */
    public function getValueByPath(string $path)
    {
        return $this->configurationAccess->get($path);
    }

    /**
     * This method can be used to get  a configuration value by path if it exists or return a
     * default value when it does not exist.
     *
     * @param string $path
     * @param mixed $defaultValue
     * @return mixed
     */
    public function getValueByPathOrDefaultValue(string $path, $defaultValue)
    {
        $value = $this->getValueByPath($path);
        if (is_null($value)) {
            return $defaultValue;
        }

        return $value;
    }

    /**
     * Gets the parent TypoScript Object from a given TypoScript path.
     *
     * In the context of a frontend content element the path plugin.tx_solr is
     * merged recursive with overrule with the content element specific typoscript
     * settings, like plugin.tx_solr_PiResults_Results, and possible flex form settings
     * (depends on the solr plugin).
     *
     * Example: plugin.tx_solr.index.queue.tt_news.fields.content
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']
     * which is a SOLR_CONTENT cObj.
     *
     * @param string $path TypoScript path
     * @return mixed The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     */
    public function getObjectByPath(string $path)
    {
        if (substr($path, -1) !== '.') {
            $path = rtrim($path, '.');
            $path = substr($path, 0, strrpos($path, '.') + 1);
        }

        return $this->configurationAccess->get($path);
    }

    /**
     * Gets the parent TypoScript Object from a given TypoScript path and if not present return
     * the default value
     *
     * @param string $path
     * @param array $defaultValue
     * @return array
     * @see getObjectByPath()
     */
    public function getObjectByPathOrDefault(string $path, array $defaultValue = []): array
    {
        try {
            $object = $this->getObjectByPath($path);
        } catch (InvalidArgumentException $e) {
            return $defaultValue;
        }

        if (!is_array($object)) {
            return $defaultValue;
        }

        return $object;
    }

    /**
     * Checks whether a given TypoScript path is valid.
     *
     * @param string $path TypoScript path
     * @return bool TRUE if the path resolves, FALSE otherwise
     */
    public function isValidPath(string $path): bool
    {
        $isValidPath = false;

        $pathValue = $this->getValueByPath($path);
        if (!is_null($pathValue)) {
            $isValidPath = true;
        }

        return $isValidPath;
    }

    /**
     * Merges a configuration with another configuration a
     *
     * @param array $configurationToMerge
     * @param bool $addKeys If set to FALSE, keys that are NOT found in $original will not be set. Thus, only existing value can/will be overruled from overrule array.
     * @param bool $includeEmptyValues If set, values from $overrule will overrule if they are empty or zero.
     * @param bool $enableUnsetFeature If set, special values "__UNSET" can be used in overruled array in order to unset array keys in the original array.
     * @return TypoScriptConfiguration
     */
    public function mergeSolrConfiguration(array $configurationToMerge, bool $addKeys = true, bool $includeEmptyValues = true, bool $enableUnsetFeature = true): TypoScriptConfiguration
    {
        $data = $this->configurationAccess->getData();
        ArrayUtility::mergeRecursiveWithOverrule(
            $data['plugin.']['tx_solr.'],
            $configurationToMerge,
            $addKeys,
            $includeEmptyValues,
            $enableUnsetFeature
        );

        $this->configurationAccess->setData($data);

        return $this;
    }

    /**
     * Returns true when ext_solr is enabled
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getEnabled(bool $defaultIfEmpty = false): bool
    {
        $path = 'plugin.tx_solr.enabled';
        $result = $this->getValueByPathOrDefaultValue($path, $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured additionalFields configured for the indexing.
     *
     * plugin.tx_solr.index.additionalFields.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexAdditionalFieldsConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.index.additionalFields.', $defaultIfEmpty);
    }

    /**
     * Returns all solr fields names where a mapping is configured in index.additionalFields
     *
     * Returns all keys from
     * plugin.tx_solr.index.additionalFields.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexMappedAdditionalFieldNames(array $defaultIfEmpty = []): array
    {
        $mappingConfiguration = $this->getIndexAdditionalFieldsConfiguration();
        $mappedFieldNames = $this->getOnlyArrayKeysWhereValueIsNotAnArray($mappingConfiguration);
        return count($mappedFieldNames) == 0 ? $defaultIfEmpty : $mappedFieldNames;
    }

    /**
     * Returns the fieldProcessingInstructions configuration array
     *
     * plugin.tx_solr.index.fieldProcessingInstructions.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexFieldProcessingInstructionsConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.index.fieldProcessingInstructions.', $defaultIfEmpty);
    }

    /**
     * Retrieves the indexing configuration array for an indexing queue by configuration name.
     *
     * plugin.tx_solr.index.queue.<configurationName>.
     *
     * @param string $configurationName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueConfigurationByName(string $configurationName, array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.';
        return $this->getObjectByPathOrDefault($path, $defaultIfEmpty);
    }

    /**
     * Returns an array of all additionalPageIds by index configuration name.
     *
     * plugin.tx_solr.index.queue.pages.additionalPageIds
     *
     * @param string $configurationName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueAdditionalPageIdsByConfigurationName(string $configurationName = 'pages', array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.additionalPageIds';
        $result = $this->getValueByPathOrDefaultValue($path, '');
        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $result);
    }

    /**
     * Returns an array of all allowedPageTypes.
     *
     * plugin.tx_solr.index.queue.pages.allowedPageTypes
     *
     * @param string $configurationName The configuration name of the queue to use.
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueAllowedPageTypesArrayByConfigurationName(string $configurationName = 'pages', array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.allowedPageTypes';
        $result = $this->getValueByPathOrDefaultValue($path, '');
        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $result);
    }

    /**
     * Returns the configured excludeContentByClass patterns as array.
     *
     * plugin.tx_solr.index.queue.pages.excludeContentByClass
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueuePagesExcludeContentByClassArray(array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.pages.excludeContentByClass';
        $result = $this->getValueByPathOrDefaultValue($path, '');

        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $result);
    }

    /**
     * Returns the configured database table for an indexing queue configuration or
     * the configurationName itself that is used by convention as tableName when no
     * other table name is present.
     *
     * plugin.tx_solr.index.queue.<configurationName>.table or configurationName
     *
     * @param string $configurationName
     * @return string
     */
    public function getIndexQueueTableNameOrFallbackToConfigurationName(string $configurationName = ''): string
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.table';
        return (string)$this->getValueByPathOrDefaultValue($path, $configurationName);
    }

    /**
     * Returns the field configuration for a specific index queue.
     *
     * plugin.tx_solr.index.queue.<configurationName>.fields.
     *
     * @param string $configurationName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueFieldsConfigurationByConfigurationName(string $configurationName = '', array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.fields.';
        return $this->getObjectByPathOrDefault($path, $defaultIfEmpty);
    }

    /**
     * Gets an array of tables configured for indexing by the Index Queue. Since the
     * record monitor must watch these tables for manipulation.
     *
     * @return array Array of table names to be watched by the record monitor.
     */
    public function getIndexQueueMonitoredTables(): array
    {
        $monitoredTables = [];

        $indexingConfigurations = $this->getEnabledIndexQueueConfigurationNames();
        foreach ($indexingConfigurations as $indexingConfigurationName) {
            $monitoredTable = $this->getIndexQueueTableNameOrFallbackToConfigurationName($indexingConfigurationName);
            $monitoredTables[] = $monitoredTable;
        }

        return array_values(array_unique($monitoredTables));
    }

    /**
     * This method can be used to check if a table is configured to be monitored by the record monitor.
     *
     * @param string $tableName
     * @return bool
     */
    public function getIndexQueueIsMonitoredTable(string $tableName): bool
    {
        return in_array($tableName, $this->getIndexQueueMonitoredTables(), true);
    }

    /**
     * Returns the configured indexer class that should be used for a certain indexingConfiguration.
     * By default, "ApacheSolrForTypo3\Solr\IndexQueue\Indexer" will be returned.
     *
     * plugin.tx_solr.index.queue.<configurationName>.indexer
     *
     * @param string $configurationName
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getIndexQueueIndexerByConfigurationName(string $configurationName, string $defaultIfEmpty = Indexer::class): string
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.indexer';
        return (string)$this->getValueByPathOrDefaultValue($path, $defaultIfEmpty);
    }

    /**
     * Returns the configuration of an indexer for a special indexingConfiguration.
     * By default, an empty array is returned.
     *
     * plugin.tx_solr.index.queue.<configurationName>.indexer.
     *
     * @param string $configurationName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueIndexerConfigurationByConfigurationName(string $configurationName, array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.indexer.';
        return $this->getObjectByPathOrDefault($path, $defaultIfEmpty);
    }

    /**
     * Returns all solr fields names where a mapping configuration is set for a certain index configuration
     *
     * Returns all keys from
     * plugin.tx_solr.index.queue.<configurationName>.fields.
     *
     * @param string $configurationName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueMappedFieldsByConfigurationName(string $configurationName = '', array $defaultIfEmpty = []): array
    {
        $mappingConfiguration = $this->getIndexQueueFieldsConfigurationByConfigurationName($configurationName);
        $mappedFieldNames = $this->getOnlyArrayKeysWhereValueIsNotAnArray($mappingConfiguration);
        return count($mappedFieldNames) == 0 ? $defaultIfEmpty : $mappedFieldNames;
    }

    /**
     * This method is used to check if an index queue configuration is enabled or not
     *
     * plugin.tx_solr.index.queue.<configurationName> = 1
     *
     * @param string $configurationName
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getIndexQueueConfigurationIsEnabled(string $configurationName, bool $defaultIfEmpty = false): bool
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName;
        $result = $this->getValueByPathOrDefaultValue($path, $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Retrieves an array of enabled index queue configurations.
     *
     * plugin.tx_solr.index.queue.<configurationName>
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getEnabledIndexQueueConfigurationNames(array $defaultIfEmpty = []): array
    {
        $tablesToIndex = [];
        $path = 'plugin.tx_solr.index.queue.';
        $indexQueueConfiguration = $this->getObjectByPathOrDefault($path, []);
        foreach ($indexQueueConfiguration as $configurationName => $indexingEnabled) {
            if (substr($configurationName, -1) != '.' && $indexingEnabled) {
                $tablesToIndex[] = $configurationName;
            }
        }

        return count($tablesToIndex) == 0 ? $defaultIfEmpty : $tablesToIndex;
    }

    /**
     * Retrieves an array of additional fields that will trigger a recursive update of pages
     * when some fields on that page are modified.
     *
     * plugin.tx_solr.index.queue.recursiveUpdateFields
     *
     * @param string $configurationName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueConfigurationRecursiveUpdateFields(string $configurationName, array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.recursiveUpdateFields';
        $recursiveUpdateFieldsString = $this->getValueByPathOrDefaultValue($path, '');
        if (trim($recursiveUpdateFieldsString) === '') {
            return $defaultIfEmpty;
        }
        $recursiveUpdateFields = GeneralUtility::trimExplode(',', $recursiveUpdateFieldsString);
        // For easier check later on we return an array by combining $recursiveUpdateFields
        return array_combine($recursiveUpdateFields, $recursiveUpdateFields);
    }

    /**
     * Retrieves and initialPagesAdditionalWhereClause where clause when configured or an empty string.
     *
     * plugin.tx_solr.index.queue.<configurationName>.initialPagesAdditionalWhereClause
     *
     * @param string $configurationName
     * @return string
     */
    public function getInitialPagesAdditionalWhereClause(string $configurationName): string
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.initialPagesAdditionalWhereClause';
        $initialPagesAdditionalWhereClause = $this->getValueByPathOrDefaultValue($path, '');

        if (trim($initialPagesAdditionalWhereClause) === '') {
            return '';
        }

        return trim($initialPagesAdditionalWhereClause);
    }

    /**
     * Retrieves and additional where clause when configured or an empty string.
     *
     * plugin.tx_solr.index.queue.<configurationName>.additionalWhereClause
     *
     * @param string $configurationName
     * @return string
     */
    public function getIndexQueueAdditionalWhereClauseByConfigurationName(string $configurationName): string
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.additionalWhereClause';
        $additionalWhere = $this->getValueByPathOrDefaultValue($path, '');

        if (trim($additionalWhere) === '') {
            return '';
        }

        return ' AND ' . $additionalWhere;
    }

    /**
     * This method can be used to retrieve all index queue configuration names, where
     * a certain table is used. It can be configured with the property "table" or is using the configuration
     * key a fallback for the table name.
     *
     * plugin.tx_solr.index.queue.<configurationName>.
     *
     * @param string $tableName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getIndexQueueConfigurationNamesByTableName(string $tableName, array $defaultIfEmpty = []): array
    {
        $path = 'plugin.tx_solr.index.queue.';
        $configuration = $this->getObjectByPathOrDefault($path, []);
        $possibleConfigurations = [];

        foreach ($configuration as $configurationName => $indexingEnabled) {
            $isObject = substr($configurationName, -1) === '.';
            if ($isObject || !$indexingEnabled) {
                continue;
            }

            // when the configuration name equals the tableName we have a fallback
            $hasTableNameAsConfigurationName = $configurationName == $tableName;
            $hasTableAssignedInQueueConfiguration = isset($configuration[$configurationName . '.']['table']) &&
                                                    $configuration[$configurationName . '.']['table'] == $tableName;
            if ($hasTableNameAsConfigurationName || $hasTableAssignedInQueueConfiguration) {
                $possibleConfigurations[] = $configurationName;
            }
        }

        return count($possibleConfigurations) > 0 ? $possibleConfigurations : $defaultIfEmpty;
    }

    /**
     * This method is used to retrieve the className of a queue initializer for a certain indexing configuration
     * of returns the default initializer class, when noting is configured.
     *
     * plugin.tx_solr.index.queue.<configurationName>.initialization
     *
     * @param string $configurationName
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getIndexQueueInitializerClassByConfigurationName(string $configurationName, string $defaultIfEmpty = Record::class): string
    {
        $path = 'plugin.tx_solr.index.queue.' . $configurationName . '.initialization';
        return (string)$this->getValueByPathOrDefaultValue($path, $defaultIfEmpty);
    }

    /**
     * Returns the _LOCAL_LANG configuration from the TypoScript.
     *
     * plugin.tx_solr._LOCAL_LANG.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getLocalLangConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr._LOCAL_LANG.', $defaultIfEmpty);
    }

    /**
     * When this is enabled the output of the devlog, will be printed as debug output.
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingDebugOutput(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.debugOutput', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if query filters should be written to the log.
     *
     * plugin.tx_solr.logging.query.filters
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryFilters(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.filters', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the querystring should be logged or not.
     *
     * plugin.tx_solr.logging.query.queryString
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryQueryString(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.queryString', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the searchWords should be logged or not.
     *
     * plugin.tx_solr.logging.query.searchWords
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQuerySearchWords(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.searchWords', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the rawGet requests should be logged or not.
     *
     * plugin.tx_solr.logging.query.rawGet
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryRawGet(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.rawGet', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the rawPost requests should be logged or not.
     *
     * plugin.tx_solr.logging.query.rawPost
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryRawPost(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.rawPost', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the rawDelete requests should be logged or not.
     *
     * plugin.tx_solr.logging.query.rawDelete
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingQueryRawDelete(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.query.rawDelete', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if exceptions should be logged or not.
     *
     * plugin.tx_solr.logging.exceptions
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingExceptions(bool $defaultIfEmpty = true): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.exceptions', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if indexing operations should be logged or not.
     *
     * plugin.tx_solr.logging.indexing
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexing(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if indexing queue operations should be logged or not.
     *
     * plugin.tx_solr.logging.indexing.queue
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexingQueue(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing.queue', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * This method can be used to check if the logging during indexing should be done.
     * It takes the specific configuration by indexQueueConfiguration into account or is using the
     * fallback when the logging is enabled on queue or indexing level.
     *
     * plugin.tx_solr.logging.indexing.queue.<indexQueueConfiguration>
     *
     * @param string $indexQueueConfiguration
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexingQueueOperationsByConfigurationNameWithFallBack(string $indexQueueConfiguration, bool $defaultIfEmpty = false): bool
    {
        // when logging is globally enabled we do not need to check the specific configuration
        if ($this->getLoggingIndexing()) {
            return true;
        }

        // when the logging for indexing is enabled on queue level we also do not need to check the specific configuration
        if ($this->getLoggingIndexingQueue()) {
            return true;
        }

        $path = 'plugin.tx_solr.logging.indexing.queue.' . $indexQueueConfiguration;
        $result = $this->getValueByPathOrDefaultValue($path, $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if a log message should be written when a page was indexed.
     *
     * plugin.tx_solr.logging.indexing.pageIndexed
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexingPageIndexed(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing.pageIndexed', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if a log message should be written when the TYPO3 search markers are missing in the page.
     *
     * plugin.tx_solr.logging.indexing.missingTypo3SearchMarkers
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexingMissingTypo3SearchMarkers(bool $defaultIfEmpty = true): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing.missingTypo3SearchMarkers', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if the initialization of an indexqueue should be logged.
     *
     * plugin.tx_solr.logging.indexing.indexQueueInitialization
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getLoggingIndexingIndexQueueInitialization(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.logging.indexing.indexQueueInitialization', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if the debug mode is enabled or not.
     *
     * plugin.tx_solr.enableDebugMode
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getEnabledDebugMode(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.enableDebugMode', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * @param $path
     * @param $fallbackPath
     * @param $defaultIfBothIsEmpty
     * @return mixed
     */
    public function getValueByPathWithFallbackOrDefaultValueAndApplyStdWrap($path, $fallbackPath, $defaultIfBothIsEmpty)
    {
        $result = (string)$this->getValueByPathOrDefaultValue($path, '');
        if ($result !== '') {
            return $this->renderContentElementOfConfigured($path, $result);
        }

        $result = (string)$this->getValueByPathOrDefaultValue($fallbackPath, $defaultIfBothIsEmpty);
        return $this->renderContentElementOfConfigured($fallbackPath, $result);
    }

    /**
     * Retrieves the complete search configuration
     *
     * plugin.tx_solr.search.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.', $defaultIfEmpty);
    }

    /**
     * Indicates if elevation should be used or not
     *
     * plugin.tx_solr.search.elevation
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchElevation(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.elevation', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if elevated results should be marked
     *
     * plugin.tx_solr.search.elevation.markElevatedResults
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchElevationMarkElevatedResults(bool $defaultIfEmpty = true): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.elevation.markElevatedResults', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if elevation should be forced
     *
     *plugin.tx_solr.search.elevation.forceElevation
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchElevationForceElevation(bool $defaultIfEmpty = true): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.elevation.forceElevation', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if collapsing on a certain field should be used to build variants or not.
     *
     * plugin.tx_solr.search.variants
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchVariants(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.variants', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if collapsing on a certain field should be used or not
     *
     * plugin.tx_solr.search.variants.variantField
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchVariantsField(string $defaultIfEmpty = 'variantId'): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.variants.variantField', $defaultIfEmpty);
    }

    /**
     * Indicates if expanding of collapsed items it activated.
     *
     * plugin.tx_solr.search.variants.expand
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchVariantsExpand(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.variants.expand', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Retrieves the number of elements that should be expanded.
     *
     * plugin.tx_solr.search.variants.limit
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchVariantsLimit(int $defaultIfEmpty = 10): int
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.variants.limit', $defaultIfEmpty);
        return (int)$result;
    }

    /**
     * Indicates if frequent searches should be show or not.
     *
     * plugin.tx_solr.search.frequentSearches
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFrequentSearches(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the sub configuration of the frequentSearches
     *
     * plugin.tx_solr.search.frequentSearches.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFrequentSearchesConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.frequentSearches.', $defaultIfEmpty);
    }

    /**
     * Retrieves the minimum font size that should be used for the frequentSearches.
     *
     * plugin.tx_solr.search.frequentSearches.minSize
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchFrequentSearchesMinSize(int $defaultIfEmpty = 14): int
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches.minSize', $defaultIfEmpty);
        return (int)$result;
    }

    /**
     * Retrieves the maximum font size that should be used for the frequentSearches.
     *
     * plugin.tx_solr.search.frequentSearches.minSize
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchFrequentSearchesMaxSize(int $defaultIfEmpty = 32): int
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches.maxSize', $defaultIfEmpty);
        return (int)$result;
    }

    /**
     * Indicates if frequent searches should be show or not.
     *
     * plugin.tx_solr.search.frequentSearches.useLowercaseKeywords
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFrequentSearchesUseLowercaseKeywords(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.frequentSearches.useLowercaseKeywords', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configuration if the search should be initialized with an empty query.
     *
     * plugin.tx_solr.search.initializeWithEmptyQuery
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchInitializeWithEmptyQuery(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.initializeWithEmptyQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured initial query
     *
     * plugin.tx_solr.search.initializeWithQuery
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchInitializeWithQuery(string $defaultIfEmpty = ''): string
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.initializeWithQuery', $defaultIfEmpty);
        return (string)$result;
    }

    /**
     * Returns if the last searches should be displayed or not.
     *
     * plugin.tx_solr.search.lastSearches
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchLastSearches(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.lastSearches', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the lastSearch mode. "user" for user specific
     *
     * plugin.tx_solr.search.lastSearches.mode
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchLastSearchesMode(string $defaultIfEmpty = 'user'): string
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.lastSearches.mode', $defaultIfEmpty);
        return (string)$result;
    }

    /**
     * Returns the lastSearch limit
     *
     * plugin.tx_solr.search.lastSearches.limit
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchLastSearchesLimit(int $defaultIfEmpty = 10): int
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.lastSearches.limit', $defaultIfEmpty);
        return (int)$result;
    }

    /**
     * Indicates if the results of an initial empty query should be shown or not.
     *
     * plugin.tx_solr.search.showResultsOfInitialEmptyQuery
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchShowResultsOfInitialEmptyQuery(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.showResultsOfInitialEmptyQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if the results of an initial search query should be shown.
     *
     * plugin.tx_solr.search.showResultsOfInitialQuery
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchShowResultsOfInitialQuery(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.showResultsOfInitialQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if sorting was enabled or not.
     *
     * plugin.tx_solr.search.sorting
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchSorting(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.sorting', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the sorting options configurations.
     *
     * plugin.tx_solr.search.sorting.options.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchSortingOptionsConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.sorting.options.', $defaultIfEmpty);
    }

    /**
     * Retrieves the sorting default order for a sort option.
     *
     * plugin.tx_solr.search.sorting.options.<sortOptionName>.defaultOrder
     *
     * or
     *
     * plugin.tx_solr.search.sorting.defaultOrder
     *
     *
     * @param string $sortOptionName
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchSortingDefaultOrderBySortOptionName(string $sortOptionName = '', string $defaultIfEmpty = 'asc'): string
    {
        $sortOrderSpecificPath = 'plugin.tx_solr.search.sorting.options.' . $sortOptionName . '.defaultOrder';
        $specificSortOrder = $this->getValueByPathOrDefaultValue($sortOrderSpecificPath, null);

        // if we have a concrete setting, use it
        if ($specificSortOrder !== null) {
            return mb_strtolower($specificSortOrder);
        }

        // no specific setting, check common setting
        $commonPath = 'plugin.tx_solr.search.sorting.defaultOrder';
        $commonATagParamOrDefaultValue = $this->getValueByPathOrDefaultValue($commonPath, $defaultIfEmpty);
        return mb_strtolower($commonATagParamOrDefaultValue);
    }

    /**
     * Returns the trusted fields configured for the search that do not need to be escaped.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchTrustedFieldsArray(array $defaultIfEmpty = ['url']): array
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.trustedFields', '');

        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $result);
    }

    /**
     * Indicates if the plugin arguments should be kept in the search form for a second submission.
     *
     * plugin.tx_solr.search.keepExistingParametersForNewSearches
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchKeepExistingParametersForNewSearches(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.keepExistingParametersForNewSearches', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns if an empty query is allowed on the query level.
     *
     * plugin.tx_solr.search.query.allowEmptyQuery
     *
     * @param string $defaultIfEmpty
     * @return bool
     */
    public function getSearchQueryAllowEmptyQuery(string $defaultIfEmpty = ''): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.allowEmptyQuery', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the filter configuration array
     *
     * plugin.tx_solr.search.query.filter.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchQueryFilterConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.query.filter.', $defaultIfEmpty);
    }

    /**
     * Can be used to overwrite the filterConfiguration.
     *
     * plugin.tx_solr.search.query.filter.
     *
     * @param array $configuration
     */
    public function setSearchQueryFilterConfiguration(array $configuration)
    {
        $this->configurationAccess->set('plugin.tx_solr.search.query.filter.', $configuration);
    }

    /**
     * Removes the pageSections filter setting.
     */
    public function removeSearchQueryFilterForPageSections()
    {
        $this->configurationAccess->reset('plugin.tx_solr.search.query.filter.__pageSections');
    }

    /**
     * Returns the configured queryFields from TypoScript
     *
     * plugin.tx_solr.search.query.queryFields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchQueryQueryFields(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.queryFields', $defaultIfEmpty);
    }

    /**
     * This method is used to check if a phrase search is enabled or not
     *
     * plugin.tx_solr.search.query.phrase = 1
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getPhraseSearchIsEnabled(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.phrase', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured phrase fields from TypoScript
     *
     * plugin.tx_solr.search.query.phrase.fields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchQueryPhraseFields(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.phrase.fields', $defaultIfEmpty);
    }

    /**
     * This method is used to check if a bigram phrase search is enabled or not
     *
     * plugin.tx_solr.search.query.bigramPhrase = 1
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getBigramPhraseSearchIsEnabled(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.bigramPhrase', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured phrase fields from TypoScript
     *
     * plugin.tx_solr.search.query.bigramPhrase.fields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchQueryBigramPhraseFields(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.bigramPhrase.fields', $defaultIfEmpty);
    }

    /**
     * This method is used to check if a trigram phrase search is enabled or not
     *
     * plugin.tx_solr.search.query.trigramPhrase = 1
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getTrigramPhraseSearchIsEnabled(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.trigramPhrase', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Returns the configured trigram phrase fields from TypoScript
     *
     * plugin.tx_solr.search.query.trigramPhrase.fields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchQueryTrigramPhraseFields(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.query.trigramPhrase.fields', $defaultIfEmpty);
    }

    /**
     * Returns the configured returnFields as array.
     *
     * plugin.tx_solr.search.query.returnFields
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchQueryReturnFieldsAsArray(array $defaultIfEmpty = []): array
    {
        $returnFields = $this->getValueByPath('plugin.tx_solr.search.query.returnFields');
        if (is_null($returnFields)) {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $returnFields);
    }

    /**
     * Returns the configured target page for the search.
     * By default, the contextPageId will be used
     *
     * plugin.tx_solr.search.targetPage
     *
     * @return int
     */
    public function getSearchTargetPage(): int
    {
        $targetPage = (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.targetPage', 0);
        if ($targetPage === 0) {
            // when no specific page was configured we use the contextPageId (which is usual $GLOBALS['TSFE']->id)
            $targetPage = $this->contextPageId;
        }

        return $targetPage;
    }

    /**
     * Retrieves the targetPage configuration.
     *
     * plugin.tx_solr.search.targetPage.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchTargetPageConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.targetPage.', $defaultIfEmpty);
    }

    /**
     * Method to check if the site highlighting is enabled. When the siteHighlighting is enabled the
     * sword_list parameter is added to the results link.
     *
     * plugin.tx_solr.search.results.siteHighlighting
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchResultsSiteHighlighting(bool $defaultIfEmpty = true): bool
    {
        $isSiteHighlightingEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.siteHighlighting', $defaultIfEmpty);
        return $this->getBool($isSiteHighlightingEnabled);
    }

    /**
     * Can be used to check if the highlighting is enabled
     *
     * plugin.tx_solr.search.results.resultsHighlighting
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getIsSearchResultsHighlightingEnabled(bool $defaultIfEmpty = false): bool
    {
        $isHighlightingEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting', $defaultIfEmpty);
        return $this->getBool($isHighlightingEnabled);
    }

    /**
     * Returns the result highlighting fields.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.highlightFields
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchResultsHighlightingFields(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.highlightFields', $defaultIfEmpty);
    }

    /**
     * Returns the result highlighting fields as array.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.highlightFields
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsHighlightingFieldsAsArray(array $defaultIfEmpty = []): array
    {
        $highlightingFields = $this->getSearchResultsHighlightingFields();

        if ($highlightingFields === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $highlightingFields, true);
    }

    /**
     * Returns the fragmentSize for highlighted segments.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.fragmentSize
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchResultsHighlightingFragmentSize(int $defaultIfEmpty = 200): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.fragmentSize', $defaultIfEmpty);
    }

    /**
     * Returns the fragmentSeparator for highlighted segments.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.fragmentSeparator
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchResultsHighlightingFragmentSeparator(string $defaultIfEmpty = '[...]'): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.fragmentSeparator', $defaultIfEmpty);
    }

    /**
     * Returns the number of results that should be shown per page.
     *
     * plugin.tx_solr.search.results.resultsPerPage
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchResultsPerPage(int $defaultIfEmpty = 10): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsPerPage', $defaultIfEmpty);
    }

    /**
     * Returns the available options for the per page switch.
     *
     * plugin.tx_solr.search.results.resultsPerPageSwitchOptions
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchResultsPerPageSwitchOptionsAsArray(array $defaultIfEmpty = []): array
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsPerPageSwitchOptions', '');

        if (trim($result) === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::intExplode(',', $result, true);
    }

    /**
     * Returns the maximum number of links shown in the paginator.
     *
     * plugin.tx_solr.search.results.maxPaginatorLinks
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getMaxPaginatorLinks(int $defaultIfEmpty = 0): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.maxPaginatorLinks', $defaultIfEmpty);
    }

    /**
     * Returns the configured wrap for the resultHighlighting.
     *
     * plugin.tx_solr.search.results.resultsHighlighting.wrap
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchResultsHighlightingWrap(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.results.resultsHighlighting.wrap', $defaultIfEmpty);
    }

    /**
     * Indicates if spellchecking is enabled or not.
     *
     * plugin.tx_solr.search.spellchecking
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchSpellchecking(bool $defaultIfEmpty = false): bool
    {
        $isFacetingEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking', $defaultIfEmpty);
        return $this->getBool($isFacetingEnabled);
    }

    /**
     * Returns the numberOfSuggestionsToTry that should be used for the spellchecking.
     *
     * plugin.tx_solr.search.spellchecking.numberOfSuggestionsToTry
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchSpellcheckingNumberOfSuggestionsToTry(int $defaultIfEmpty = 1): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking.numberOfSuggestionsToTry', $defaultIfEmpty);
    }

    /**
     * Indicates if a second search should be fired from the spellchecking suggestion if no results could be found.
     *
     * plugin.tx_solr.search.spellchecking.searchUsingSpellCheckerSuggestion
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchSpellcheckingSearchUsingSpellCheckerSuggestion(bool $defaultIfEmpty = false): bool
    {
        $result = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.spellchecking.searchUsingSpellCheckerSuggestion', $defaultIfEmpty);
        return $this->getBool($result);
    }

    /**
     * Indicates if faceting is enabled or not.
     *
     * plugin.tx_solr.search.faceting
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFaceting(bool $defaultIfEmpty = false): bool
    {
        $isFacetingEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting', $defaultIfEmpty);
        return $this->getBool($isFacetingEnabled);
    }

    /**
     * Retrieves the showEvenWhenEmpty for a facet by facet name. If nothing specific is configured
     * the global showEmptyFacets with be returned.
     *
     * plugin.tx_solr.search.faceting.facets.<facetName>.showEvenWhenEmpty
     *
     * or
     *
     * plugin.tx_solr.search.faceting.showEmptyFacets
     *
     *
     * @param string $facetName
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingShowEmptyFacetsByName(string $facetName = '', bool $defaultIfEmpty = false): bool
    {
        $facetSpecificPath = 'plugin.tx_solr.search.faceting.facets.' . $facetName . '.showEvenWhenEmpty';
        $specificShowWhenEmpty = $this->getValueByPathOrDefaultValue($facetSpecificPath, null);

        // if we have a concrete setting, use it
        if ($specificShowWhenEmpty !== null) {
            return $this->getBool($specificShowWhenEmpty);
        }

        // no specific setting, check common setting
        $commonPath = 'plugin.tx_solr.search.faceting.showEmptyFacets';
        return $this->getBool($this->getValueByPathOrDefaultValue($commonPath, $defaultIfEmpty));
    }

    /**
     * Returns the wrap for the faceting show all link
     *
     * plugin.tx_solr.search.faceting.showAllLink.wrap
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingShowAllLinkWrap(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.showAllLink.wrap', $defaultIfEmpty);
    }

    /**
     * Returns the link url parameters that should be added to a facet.
     *
     * plugin.tx_solr.search.faceting.facetLinkUrlParameters
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingFacetLinkUrlParameters(string $defaultIfEmpty = ''): string
    {
        return trim($this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.facetLinkUrlParameters', $defaultIfEmpty));
    }

    /**
     * Returns if the facetLinkUrlsParameters should be included in the reset link.
     *
     * plugin.tx_solr.search.faceting.facetLinkUrlParameters.useForFacetResetLinkUrl
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl(bool $defaultIfEmpty = true): bool
    {
        $useForFacetResetLinkUrl = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.facetLinkUrlParameters.useForFacetResetLinkUrl', $defaultIfEmpty);
        return $this->getBool($useForFacetResetLinkUrl);
    }

    /**
     * Returns the link url parameters that should be added to a facet as array.
     *
     * plugin.tx_solr.search.faceting.facetLinkUrlParameters
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFacetingFacetLinkUrlParametersAsArray(array $defaultIfEmpty = []): array
    {
        $linkUrlParameters = $this->getSearchFacetingFacetLinkUrlParameters();
        if ($linkUrlParameters === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::explodeUrl2Array($linkUrlParameters);
    }

    /**
     * Return the configured minimumCount value for facets.
     *
     * plugin.tx_solr.search.faceting.minimumCount
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchFacetingMinimumCount(int $defaultIfEmpty = 1): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.minimumCount', $defaultIfEmpty);
    }

    /**
     * Return the configured limit value for facets, used for displaying.
     *
     * plugin.tx_solr.search.faceting.limit
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchFacetingLimit(int $defaultIfEmpty = 10): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.limit', $defaultIfEmpty);
    }

    /**
     * Return the configured limit value for facets, used for the response.
     *
     * plugin.tx_solr.search.faceting.facetLimit
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchFacetingFacetLimit(int $defaultIfEmpty = 100): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.facetLimit', $defaultIfEmpty);
    }

    /**
     * Return the configured url parameter style value for facets, used for building faceting parameters.
     *
     * plugin.tx_solr.search.faceting.urlParameterStyle
     *
     * @param string $defaultUrlParameterStyle
     * @return string
     */
    public function getSearchFacetingUrlParameterStyle(string $defaultUrlParameterStyle = 'index'): string
    {
        return (string)$this->getValueByPathOrDefaultValue(
            'plugin.tx_solr.search.faceting.urlParameterStyle',
            $defaultUrlParameterStyle
        );
    }

    /**
     * Return the configuration if the URL parameters should be sorted.
     *
     * plugin.tx_solr.search.faceting.urlParameterSort
     *
     * @param bool $defaultUrlParameterSort
     * @return bool
     */
    public function getSearchFacetingUrlParameterSort(bool $defaultUrlParameterSort = false): bool
    {
        return (bool)$this->getValueByPathOrDefaultValue(
            'plugin.tx_solr.search.faceting.urlParameterSort',
            $defaultUrlParameterSort
        );
    }

    /**
     * Return the configured faceting sortBy value.
     *
     * plugin.tx_solr.search.faceting.sortBy
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchFacetingSortBy(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.sortBy', $defaultIfEmpty);
    }

    /**
     * Returns if a facets should be kept on selection. Global faceting setting
     * can also be configured on facet level by using
     * (plugin.tx_solr.search.faceting.facets.<fieldName>.keepAllOptionsOnSelection)
     *
     * plugin.tx_solr.search.faceting.keepAllFacetsOnSelection
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingKeepAllFacetsOnSelection(bool $defaultIfEmpty = false): bool
    {
        $keepAllOptionsOnSelection = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.keepAllFacetsOnSelection', $defaultIfEmpty);
        return $this->getBool($keepAllOptionsOnSelection);
    }

    /**
     * Returns if the facet count should be calculated based on the facet selection when
     * plugin.tx_solr.search.faceting.keepAllFacetsOnSelection has been enabled
     *
     * plugin.tx_solr.search.faceting.countAllFacetsForSelection
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchFacetingCountAllFacetsForSelection(bool $defaultIfEmpty = false): bool
    {
        $countAllFacetsForSelection = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.faceting.countAllFacetsForSelection', $defaultIfEmpty);
        return $this->getBool($countAllFacetsForSelection);
    }

    /**
     * Returns the configured faceting configuration.
     *
     * plugin.tx_solr.search.faceting.facets
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFacetingFacets(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.faceting.facets.', $defaultIfEmpty);
    }

    /**
     * Returns the configuration of a single facet by facet name.
     *
     * plugin.tx_solr.search.faceting.facets.<facetName>
     *
     * @param string $facetName
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchFacetingFacetByName(string $facetName, array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.faceting.facets.' . $facetName . '.', $defaultIfEmpty);
    }

    /**
     * Indicates if statistics is enabled or not.
     *
     * plugin.tx_solr.statistics
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getStatistics(bool $defaultIfEmpty = false): bool
    {
        $isStatisticsEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.statistics', $defaultIfEmpty);
        return $this->getBool($isStatisticsEnabled);
    }

    /**
     * Indicates to which length an ip should be anonymized in the statistics
     *
     * plugin.tx_solr.statistics.anonymizeIP
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getStatisticsAnonymizeIP(int $defaultIfEmpty = 0): int
    {
        $anonymizeToLength = $this->getValueByPathOrDefaultValue('plugin.tx_solr.statistics.anonymizeIP', $defaultIfEmpty);
        return (int)$anonymizeToLength;
    }

    /**
     * Indicates if additional debug Data should be added to the statistics
     *
     * plugin.tx_solr.statistics.addDebugData
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getStatisticsAddDebugData(bool $defaultIfEmpty = false): bool
    {
        $statisticsAddDebugDataEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.statistics.addDebugData', $defaultIfEmpty);
        return $this->getBool($statisticsAddDebugDataEnabled);
    }

    /**
     * Indicates if suggestion is enabled or not.
     *
     * plugin.tx_solr.suggest
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSuggest(bool $defaultIfEmpty = false): bool
    {
        $isSuggestionEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest', $defaultIfEmpty);
        return $this->getBool($isSuggestionEnabled);
    }

    /**
     * Indicates if https should be used for the suggestions form.
     *
     * plugin.tx_solr.suggest.forceHttps
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSuggestForceHttps(bool $defaultIfEmpty = false): bool
    {
        $isHttpsForced = $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest.forceHttps', $defaultIfEmpty);
        return $this->getBool($isHttpsForced);
    }

    /**
     * Returns the allowed number of suggestions.
     *
     * plugin.tx_solr.suggest.numberOfSuggestions
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSuggestNumberOfSuggestions(int $defaultIfEmpty = 10): int
    {
        $numberOfSuggestions = $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest.numberOfSuggestions', $defaultIfEmpty);
        return (int)$numberOfSuggestions;
    }

    /**
     * Indicates if the topResults should be shown or not
     *
     * plugin.tx_solr.suggest.showTopResults
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSuggestShowTopResults(bool $defaultIfEmpty = true): bool
    {
        $showTopResults = $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest.showTopResults', $defaultIfEmpty);
        return $this->getBool($showTopResults);
    }

    /**
     * Returns the configured number of top results to show
     *
     * plugin.tx_solr.suggest.numberOfTopResults
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSuggestNumberOfTopResults(int $defaultIfEmpty = 5): int
    {
        $numberOfTopResults = $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest.numberOfTopResults', $defaultIfEmpty);
        return (int)$numberOfTopResults;
    }

    /**
     * Returns additional fields for the top results
     *
     * plugin.tx_solr.suggest.additionalTopResultsFields
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSuggestAdditionalTopResultsFields(array $defaultIfEmpty = []): array
    {
        $additionalTopResultsFields = $this->getValueByPathOrDefaultValue('plugin.tx_solr.suggest.additionalTopResultsFields', '');
        if ($additionalTopResultsFields === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $additionalTopResultsFields, true);
    }

    /**
     * Returns the configured template for a specific template fileKey.
     *
     * plugin.tx_solr.view.templateFiles.<fileKey>
     *
     * @param string $fileKey
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getViewTemplateByFileKey(string $fileKey, string $defaultIfEmpty = ''): string
    {
        $templateFileName = $this->getValueByPathOrDefaultValue('plugin.tx_solr.view.templateFiles.' . $fileKey, $defaultIfEmpty);
        return (string)$templateFileName;
    }

    /**
     * Returns the configured available template files for the flexform.
     *
     * plugin.tx_solr.view.templateFiles.[fileKey].availableTemplates.
     *
     * @param string $fileKey
     * @return array
     */
    public function getAvailableTemplatesByFileKey(string $fileKey): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.view.templateFiles.' . $fileKey . '.availableTemplates.', []);
    }

    /**
     * Returns the configuration of the crop view helper.
     *
     * plugin.tx_solr.viewHelpers.crop.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getViewHelpersCropConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.viewHelpers.crop.', $defaultIfEmpty);
    }

    /**
     * Returns the configuration of the sorting view helper.
     *
     * plugin.tx_solr.viewHelpers.sortIndicator.
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getViewHelpersSortIndicatorConfiguration(array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.viewHelpers.sortIndicator.', $defaultIfEmpty);
    }

    /**
     * Controls whether ext-solr will send commits to solr.
     * Beware: If you disable this, you need to ensure
     * that some other mechanism will commit your changes
     * otherwise they will never be searchable.
     * A good way to achieve this is enabling the solr
     * daemons autoCommit feature.
     *
     * plugin.tx_solr.index.enableCommits
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getEnableCommits(bool $defaultIfEmpty = true): bool
    {
        $enableCommits = $this->getValueByPathOrDefaultValue('plugin.tx_solr.index.enableCommits', $defaultIfEmpty);
        return $this->getBool($enableCommits);
    }

    /**
     * Returns the url namespace that is used for the arguments.
     *
     * plugin.tx_solr.view.pluginNamespace
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchPluginNamespace(string $defaultIfEmpty = 'tx_solr'): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.view.pluginNamespace', $defaultIfEmpty);
    }

    /**
     * Returns true if the global url parameter q, that indicates the query should be used.
     *
     * Should be set to false, when multiple instance on the same page should have their querystring.
     *
     * plugin.tx_solr.search.ignoreGlobalQParameter
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getSearchIgnoreGlobalQParameter(bool $defaultIfEmpty = false): bool
    {
        $enableQParameter = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.ignoreGlobalQParameter', $defaultIfEmpty);
        return $this->getBool($enableQParameter);
    }

    /**
     * Returns the argument names, that should be added to the persistent arguments, as array.
     *
     * plugin.tx_solr.search.additionalPersistentArgumentNames
     *
     * @param array $defaultIfEmpty
     * @return array
     */
    public function getSearchAdditionalPersistentArgumentNames(array $defaultIfEmpty = []): array
    {
        $additionalPersistentArgumentNames = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.additionalPersistentArgumentNames', '');

        if ($additionalPersistentArgumentNames === '') {
            return $defaultIfEmpty;
        }

        return GeneralUtility::trimExplode(',', $additionalPersistentArgumentNames, true);
    }

    /**
     * Method to check if grouping was enabled with typoscript.
     *
     * plugin.tx_solr.search.grouping
     *
     * @param bool $defaultIfEmpty
     * @return bool
     */
    public function getIsSearchGroupingEnabled(bool $defaultIfEmpty = false): bool
    {
        $groupingEnabled = $this->getValueByPathOrDefaultValue('plugin.tx_solr.search.grouping', $defaultIfEmpty);
        return $this->getBool($groupingEnabled);
    }

    /**
     * Returns the configured numberOfGroups.
     *
     * plugin.tx_solr.search.grouping.numberOfGroups
     *
     * @param int $defaultIfEmpty
     * @return int
     */
    public function getSearchGroupingNumberOfGroups(int $defaultIfEmpty = 5): int
    {
        return (int)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.grouping.numberOfGroups', $defaultIfEmpty);
    }

    /**
     * Returns the sortBy configuration for the grouping.
     *
     * plugin.tx_solr.search.grouping.sortBy
     *
     * @param string $defaultIfEmpty
     * @return string
     */
    public function getSearchGroupingSortBy(string $defaultIfEmpty = ''): string
    {
        return (string)$this->getValueByPathOrDefaultValue('plugin.tx_solr.search.grouping.sortBy', $defaultIfEmpty);
    }

    /**
     * Returns the highestValue of the numberOfResultsPerGroup configuration that is globally configured and
     * for each group.
     *
     * plugin.tx_solr.search.grouping.
     *
     * @param ?int $defaultIfEmpty
     * @return int
     */
    public function getSearchGroupingHighestGroupResultsLimit(?int $defaultIfEmpty = 1): int
    {
        $groupingConfiguration = $this->getObjectByPathOrDefault('plugin.tx_solr.search.grouping.', []);
        $highestLimit = $defaultIfEmpty;
        if (!empty($groupingConfiguration['numberOfResultsPerGroup'])) {
            $highestLimit = $groupingConfiguration['numberOfResultsPerGroup'];
        }

        if (!isset($groupingConfiguration['groups.']) || !is_array($groupingConfiguration['groups.'])) {
            return $highestLimit;
        }

        foreach ($groupingConfiguration['groups.'] as $groupConfiguration) {
            if (!empty($groupConfiguration['numberOfResultsPerGroup']) && $groupConfiguration['numberOfResultsPerGroup'] > $highestLimit) {
                $highestLimit = $groupConfiguration['numberOfResultsPerGroup'];
            }
        }

        return $highestLimit;
    }

    /**
     * Returns the valid numberOfResultsPerGroup value for a group.
     *
     * Returns:
     *
     * plugin.tx_solr.search.grouping.groups.<groupName>.numberOfResultsPerGroup if it is set otherwise
     * plugin.tx_solr.search.grouping.numberOfResultsPerGroup
     *
     * @param string $groupName
     * @param ?int $defaultIfEmpty
     * @return int
     */
    public function getSearchGroupingResultLimit(string $groupName, ?int $defaultIfEmpty = 1): ?int
    {
        $specificPath = 'plugin.tx_solr.search.grouping.groups.' . $groupName . 'numberOfResultsPerGroup';
        $specificResultsPerGroup = $this->getValueByPathOrDefaultValue($specificPath, null);

        if ($specificResultsPerGroup !== null) {
            return (int)$specificResultsPerGroup;
        }

        $commonPath = 'plugin.tx_solr.search.grouping.numberOfResultsPerGroup';
        $commonValue = $this->getValueByPathOrDefaultValue($commonPath, null);
        if ($commonValue !== null) {
            return (int)$commonValue;
        }

        return $defaultIfEmpty;
    }

    /**
     * Returns everything that is configured for the groups (plugin.tx_solr.search.grouping.groups.)
     *
     * plugin.tx_solr.search.grouping.groups.
     *
     * @param ?array $defaultIfEmpty
     * @return array
     */
    public function getSearchGroupingGroupsConfiguration(?array $defaultIfEmpty = []): array
    {
        return $this->getObjectByPathOrDefault('plugin.tx_solr.search.grouping.groups.', $defaultIfEmpty);
    }

    /*
     * Applies the stdWrap if it is configured for the path, otherwise the unprocessed value will be returned.
     *
     * @param string $valuePath
     * @param mixed $value
     * @return mixed
     */
    protected function renderContentElementOfConfigured($valuePath, $value)
    {
        $configurationPath = $valuePath . '.';
        $configuration = $this->getObjectByPath($configurationPath);

        if ($configuration == null) {
            return $value;
        }

        return $this->contentObjectService->renderSingleContentObject($value, $configuration);
    }
}
