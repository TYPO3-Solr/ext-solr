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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Symfony\Component\EventDispatcher\GenericEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use ApacheSolrForTypo3\Solr\System\Mvc\Frontend\Controller\OverriddenTypoScriptFrontendController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
    public static function getPageDocumentId($uid, $typeNum = 0, $language = 0, $accessGroups = '0,-1', $mountPointParameter = '')
    {
        $additionalParameters = $typeNum . '/' . $language . '/' . $accessGroups;

        if ((string)$mountPointParameter !== '') {
            $additionalParameters = $mountPointParameter . '/' . $additionalParameters;
        }

        $documentId = self::getDocumentId('pages', $uid, $uid, $additionalParameters);

        return $documentId;
    }

    /**
     * Generates a document id in the form $siteHash/$type/$uid.
     *
     * @param string $table The records table name
     * @param int $rootPageId The record's site root id
     * @param int $uid The record's uid
     * @param string $additionalIdParameters Additional ID parameters
     * @return string A document id
     */
    public static function getDocumentId($table, $rootPageId, $uid, $additionalIdParameters = '')
    {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($rootPageId);
        $siteHash = $site->getSiteHash();

        $documentId = $siteHash . '/' . $table . '/' . $uid;
        if (!empty($additionalIdParameters)) {
            $documentId .= '/' . $additionalIdParameters;
        }

        return $documentId;
    }

    /**
     * Shortcut to retrieve the TypoScript configuration for EXT:solr
     * (plugin.tx_solr) from TSFE.
     *
     * @return TypoScriptConfiguration
     */
    public static function getSolrConfiguration()
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration();
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
    public static function getSolrConfigurationFromPageId($pageId, $initializeTsfe = false, $language = 0)
    {
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
    public static function getConfigurationFromPageId($pageId, $path, $initializeTsfe = false, $language = 0, $useTwoLevelCache = true)
    {
        $pageId = self::getConfigurationPageIdToUse($pageId);

        static $configurationObjectCache = [];
        $cacheId = md5($pageId . '|' . $path . '|' . $language . '|' . ($initializeTsfe ? '1' : '0'));
        if (isset($configurationObjectCache[$cacheId])) {
            if ($initializeTsfe) {
                self::initializeTsfe($pageId, $language);
            }
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
            $cache = GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'tx_solr_configuration');
            $configurationArray = $cache->get($cacheId);
        }

        if (!empty($configurationArray)) {
            // we have a cache hit and can return it.
            if ($initializeTsfe) {
                self::initializeTsfe($pageId, $language);
            }
            return $configurationObjectCache[$cacheId] = self::buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
        }

        // we have nothing in the cache. We need to build the configurationToUse
        $configurationArray = self::buildConfigurationArray($pageId, $path, $initializeTsfe, $language);

        if ($useTwoLevelCache && isset($cache)) {
            $cache->set($cacheId, $configurationArray);
        }

        return $configurationObjectCache[$cacheId] = self::buildTypoScriptConfigurationFromArray($configurationArray, $pageId, $language, $path);
    }

    /**
     * This method retrieves the closest pageId where a configuration is located, when this
     * feature is enabled.
     *
     * @param int $pageId
     * @return int
     */
    protected static function getConfigurationPageIdToUse($pageId)
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        if ($extensionConfiguration->getIsUseConfigurationFromClosestTemplateEnabled()) {
            /** @var $configurationPageResolve ConfigurationPageResolver */
            $configurationPageResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);
            $pageId = $configurationPageResolver->getClosestPageIdWithActiveTemplate($pageId);
            return $pageId;
        }
        return $pageId;
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
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
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
     *
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

        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId);
        try {
            $rootLine = $rootlineUtility->get();
        } catch (\RuntimeException $e) {
            $rootLine = [];
        }

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
     * @todo When we drop TYPO3 8 support we should use a middleware stack to initialize a TSFE for our needs
     * @return void
     */
    public static function initializeTsfe($pageId, $language = 0, $useCache = true)
    {
        static $tsfeCache = [];

        // resetting, a TSFE instance with data from a different page Id could be set already
        unset($GLOBALS['TSFE']);

        $cacheId = $pageId . '|' . $language;

        if (!is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = GeneralUtility::makeInstance(TimeTracker::class, false);
        }


        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $context->setAspect('language', GeneralUtility::makeInstance(LanguageAspect::class, $language));

        // needs to be set regardless if $GLOBALS['TSFE'] is loaded from cache
        // otherwise it is not guaranteed that the correct language id is used everywhere for this index cycle (e.g. Typo3QuerySettings)
        GeneralUtility::_GETset($language, 'L');

        if (!isset($tsfeCache[$cacheId]) || !$useCache) {

            $GLOBALS['TSFE'] = GeneralUtility::makeInstance(OverriddenTypoScriptFrontendController::class, $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0);

            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId, 'fe_group');
            $groupListBackup = $GLOBALS['TSFE']->gr_list;
            $GLOBALS['TSFE']->gr_list = $pageRecord['fe_group'];

            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
            self::getPageAndRootlineOfTSFE($pageId);

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

            // fixes wrong language uid in global context when tsfe is taken from cache
            $GLOBALS['TSFE']->__set('sys_language_uid', $language);


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
     * @deprecated This is only implemented to provide compatibility for TYPO3 8 and 9 when we drop TYPO3 8 support this
     * should changed to use a middleware stack
     * @param integer $pageId
     */
    private static function getPageAndRootlineOfTSFE($pageId)
    {
        //@todo When we drop the support of TYPO3 8 we should use the frontend middleware stack instead of initializing this on our own
        /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($pageId);
        if (!is_null($site)) {
            $GLOBALS['TSFE']->getPageAndRootlineWithDomain($site->getRootPageId());
        }
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
        $configurationName = $configurationName ?? 'pages';
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

    /**
     * Returns the current language ID from the active context.
     * @return int
     */
    public static function getLanguageUid(): int
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return (int)$context->getPropertyFromAspect('language', 'id');
    }

    /**
     * @return string
     */
    public static function getFrontendUserGroupsList(): string
    {
        return implode(',', self::getFrontendUserGroups());
    }

    /**
     * @return array
     */
    public static function getFrontendUserGroups(): array
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return $context->getPropertyFromAspect('frontend.user', 'groupIds');
    }
}
