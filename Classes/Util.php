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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequest;

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
            /** @var SiteRepository $siteRepository */
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
     * @param int $language System language uid, optional, defaults to 0
     * @deprecated
     *
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public static function getSolrConfigurationFromPageId($pageId, $initializeTsfe = false, $language = 0)
    {
        trigger_error('solr:deprecation: Method getSolrConfigurationFromPageId is deprecated since EXT:solr 11 and will be removed in v12, use FrontendEnvironment directly.', E_USER_DEPRECATED);
        if ($initializeTsfe === true) {
            GeneralUtility::makeInstance(FrontendEnvironment::class)->initializeTsfe($pageId, $language);
        }
        return GeneralUtility::makeInstance(FrontendEnvironment::class)->getSolrConfigurationFromPageId($pageId, $language);
    }

    /**
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId Id of the (root) page to get the Solr configuration from.
     * @param string $path The TypoScript configuration path to retrieve.
     * @param bool $initializeTsfe
     * @deprecated
     * @param int $language System language uid, optional, defaults to 0
     * @param bool $useTwoLevelCache Flag to enable the two level cache for the typoscript configuration array
     * @return TypoScriptConfiguration The Solr configuration for the requested tree.
     */
    public static function getConfigurationFromPageId($pageId, $path, $initializeTsfe = false, $language = 0, $useTwoLevelCache = true)
    {
        trigger_error('Method getConfigurationFromPageId is deprecated since EXT:solr 11 and will be removed in v12, use FrontendEnvironment directly.', E_USER_DEPRECATED);
        if ($initializeTsfe === true) {
            GeneralUtility::makeInstance(FrontendEnvironment::class)->initializeTsfe($pageId, $language);
        }
        return GeneralUtility::makeInstance(FrontendEnvironment::class)->getConfigurationFromPageId($pageId, $path, $language);
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param $pageId
     * @param int $language
     * @param bool $useCache
     * @deprecated
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Error\Http\ServiceUnavailableException
     * @throws \TYPO3\CMS\Core\Http\ImmediateResponseException
     */
    public static function initializeTsfe($pageId, $language = 0, $useCache = true)
    {
        trigger_error('solr:deprecation: Method initializeTsfe is deprecated since EXT:solr 11 and will be removed in v12, use FrontendEnvironment directly.', E_USER_DEPRECATED);
        GeneralUtility::makeInstance(FrontendEnvironment::class)->initializeTsfe($pageId, $language);
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
     * @deprecated
     *
     * @return bool TRUE if the page type is allowed, otherwise FALSE
     */
    public static function isAllowedPageType(array $pageRecord, $configurationName = 'pages')
    {
        trigger_error('solr:deprecation: Method isAllowedPageType is deprecated since EXT:solr 11 and will be removed in v12, use FrontendEnvironment directly.', E_USER_DEPRECATED);
        return GeneralUtility::makeInstance(FrontendEnvironment::class)->isAllowedPageType($pageRecord, $configurationName);
    }

    /**
     * Get allowed page types
     *
     * @param int $pageId Page ID
     * @param string $configurationName The name of the configuration to use.
     * @deprecated
     * @return array Allowed page types to compare to a doktype of a page record
     */
    public static function getAllowedPageTypes($pageId, $configurationName = 'pages')
    {
        trigger_error('solr:deprecation: Method getAllowedPageTypes is deprecated since EXT:solr 11 and will be removed in v12, no call required.', E_USER_DEPRECATED);
        $rootPath = '';
        $configuration = self::getConfigurationFromPageId($pageId, $rootPath);
        return $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
    }

    /**
     * Resolves the configured absRefPrefix to a valid value and resolved if absRefPrefix
     * is set to "auto".
     *
     * @param TypoScriptFrontendController $TSFE
     * @deprecated
     * @return string
     */
    public static function getAbsRefPrefixFromTSFE(TypoScriptFrontendController $TSFE)
    {
        trigger_error('solr:deprecation: Method getAbsRefPrefixFromTSFE is deprecated since EXT:solr 11 and will be removed in v12, no call required.', E_USER_DEPRECATED);
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

    /**
     * @todo This method is just added for compatibility checks for TYPO3 version 9 and will be removed when TYPO9 support is dropped
     * @return boolean
     */
    public static function getIsTYPO3VersionBelow10()
    {
        return GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 10;
    }

}
