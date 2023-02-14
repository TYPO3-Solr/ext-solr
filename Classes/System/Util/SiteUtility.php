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

use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains related functions for the new site management that was introduced with TYPO3 9.
 */
class SiteUtility
{
    /**
     * @var array
     */
    public static array $languages = [];

    /**
     * Determines if the site where the page belongs to is managed with the TYPO3 site management.
     *
     * @param int $pageId
     * @return bool
     */
    public static function getIsSiteManagedSite(int $pageId): bool
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            /* @var SiteFinder $siteFinder */
            return $siteFinder->getSiteByPageId($pageId) instanceof Site;
        } catch (SiteNotFoundException $e) {
        }
        return false;
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
     *
     * @param Site $typo3Site
     * @param string $property
     * @param int $languageId
     * @param string $scope
     * @param mixed $defaultValue
     * @return mixed
     */
    public static function getConnectionProperty(
        Site $typo3Site,
        string $property,
        int $languageId,
        string $scope,
        $defaultValue = null
    ) {
        $value = self::getConnectionPropertyOrFallback($typo3Site, $property, $languageId, $scope);
        if ($value === null) {
            return $defaultValue;
        }
        return $value;
    }

    /**
     * Builds the Solr connection configuration
     *
     * @param Site $typo3Site
     * @param int $languageUid
     * @return array|null
     */
    public static function getSolrConnectionConfiguration(Site $typo3Site, int $languageUid): ?array
    {
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
                // @todo: transform core to path
                'path' =>
                self::getConnectionProperty($typo3Site, 'path', $languageUid, 'read', '/solr/') .
                $solrReadCore . '/' ,
                'username' => self::getConnectionProperty($typo3Site, 'username', $languageUid, 'read', ''),
                'password' => self::getConnectionProperty($typo3Site, 'password', $languageUid, 'read', ''),
            ],
            'write' => [
                'scheme' => self::getConnectionProperty($typo3Site, 'scheme', $languageUid, 'write', 'http'),
                'host' => self::getConnectionProperty($typo3Site, 'host', $languageUid, 'write', 'localhost'),
                'port' => (int)self::getConnectionProperty($typo3Site, 'port', $languageUid, 'write', 8983),
                // @todo: transform core to path
                'path' =>
                self::getConnectionProperty($typo3Site, 'path', $languageUid, 'write', '/solr/') .
                $solrWriteCore . '/' ,
                'username' => self::getConnectionProperty($typo3Site, 'username', $languageUid, 'write', ''),
                'password' => self::getConnectionProperty($typo3Site, 'password', $languageUid, 'write', ''),
            ],

            'language' => $languageUid,
        ];
    }

    /**
     * Builds the Solr connection configuration for all languages of given TYPO3 site
     *
     * @param Site $typo3Site
     * @return array
     */
    public static function getAllSolrConnectionConfigurations(Site $typo3Site): array
    {
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
     *
     * @param Site $typo3Site
     * @param string $property
     * @param int $languageId
     * @param string $scope
     * @return string|bool|null
     */
    protected static function getConnectionPropertyOrFallback(
        Site $typo3Site,
        string $property,
        int $languageId,
        string $scope
    ) {
        if ($scope === 'write' && !self::writeConnectionIsEnabled($typo3Site, $languageId)) {
            $scope = 'read';
        }

        // convention key solr_$property_$scope
        $keyToCheck = 'solr_' . $property . '_' . $scope;

        // convention fallback key solr_$property_read
        $fallbackKey = 'solr_' . $property . '_read';

        // try to find language specific setting if found return it
        $rootPageUid = $typo3Site->getRootPageId();
        if (isset(self::$languages[$rootPageUid][$languageId]) === false) {
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
     *
     * @param Site $typo3Site
     * @param int $languageId
     * @return bool
     */
    protected static function writeConnectionIsEnabled(Site $typo3Site, int $languageId): bool
    {
        $rootPageUid = $typo3Site->getRootPageId();
        if (isset(self::$languages[$rootPageUid][$languageId]) === false) {
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
     * @param array $data
     * @param string $keyToCheck
     * @param string $fallbackKey
     * @return string|bool|null
     */
    protected static function getValueOrFallback(array $data, string $keyToCheck, string $fallbackKey)
    {
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
     *
     * @param string|bool|null $value
     * @return string|bool|null
     */
    protected static function evaluateConfigurationData($value)
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        return $value;
    }
}
