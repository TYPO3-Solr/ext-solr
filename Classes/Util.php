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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\DateTime\FormatService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TimeTracker\NullTimeTracker;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Utility class for tx_solr
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Util
{

    /**
     * Generates a document id for documents representing page records.
     *
     * @param int $uid The page's uid
     * @param int $typeNum The page's typeNum
     * @param int $language the language id, defaults to 0
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
     * @param int $pid The record's pid
     * @param int $uid The record's uid
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
     * @param int $timestamp unix timestamp
     * @return string the date in ISO 8601 format
     * @deprecated since 6.1 will be removed in 7.0
     */
    public static function timestampToIso($timestamp)
    {
        GeneralUtility::logDeprecatedFunction();
        $formatService = GeneralUtility::makeInstance(FormatService::class);

        return $formatService->timestampToIso($timestamp);
    }

    /**
     * Converts a date from ISO 8601 format to unix timestamp.
     *
     * @param string $isoTime date in ISO 8601 format
     * @return int unix timestamp
     * @deprecated since 6.1 will be removed in 7.0
     */
    public static function isoToTimestamp($isoTime)
    {
        GeneralUtility::logDeprecatedFunction();
        $formatService = GeneralUtility::makeInstance(FormatService::class);

        return $formatService->isoToTimestamp($isoTime);
    }

    /**
     * Converts a date from unix timestamp to ISO 8601 format in UTC timezone.
     *
     * @param int $timestamp unix timestamp
     * @return string the date in ISO 8601 format
     * @deprecated since 6.1 will be removed in 7.0
     */
    public static function timestampToUtcIso($timestamp)
    {
        GeneralUtility::logDeprecatedFunction();
        $formatService = GeneralUtility::makeInstance(FormatService::class);

        return $formatService->timestampToUtcIso($timestamp);
    }

    /**
     * Converts a date from ISO 8601 format in UTC timezone to unix timestamp.
     *
     * @param string $isoTime date in ISO 8601 format
     * @return int unix timestamp
     * @deprecated since 6.1 will be removed in 7.0
     */
    public static function utcIsoToTimestamp($isoTime)
    {
        GeneralUtility::logDeprecatedFunction();
        $formatService = GeneralUtility::makeInstance(FormatService::class);

        return $formatService->utcIsoToTimestamp($isoTime);
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
        /** @var ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager;
    }

    /**
     * Gets the Solr configuration for a specific root page id.
     * To be used from the backend.
     *
     * @param int $pageId Id of the (root) page to get the Solr configuration from.
     * @param bool $initializeTsfe Optionally initializes a full TSFE to get the configuration, defaults to FALSE
     * @param int $language System language uid, optional, defaults to 0
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public static function getSolrConfigurationFromPageId(
        $pageId,
        $initializeTsfe = false,
        $language = 0
    ) {
        $rootPath = '';
        return self::getConfigurationFromPageId($pageId, $rootPath, $initializeTsfe, $language);
    }

    /**
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId Id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     * @param bool $initializeTsfe Optionally initializes a full TSFE to get the configuration, defaults to FALSE
     * @param int $language System language uid, optional, defaults to 0
     * @param bool $useTwoLevelCache Flag to enable the two level cache for the typoscript configuration array
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public static function getConfigurationFromPageId(
        $pageId,
        $path,
        $initializeTsfe = false,
        $language = 0,
        $useTwoLevelCache = true
    ) {
        static $configurationObjectCache = [];
        $cacheId = md5($pageId . '|' . $path . '|' . $language);
        if (isset($configurationObjectCache[$cacheId])) {
            return $configurationObjectCache[$cacheId];
        }

        // If we're on UID 0, we cannot retrieve a configuration currently.
        // getRootline() below throws an exception (since #typo3-60 )
        // as UID 0 cannot have any parent rootline by design.
        if ($pageId == 0) {
            return $configurationObjectCache[$cacheId] = self::buildTypoScriptConfigurationFromArray([], $pageId, $language, $path);
        }

        if ($useTwoLevelCache) {
            /** @var $cache TwoLevelCache */
            $cache = GeneralUtility::makeInstance(TwoLevelCache::class, 'tx_solr_configuration');
            $configurationArray = $cache->get($cacheId);
        }

        if (!empty($configurationArray)) {
            // we have a cache hit and can return it.
            return $configurationObjectCache[$cacheId] = self::buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
        }

        // Check if there is no TypoScript Template on this page since we might
        // be able to re-use the TypoScript config from the parent
        $parentPageId = self::shouldReuseParentTypoScriptConfiguration($pageId);
        if ($parentPageId !== $pageId) {
            $parentCacheId = md5($parentPageId . '|' . $path . '|' . $language);
            if (isset($configurationObjectCache[$parentCacheId])) {
                $configurationObjectCache[$cacheId] = $configurationObjectCache[$parentCacheId];
                return $configurationObjectCache[$cacheId];
            }
        }

        // We have nothing in the cache. We need to build the configurationToUse
        $configurationArray = self::buildConfigurationArray($pageId, $path, $initializeTsfe, $language);

        if ($useTwoLevelCache && isset($cache)) {
            $cache->set($cacheId, $configurationArray);
        }

        return $configurationObjectCache[$cacheId] = self::buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
    }

    /**
     * Send signal to check if we should re-use parent TS config
     *
     * @param int $pageId
     * @return int
     */
    protected static function shouldReuseParentTypoScriptConfiguration($pageId)
    {
        static $signalDispatched = false;
        static $shouldReuseParentTypoScriptConfiguration = false;

        if ($signalDispatched === false) {
            $signalDispatched = true;
            $signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);
            $signalSlotDispatcher->dispatch(__CLASS__, 'shouldReuseParentTypoScriptConfiguration',
                [&$shouldReuseParentTypoScriptConfiguration]);
        }

        if ($shouldReuseParentTypoScriptConfiguration === true) {
            $res = self::checkIfPageHasTypoScriptTemplate($pageId);
            if ((!empty($res)) && ($res['pid'] > 0) && ($res['hasTemplate'] == 0)) {
                return $res['pid'];
            }
        }

        return $pageId;
    }

    /**
     * Check if page has a TS template or not
     *
     * @param $pageId
     * @return array
     */
    protected static function checkIfPageHasTypoScriptTemplate($pageId)
    {
        $result = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
            'pid, (select count(*) from sys_template s where s.pid = p.uid and s.deleted = 0 and s.hidden = 0) as hasTemplate',
            'pages p',
            'uid = ' . $pageId . ' and hidden = 0 and deleted = 0'
        );

        return (array)$result;
    }

    /**
     * Initializes a TSFE, if required and builds an configuration array, containing the solr configuration.
     *
     * @param integer $pageId
     * @param string $path
     * @param boolean $initializeTsfe
     * @param integer $language
     * @return array
     */
    protected static function buildConfigurationArray($pageId, $path, $initializeTsfe, $language)
    {
        if ($initializeTsfe) {
            self::initializeTsfe($pageId, $language);
            $configurationToUse = self::getConfigurationFromInitializedTSFE($path);
        } else {
            $configurationToUse = self::getConfigurationFromExistingTSFE($pageId, $path, $language);
        }

        return is_array($configurationToUse) ? $configurationToUse : [];
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
        $configurationManager = self::getConfigurationManager();
        return $configurationManager->getTypoScriptConfiguration($configurationToUse, $pageId, $languageId, $typoScriptPath);
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
        /** @var $tmpl ExtendedTemplateService */
        $tmpl = GeneralUtility::makeInstance(ExtendedTemplateService::class);
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

            /** @var $pageSelect PageRepository */
        $pageSelect = GeneralUtility::makeInstance(PageRepository::class);
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
            /** @var $tmpl ExtendedTemplateService */
        $tmpl = GeneralUtility::makeInstance(ExtendedTemplateService::class);
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
     * @param int $pageId The page id to initialize the TSFE for
     * @param int $language System language uid, optional, defaults to 0
     * @param bool $useCache Use cache to reuse TSFE
     * @return void
     */
    public static function initializeTsfe(
        $pageId,
        $language = 0,
        $useCache = true
    ) {
        static $tsfeCache = [];

        // resetting, a TSFE instance with data from a different page Id could be set already
        unset($GLOBALS['TSFE']);

        $cacheId = $pageId . '|' . $language;

        if (!is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = GeneralUtility::makeInstance(NullTimeTracker::class);
        }

        if (!isset($tsfeCache[$cacheId]) || !$useCache) {
            GeneralUtility::_GETset($language, 'L');

            $GLOBALS['TSFE'] = GeneralUtility::makeInstance(TypoScriptFrontendController::class,
                $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0);

            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId, 'fe_group');
            $groupListBackup = $GLOBALS['TSFE']->gr_list;
            $GLOBALS['TSFE']->gr_list = $pageRecord['fe_group'];

            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
            $GLOBALS['TSFE']->getPageAndRootline();

            // restore gr_list
            $GLOBALS['TSFE']->gr_list = $groupListBackup;

            $GLOBALS['TSFE']->initTemplate();
            $GLOBALS['TSFE']->forceTemplateParsing = true;
            $GLOBALS['TSFE']->initFEuser();
            $GLOBALS['TSFE']->initUserGroups();
            //  $GLOBALS['TSFE']->getCompressedTCarray(); // seems to cause conflicts sometimes

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
     * @param int $pageId A page ID somewhere in a tree.
     * @param bool $forceFallback Force the explicit detection and do not use the current frontend root line
     * @return int The page's tree branch's root page ID
     * @deprecated since 6.1 will be removed in 7.0
     */
    public static function getRootPageId($pageId = 0, $forceFallback = false)
    {
        GeneralUtility::logDeprecatedFunction();
        $rootPageResolver = GeneralUtility::makeInstance(RootPageResolver::class);

        return $rootPageResolver->getRootPageId($pageId, $forceFallback);
    }

    /**
     * Takes a page Id and checks whether the page is marked as root page.
     *
     * @param int $pageId Page ID
     * @return bool TRUE if the page is marked as root page, FALSE otherwise
     * @deprecated since 6.1 will be removed in 7.0
     */
    public static function isRootPage($pageId)
    {
        GeneralUtility::logDeprecatedFunction();
        $rootPageResolver = GeneralUtility::makeInstance(RootPageResolver::class);

        return $rootPageResolver->getIsRootPageId($pageId);
    }

    /**
     * Gets the site hash for a domain
     *
     * @deprecated since 6.1 will be removed in 7.0. use SiteHashService->getSiteHashForDomain now.
     * @param string $domain Domain to calculate the site hash for.
     * @return string site hash for $domain
     */
    public static function getSiteHashForDomain($domain)
    {
        GeneralUtility::logDeprecatedFunction();
            /** @var $siteHashService SiteHashService */
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        return $siteHashService->getSiteHashForDomain($domain);
    }

    /**
     * Resolves magic keywords in allowed sites configuration.
     * Supported keywords:
     *   __solr_current_site - The domain of the site the query has been started from
     *   __current_site - Same as __solr_current_site
     *   __all - Adds all domains as allowed sites
     *   * - Means all sites are allowed, same as no siteHash
     *
     * @deprecated since 6.1 will be removed in 7.0. use SiteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration now.
     * @param int $pageId A page ID that is then resolved to the site it belongs to
     * @param string $allowedSitesConfiguration TypoScript setting for allowed sites
     * @return string List of allowed sites/domains, magic keywords resolved
     */
    public static function resolveSiteHashAllowedSites($pageId, $allowedSitesConfiguration)
    {
        /** @var $siteHashService SiteHashService */
        GeneralUtility::logDeprecatedFunction();
        $siteHashService = GeneralUtility::makeInstance(SiteHashService::class);
        return $siteHashService->getAllowedSitesForPageIdAndAllowedSitesConfiguration($pageId, $allowedSitesConfiguration);
    }

    /**
     * Check if record ($table, $uid) is a workspace record
     *
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @return bool TRUE if the record is in a draft workspace, FALSE if it's a LIVE record
     */
    public static function isDraftRecord($table, $uid)
    {
        $isWorkspaceRecord = false;

        if ((ExtensionManagementUtility::isLoaded('workspaces')) && (BackendUtility::isTableWorkspaceEnabled($table))) {
            $record = BackendUtility::getRecord($table, $uid, 'pid, t3ver_state');

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
     * @return bool TRUE if the record is a language overlay, FALSE otherwise
     */
    public static function isLocalizedRecord($tableName, array $record)
    {
        $isLocalizedRecord = false;

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
     * @param string $configurationName The name of the configuration to use.
     *
     * @return bool TRUE if the page type is allowed, otherwise FALSE
     */
    public static function isAllowedPageType(array $pageRecord, $configurationName = 'pages')
    {
        $isAllowedPageType = false;
        $configurationName = is_null($configurationName) ? 'pages' : $configurationName;
        $allowedPageTypes = self::getAllowedPageTypes($pageRecord['uid'], $configurationName);

        if (in_array($pageRecord['doktype'], $allowedPageTypes)) {
            $isAllowedPageType = true;
        }

        return $isAllowedPageType;
    }

    /**
     * Get allowed page types
     *
     * @param int $pageId Page ID
     * @param string $configurationName The name of the configuration to use.
     *
     * @return array Allowed page types to compare to a doktype of a page record
     */
    public static function getAllowedPageTypes($pageId, $configurationName = 'pages')
    {
        $rootPath = '';
        $configuration = self::getConfigurationFromPageId($pageId, $rootPath);
        return $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
    }

    /**
     * Method to check if a page exists.
     *
     * @param int $pageId
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
