<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use InvalidArgumentException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Utility class for tx_solr
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Util
{
    const SOLR_ISO_DATETIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Generates a document id for documents representing page records.
     *
     * @param integer $uid The page's uid
     * @param integer $typeNum The page's typeNum
     * @param integer $language the language id, defaults to 0
     * @param string $accessGroups comma separated list of uids of groups that have access to that page
     * @param string $mountPointParameter The mount point parameter that is used to access the page.
     * @return string The document id for that page
     */
    public static function getPageDocumentId(
        $uid,
        $typeNum = 0,
        $language = 0,
        $accessGroups = '0,-1',
        $mountPointParameter = ''
    ) {
        $additionalParameters = $typeNum . '/' . $language . '/' . $accessGroups;

        if ((string)$mountPointParameter !== '') {
            $additionalParameters = $mountPointParameter . '/' . $additionalParameters;
        }

        $documentId = self::getDocumentId(
            'pages',
            $uid,
            $uid,
            $additionalParameters
        );

        return $documentId;
    }

    /**
     * Generates a document id in the form $siteHash/$type/$uid.
     *
     * @param string $table The records table name
     * @param integer $pid The record's pid
     * @param integer $uid The record's uid
     * @param string $additionalIdParameters Additional ID parameters
     * @return string A document id
     */
    public static function getDocumentId(
        $table,
        $pid,
        $uid,
        $additionalIdParameters = ''
    ) {
        $siteHash = Site::getSiteByPageId($pid)->getSiteHash();

        $documentId = $siteHash . '/' . $table . '/' . $uid;
        if (!empty($additionalIdParameters)) {
            $documentId .= '/' . $additionalIdParameters;
        }

        return $documentId;
    }

    /**
     * Converts a date from unix timestamp to ISO 8601 format.
     *
     * @param integer $timestamp unix timestamp
     * @return string the date in ISO 8601 format
     */
    public static function timestampToIso($timestamp)
    {
        return date(self::SOLR_ISO_DATETIME_FORMAT, $timestamp);
    }

    /**
     * Converts a date from ISO 8601 format to unix timestamp.
     *
     * @param string $isoTime date in ISO 8601 format
     * @return integer unix timestamp
     */
    public static function isoToTimestamp($isoTime)
    {
        $dateTime = \DateTime::createFromFormat(self::SOLR_ISO_DATETIME_FORMAT,
            $isoTime);
        return $dateTime ? (int)$dateTime->format('U') : 0;
    }

    /**
     * Converts a date from unix timestamp to ISO 8601 format in UTC timezone.
     *
     * @param integer $timestamp unix timestamp
     * @return string the date in ISO 8601 format
     */
    public static function timestampToUtcIso($timestamp)
    {
        return gmdate(self::SOLR_ISO_DATETIME_FORMAT, $timestamp);
    }

    /**
     * Converts a date from ISO 8601 format in UTC timezone to unix timestamp.
     *
     * @param string $isoTime date in ISO 8601 format
     * @return integer unix timestamp
     */
    public static function utcIsoToTimestamp($isoTime)
    {
        $utcTimeZone = new \DateTimeZone('UTC');
        $dateTime = \DateTime::createFromFormat(self::SOLR_ISO_DATETIME_FORMAT,
            $isoTime, $utcTimeZone);
        return $dateTime ? (int)$dateTime->format('U') : 0;
    }

    /**
     * Returns given word as CamelCased.
     *
     * Converts a word like "send_email" to "SendEmail". It
     * will remove non alphanumeric characters from the word, so
     * "who's online" will be converted to "WhoSOnline"
     *
     * @param string $word Word to convert to camel case
     * @return string UpperCamelCasedWord
     */
    public static function camelize($word)
    {
        return str_replace(' ', '',
            ucwords(preg_replace('![^A-Z^a-z^0-9]+!', ' ', $word)));
    }

    /**
     * Returns a given CamelCasedString as an lowercase string with underscores.
     * Example: Converts BlogExample to blog_example, and minimalValue to minimal_value
     *
     * @param string $string String to be converted to lowercase underscore
     * @return string     lowercase_and_underscored_string
     */
    public static function camelCaseToLowerCaseUnderscored($string)
    {
        return strtolower(preg_replace('/(?<=\w)([A-Z])/', '_\\1', $string));
    }

    /**
     * Returns a given string with underscores as UpperCamelCase.
     * Example: Converts blog_example to BlogExample
     *
     * @param string $string String to be converted to camel case
     * @return string     UpperCamelCasedWord
     */
    public static function underscoredToUpperCamelCase($string)
    {
        return str_replace(' ', '',
            ucwords(str_replace('_', ' ', strtolower($string))));
    }

    /**
     * Shortcut to retrieve the TypoScript configuration for EXT:solr
     * (plugin.tx_solr) from TSFE.
     *
     * @return TypoScriptConfiguration
     */
    public static function getSolrConfiguration()
    {
        $configurationManager = self::getConfigurationManager();

        return $configurationManager->getTypoScriptConfiguration();
    }

    /**
     * @return ConfigurationManager
     */
    private static function getConfigurationManager()
    {
        /** @var \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\ConfigurationManager');
        return $configurationManager;
    }

    /**
     * Gets the Solr configuration for a specific root page id.
     * To be used from the backend.
     *
     * @param integer $pageId Id of the (root) page to get the Solr configuration from.
     * @param boolean $initializeTsfe Optionally initializes a full TSFE to get the configuration, defaults to FALSE
     * @param integer $language System language uid, optional, defaults to 0
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public static function getSolrConfigurationFromPageId(
        $pageId,
        $initializeTsfe = false,
        $language = 0
    ) {
        return self::getConfigurationFromPageId($pageId, 'plugin.tx_solr', $initializeTsfe, $language);
    }

    /**
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param integer $pageId Id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     * @param boolean $initializeTsfe Optionally initializes a full TSFE to get the configuration, defaults to FALSE
     * @param integer|boolean $language System language uid or FALSE to disable language usage, optional, defaults to 0
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public static function getConfigurationFromPageId(
        $pageId,
        $path,
        $initializeTsfe = false,
        $language = 0,
        $useCache = true
    ) {
        // If we're on UID 0, we cannot retrieve a configuration currently.
        // getRootline() below throws an exception (since #typo3-60 )
        // as UID 0 cannot have any parent rootline by design.
        if ($pageId == 0) {
            return self::buildTypoScriptConfigurationFromArray(array(), $pageId, $language, $path);
        }

        if ($useCache) {
            $cacheId = md5($pageId . '|' . $path . '|' . $language);
            $cache = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache', 'tx_solr_configuration');
            $configurationToUse = $cache->get($cacheId);
        }

        if ($initializeTsfe) {
            self::initializeTsfe($pageId, $language);
            if (!empty($configurationToUse)) {
                return self::buildTypoScriptConfigurationFromArray($configurationToUse, $pageId, $language, $path);
            }
            $configurationToUse = self::getConfigurationFromInitializedTSFE($path);
        } else {
            if (!empty($configurationToUse)) {
                return self::buildTypoScriptConfigurationFromArray($configurationToUse, $pageId, $language, $path);
            }
            $configurationToUse = self::getConfigurationFromExistingTSFE($pageId, $path, $language);
        }

        if ($useCache) {
            $cache->set($cacheId, $configurationToUse);
        }

        return self::buildTypoScriptConfigurationFromArray($configurationToUse, $pageId, $language, $path);
    }

    /**
     * Builds the configuration object from a config array and returns it.
     *
     * @param array $configurationToUse
     * @param int $pageId
     * @param int $languageId
     * @param string $typoScriptPath
     * @return TypoScriptConfiguration
     */
    protected static function buildTypoScriptConfigurationFromArray(array $configurationToUse, $pageId, $languageId, $typoScriptPath)
    {
        $configurationArray = array();
        $configurationArray['plugin.']['tx_solr.'] = $configurationToUse;
        $configurationManager = self::getConfigurationManager();

        return $configurationManager->getTypoScriptConfiguration($configurationArray, $pageId, $languageId, $typoScriptPath);
    }

    /**
     * This function is used to retrieve the configuration from a previous initialized TSFE
     * (see: getConfigurationFromPageId)
     *
     * @param string $path
     * @return mixed
     */
    private static function getConfigurationFromInitializedTSFE($path)
    {
        $tmpl = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\TypoScript\\ExtendedTemplateService');
        $configuration = $tmpl->ext_getSetup($GLOBALS['TSFE']->tmpl->setup, $path);
        $configurationToUse = $configuration[0];
        return $configurationToUse;
    }

    /**
     * This function is used to retrieve the configuration from an existing TSFE instance
     * @param $pageId
     * @param $path
     * @param $language
     * @return mixed
     */
    private static function getConfigurationFromExistingTSFE($pageId, $path, $language)
    {
        if (is_int($language)) {
            GeneralUtility::_GETset($language, 'L');
        }

        $pageSelect = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
        $rootLine = $pageSelect->getRootLine($pageId);

        $initializedTsfe = false;
        $initializedPageSelect = false;
        if (empty($GLOBALS['TSFE']->sys_page)) {
            if (empty($GLOBALS['TSFE'])) {
                $GLOBALS['TSFE'] = new \stdClass();
                $GLOBALS['TSFE']->tmpl = new \stdClass();
                $GLOBALS['TSFE']->tmpl->rootLine = $rootLine;
                $GLOBALS['TSFE']->sys_page = $pageSelect;
                $GLOBALS['TSFE']->id = $pageId;
                $GLOBALS['TSFE']->tx_solr_initTsfe = 1;
                $initializedTsfe = true;
            }

            $GLOBALS['TSFE']->sys_page = $pageSelect;
            $initializedPageSelect = true;
        }

        $tmpl = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\TypoScript\\ExtendedTemplateService');
        $tmpl->tt_track = false; // Do not log time-performance information
        $tmpl->init();
        $tmpl->runThroughTemplates($rootLine); // This generates the constants/config + hierarchy info for the template.
        $tmpl->generateConfig();

        $getConfigurationFromInitializedTSFEAndWriteToCache = $tmpl->ext_getSetup($tmpl->setup, $path);
        $configurationToUse = $getConfigurationFromInitializedTSFEAndWriteToCache[0];

        if ($initializedPageSelect) {
            $GLOBALS['TSFE']->sys_page = null;
        }
        if ($initializedTsfe) {
            unset($GLOBALS['TSFE']);
        }
        return $configurationToUse;
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param integer $pageId The page id to initialize the TSFE for
     * @param integer $language System language uid, optional, defaults to 0
     * @param boolean $useCache Use cache to reuse TSFE
     * @return void
     */
    public static function initializeTsfe(
        $pageId,
        $language = 0,
        $useCache = true
    ) {
        static $tsfeCache = array();

        // resetting, a TSFE instance with data from a different page Id could be set already
        unset($GLOBALS['TSFE']);

        $cacheId = $pageId . '|' . $language;

        if (!is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\TimeTracker\\NullTimeTracker');
        }

        if (!isset($tsfeCache[$cacheId]) || !$useCache) {
            GeneralUtility::_GETset($language, 'L');

            $GLOBALS['TSFE'] = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController',
                $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0);

            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId);
            $groupListBackup = $GLOBALS['TSFE']->gr_list;
            $GLOBALS['TSFE']->gr_list = $pageRecord['fe_group'];

            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
            $GLOBALS['TSFE']->getPageAndRootline();

            // restore gr_list
            $GLOBALS['TSFE']->gr_list = $groupListBackup;

            $GLOBALS['TSFE']->initTemplate();
            $GLOBALS['TSFE']->forceTemplateParsing = true;
            $GLOBALS['TSFE']->initFEuser();
            $GLOBALS['TSFE']->initUserGroups();
            // $GLOBALS['TSFE']->getCompressedTCarray(); // seems to cause conflicts sometimes

            $GLOBALS['TSFE']->no_cache = true;
            $GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
            $GLOBALS['TSFE']->no_cache = false;
            $GLOBALS['TSFE']->getConfigArray();

            $GLOBALS['TSFE']->settingLanguage();
            if (!$useCache) {
                $GLOBALS['TSFE']->settingLocale();
            }

            $GLOBALS['TSFE']->newCObj();
            $GLOBALS['TSFE']->absRefPrefix = self::getAbsRefPrefixFromTSFE($GLOBALS['TSFE']);
            $GLOBALS['TSFE']->calculateLinkVars();

            if ($useCache) {
                $tsfeCache[$cacheId] = $GLOBALS['TSFE'];
            }
        }

        if ($useCache) {
            $GLOBALS['TSFE'] = $tsfeCache[$cacheId];
            $GLOBALS['TSFE']->settingLocale();
        }
    }

    /**
     * Determines the rootpage ID for a given page.
     *
     * @param integer $pageId A page ID somewhere in a tree.
     * @param bool $forceFallback Force the explicit detection and do not use the current frontend root line
     * @return integer The page's tree branch's root page ID
     */
    public static function getRootPageId($pageId = 0, $forceFallback = false)
    {
        $rootLine = array();
        $rootPageId = intval($pageId) ? intval($pageId) : $GLOBALS['TSFE']->id;

        // frontend
        if (!empty($GLOBALS['TSFE']->rootLine)) {
            $rootLine = $GLOBALS['TSFE']->rootLine;
        }

        // fallback, backend
        if ($pageId != 0 && ($forceFallback || empty($rootLine) || !self::rootlineContainsRootPage($rootLine))) {
            $pageSelect = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
            $rootLine = $pageSelect->getRootLine($pageId, '', true);
        }

        $rootLine = array_reverse($rootLine);
        foreach ($rootLine as $page) {
            if ($page['is_siteroot']) {
                $rootPageId = $page['uid'];
            }
        }

        return $rootPageId;
    }

    /**
     * Checks whether a given root line contains a page marked as root page.
     *
     * @param array $rootLine A root line array of page records
     * @return boolean TRUE if the root line contains a root page record, FALSE otherwise
     */
    protected static function rootlineContainsRootPage(array $rootLine)
    {
        $containsRootPage = false;

        foreach ($rootLine as $page) {
            if ($page['is_siteroot']) {
                $containsRootPage = true;
                break;
            }
        }

        return $containsRootPage;
    }

    /**
     * Takes a page Id and checks whether the page is marked as root page.
     *
     * @param integer $pageId Page ID
     * @return boolean TRUE if the page is marked as root page, FALSE otherwise
     */
    public static function isRootPage($pageId)
    {
        $isRootPage = false;

        $page = BackendUtility::getRecord('pages', $pageId);
        if ($page['is_siteroot']) {
            $isRootPage = true;
        }

        return $isRootPage;
    }

    /**
     * Gets the parent TypoScript Object from a given TypoScript path.
     *
     * Example: plugin.tx_solr.index.queue.tt_news.fields.content
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['index.']['queue.']['tt_news.']['fields.']['content.']
     * which is a SOLR_CONTENT cObj.
     *
     * @param string $path TypoScript path
     * @return array The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::getObjectByPath() instead, will be removed in version 5.0
     */
    public static function getTypoScriptObject($path)
    {
        GeneralUtility::logDeprecatedFunction();
        return self::getConfigurationManager()->getTypoScriptConfiguration()->getObjectByPath($path);
    }

    /**
     * Checks whether a given TypoScript path is valid.
     *
     * @param string $path TypoScript path
     * @return boolean TRUE if the path resolves, FALSE otherwise
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::isValidPath() instead, will be removed in version 5.0
     */
    public static function isValidTypoScriptPath($path)
    {
        GeneralUtility::logDeprecatedFunction();
        return self::getConfigurationManager()->getTypoScriptConfiguration()->isValidPath($path);
    }

    /**
     * Gets the value from a given TypoScript path.
     *
     * Example: plugin.tx_solr.search.targetPage
     * returns $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['search.']['targetPage']
     *
     * @param string $path TypoScript path
     * @return array The TypoScript object defined by the given path
     * @throws InvalidArgumentException
     *
     * @deprecated since 4.0, use TypoScriptConfiguration::getValueByPath() instead, will be removed in version 5.0
     */
    public static function getTypoScriptValue($path)
    {
        GeneralUtility::logDeprecatedFunction();
        return self::getConfigurationManager()->getTypoScriptConfiguration()->getValueByPath($path);
    }

    /**
     * Gets the site hash for a domain
     *
     * @param string $domain Domain to calculate the site hash for.
     * @return string site hash for $domain
     */
    public static function getSiteHashForDomain($domain)
    {
        $siteHash = sha1(
            $domain .
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] .
            'tx_solr'
        );

        return $siteHash;
    }

    /**
     * Resolves magic keywords in allowed sites configuration.
     * Supported keywords:
     *   __solr_current_site - The domain of the site the query has been started from
     *   __current_site - Same as __solr_current_site
     *   __all - Adds all domains as allowed sites
     *   * - Same as __all
     *
     * @param integer $pageId A page ID that is then resolved to the site it belongs to
     * @param string $allowedSitesConfiguration TypoScript setting for allowed sites
     * @return string List of allowed sites/domains, magic keywords resolved
     */
    public static function resolveSiteHashAllowedSites(
        $pageId,
        $allowedSitesConfiguration
    ) {
        if ($allowedSitesConfiguration == '*' || $allowedSitesConfiguration == '__all') {
            $sites = Site::getAvailableSites();
            $domains = array();
            foreach ($sites as $site) {
                $domains[] = $site->getDomain();
            }

            $allowedSites = implode(',', $domains);
        } else {
            $allowedSites = str_replace(
                array('__solr_current_site', '__current_site'),
                Site::getSiteByPageId($pageId)->getDomain(),
                $allowedSitesConfiguration
            );
        }

        return $allowedSites;
    }

    /**
     * Check if record ($table, $uid) is a workspace record
     *
     * @param string $table The table the record belongs to
     * @param integer $uid The record's uid
     * @return boolean TRUE if the record is in a draft workspace, FALSE if it's a LIVE record
     */
    public static function isDraftRecord($table, $uid)
    {
        $isWorkspaceRecord = false;

        if (BackendUtility::isTableWorkspaceEnabled($table)) {
            $record = BackendUtility::getRecord($table, $uid);

            if ($record['pid'] == '-1' || $record['t3ver_state'] > 0) {
                $isWorkspaceRecord = true;
            }
        }

        return $isWorkspaceRecord;
    }

    /**
     * Checks whether a record is a localization overlay.
     *
     * @param string $tableName The record's table name
     * @param array $record The record to check
     * @return boolean TRUE if the record is a language overlay, FALSE otherwise
     */
    public static function isLocalizedRecord($tableName, array $record)
    {
        $isLocalizedRecord = false;
        $translationOriginalPointerField = '';

        if (isset($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'])) {
            $translationOriginalPointerField = $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'];

            if ($record[$translationOriginalPointerField] > 0) {
                $isLocalizedRecord = true;
            }
        }

        return $isLocalizedRecord;
    }

    /**
     * Check if the page type of a page record is allowed
     *
     * @param array $pageRecord The pages database row
     *
     * @return boolean TRUE if the page type is allowed, otherwise FALSE
     */
    public static function isAllowedPageType(array $pageRecord)
    {
        $isAllowedPageType = false;
        $allowedPageTypes = self::getAllowedPageTypes($pageRecord['uid']);

        if (in_array($pageRecord['doktype'], $allowedPageTypes)) {
            $isAllowedPageType = true;
        }

        return $isAllowedPageType;
    }

    /**
     * Get allowed page types
     *
     * @param integer $pageId Page ID
     *
     * @return array Allowed page types to compare to a doktype of a page record
     */
    public static function getAllowedPageTypes($pageId)
    {
        $configuration = self::getConfigurationFromPageId($pageId, 'plugin.tx_solr');
        return $configuration->getIndexQueuePagesAllowedPageTypesArray();
    }

    /**
     * Method to check if a page exists.
     *
     * @param integer $pageId
     * @return bool
     */
    public static function pageExists($pageId)
    {
        $page = BackendUtility::getRecord('pages', (int)$pageId, 'uid');

        if (!is_array($page) || $page['uid'] != $pageId) {
            return false;
        }

        return true;
    }

    /**
     * Resolves the configured absRefPrefix to a valid value and resolved if absRefPrefix
     * is set to "auto".
     *
     * @param TypoScriptFrontendController $TSFE
     * @return string
     */
    public static function getAbsRefPrefixFromTSFE(TypoScriptFrontendController $TSFE)
    {
        $absRefPrefix = '';
        if (empty($TSFE->config['config']['absRefPrefix'])) {
            return $absRefPrefix;
        }

        $absRefPrefix = trim($TSFE->config['config']['absRefPrefix']);
        if ($absRefPrefix === 'auto') {
            $absRefPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
        }

        return $absRefPrefix;
    }

    /**
     * This function can be used to check if one of the strings in needles is
     * contained in the haystack.
     *
     *
     * Example:
     *
     * haystack: the brown fox
     * needles: ['hello', 'world']
     * result: false
     *
     * haystack: the brown fox
     * needles: ['is', 'fox']
     * result: true
     *
     * @param string $haystack
     * @param array $needles
     * @return bool
     */
    public static function containsOneOfTheStrings($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle);
            if ($position !== false) {
                return true;
            }
        }

        return false;
    }
}
