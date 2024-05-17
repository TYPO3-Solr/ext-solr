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

namespace ApacheSolrForTypo3\Solr\System\Util;

use ApacheSolrForTypo3\Solr\Domain\Site\Site as ExtSolrSite;
use TYPO3\CMS\Core\Site\Entity\Site as CoreSite;

/**
 * This class contains related functions for the new site management that was introduced with TYPO3 9.
 */
class SiteUtility
{
    /**
     * In memory cache indexed by [<root-page-id>][<language-id>]
     */
    private static array $languages = [];

    /**
     * @internal for unit tests tear down methods.
     */
    public static function reset(): void
    {
        self::$languages = [];
    }

    /**
     * This method is used to retrieve the connection configuration from the TYPO3 site configuration.
     *
     * Note: Language context properties have precedence over global settings.
     *
     * The configuration is done in the globals configuration of a site, and be extended in the language specific configuration
     * of a site.
     *
     * Typically, everything except the core name is configured on the global level and the core name differs for each language.
     *
     * In addition, every property can be defined for the ```read``` and ```write``` scope.
     *
     * The convention for property keys is "solr_{propertyName}_{scope}". With the configuration "solr_host_read" you define the host
     * for the solr read connection.
     */
    public static function getConnectionProperty(
        CoreSite $typo3Site,
        string $property,
        int $languageId,
        string $scope,
        mixed $defaultValue = null,
    ): string|int|bool|null {
        $value = self::getConnectionPropertyOrFallback($typo3Site, $property, $languageId, $scope);
        if ($value === null) {
            return $defaultValue;
        }
        return $value;
    }

    /**
     * Builds the Solr connection configuration
     */
    public static function getSolrConnectionConfiguration(
        CoreSite $typo3Site,
        int $languageUid,
    ): ?array {
        $solrEnabled = self::getConnectionProperty($typo3Site, 'enabled', $languageUid, 'read', true);
        $solrReadCore = self::getConnectionProperty($typo3Site, 'core', $languageUid, 'read');
        $solrWriteCore = self::getConnectionProperty($typo3Site, 'core', $languageUid, 'write');
        if (!$solrEnabled || empty($solrReadCore) || empty($solrWriteCore)) {
            return null;
        }

        $rootPageUid = $typo3Site->getRootPageId();
        return [
            'connectionKey' => $rootPageUid . '|' . $languageUid,
            'rootPageUid' => $rootPageUid,
            'read' => [
                'scheme' => self::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'read', 'http'),
                'host' => self::getConnectionProperty($typo3Site, 'host', $languageUid, 'read', 'localhost'),
                'port' => (int)self::getConnectionProperty($typo3Site, 'port', $languageUid, 'read', 8983),
                'path' => self::getConnectionProperty($typo3Site, 'path', $languageUid, 'read', ''),
                'core' => $solrReadCore,
                'username' => self::getConnectionProperty($typo3Site, 'username', $languageUid, 'read', ''),
                'password' => self::getConnectionProperty($typo3Site, 'password', $languageUid, 'read', ''),
            ],
            'write' => [
                'scheme' => self::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'write', 'http'),
                'host' => self::getConnectionProperty($typo3Site, 'host', $languageUid, 'write', 'localhost'),
                'port' => (int)self::getConnectionProperty($typo3Site, 'port', $languageUid, 'write', 8983),
                'path' => self::getConnectionProperty($typo3Site, 'path', $languageUid, 'write', ''),
                'core' => $solrWriteCore,
                'username' => self::getConnectionProperty($typo3Site, 'username', $languageUid, 'write', ''),
                'password' => self::getConnectionProperty($typo3Site, 'password', $languageUid, 'write', ''),
            ],

            'language' => $languageUid,
        ];
    }

    /**
     * Builds the Solr connection configuration for all languages of given TYPO3 site
     */
    public static function getAllSolrConnectionConfigurations(
        CoreSite $typo3Site,
    ): array {
        $connections = [];
        foreach ($typo3Site->getLanguages() as $language) {
            $connection = self::getSolrConnectionConfiguration($typo3Site, $language->getLanguageId());
            if ($connection !== null) {
                $connections[$language->getLanguageId()] = $connection;
            }
        }

        return $connections;
    }

    /**
     * Resolves site configuration properties.
     * Language context properties have precedence over global settings.
     */
    protected static function getConnectionPropertyOrFallback(
        CoreSite $typo3Site,
        string $property,
        int $languageId,
        string $scope,
    ): string|int|bool|null {
        if ($scope === 'write' && !self::isWriteConnectionEnabled($typo3Site, $languageId)) {
            $scope = 'read';
        }

        // convention key solr_$property_$scope
        $keyToCheck = 'solr_' . $property . '_' . $scope;

        // convention fallback key solr_$property_read
        $fallbackKey = 'solr_' . $property . '_read';

        // try to find language specific setting if found return it
        $rootPageUid = $typo3Site->getRootPageId();
        if (!isset(self::$languages[$rootPageUid][$languageId])) {
            self::$languages[$rootPageUid][$languageId] = $typo3Site->getLanguageById($languageId)->toArray();
        }
        $value = self::getValueOrFallback(self::$languages[$rootPageUid][$languageId], $keyToCheck, $fallbackKey);
        if ($value !== null) {
            return $value;
        }

        // if not found check global configuration
        $siteBaseConfiguration = $typo3Site->getConfiguration();
        return self::getValueOrFallback($siteBaseConfiguration, $keyToCheck, $fallbackKey);
    }

    /**
     * Checks whether write connection is enabled.
     * Language context properties have precedence over global settings.
     */
    protected static function isWriteConnectionEnabled(
        CoreSite $typo3Site,
        int $languageId,
    ): bool {
        $rootPageUid = $typo3Site->getRootPageId();
        if (!isset(self::$languages[$rootPageUid][$languageId])) {
            self::$languages[$rootPageUid][$languageId] = $typo3Site->getLanguageById($languageId)->toArray();
        }
        $value = self::getValueOrFallback(self::$languages[$rootPageUid][$languageId], 'solr_use_write_connection', 'solr_use_write_connection');
        if ($value !== null) {
            return $value;
        }

        $siteBaseConfiguration = $typo3Site->getConfiguration();
        $value = self::getValueOrFallback($siteBaseConfiguration, 'solr_use_write_connection', 'solr_use_write_connection');
        if ($value !== null) {
            return $value;
        }
        return false;
    }

    /**
     * Returns value of data by key or by fallback key if exists or null if not.
     */
    protected static function getValueOrFallback(
        array $data,
        string $keyToCheck,
        string $fallbackKey,
    ): string|int|bool|null {
        $value = $data[$keyToCheck] ?? null;
        if ($value === '0' || $value === 0 || !empty($value)) {
            return self::evaluateConfigurationData($value);
        }

        return self::evaluateConfigurationData($data[$fallbackKey] ?? null);
    }

    /**
     * Evaluate configuration data
     *
     * Setting boolean values via environment variables
     * results in strings like 'false' that may be misinterpreted
     * thus we check for boolean values in strings.
     */
    protected static function evaluateConfigurationData(string|bool|null $value): string|int|bool|null
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        return $value;
    }

    /**
     * Takes a page record and checks whether the page is marked as root page.
     */
    public static function isRootPage(array $pageRecord): bool
    {
        return ($pageRecord['is_siteroot'] ?? null) == 1;
    }

    /**
     * Retrieves the rootPageIds as an array from a set of sites.
     *
     * @param CoreSite[]|ExtSolrSite[] $sites
     * @return int[]
     */
    public static function getRootPageIdsFromSites(array $sites): array
    {
        $rootPageIds = [];
        foreach ($sites as $site) {
            $rootPageIds[] = $site->getRootPageId();
        }

        return $rootPageIds;
    }
}
