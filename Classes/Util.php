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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class for tx_solr
 *
 * @author Ingo Renner <ingo@typo3.org>
 * (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
     * @throws DBALDriverException
     */
    public static function getPageDocumentId(
        int $uid,
        int $typeNum = 0,
        int $language = 0,
        string $accessGroups = '0,-1',
        string $mountPointParameter = ''
    ): string {
        $additionalParameters = $typeNum . '/' . $language . '/' . $accessGroups;

        if ($mountPointParameter !== '') {
            $additionalParameters = $mountPointParameter . '/' . $additionalParameters;
        }

        return self::getDocumentId('pages', $uid, $uid, $additionalParameters);
    }

    /**
     * Generates a document id in the form $siteHash/$type/$uid.
     *
     * @param string $table The record's table name
     * @param int $rootPageId The record's site root id
     * @param int $uid The record's uid
     * @param string $additionalIdParameters Additional ID parameters
     * @return string A document id
     * @throws DBALDriverException
     */
    public static function getDocumentId(
        string $table,
        int $rootPageId,
        int $uid,
        string $additionalIdParameters = ''
    ): string {
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
    public static function getSolrConfiguration(): TypoScriptConfiguration
    {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration();
    }

    /**
     * Check if record ($table, $uid) is a workspace record
     *
     * @param string $table The table the record belongs to
     * @param int $uid The record's uid
     * @return bool TRUE if the record is in a draft workspace, FALSE if it's a LIVE record
     */
    public static function isDraftRecord(string $table, int $uid): bool
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
    public static function containsOneOfTheStrings(string $haystack, array $needles): bool
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
     * @throws AspectNotFoundException
     * @todo: Remove all usages of this method for all usages in isolated/capsuled TSFE approach.
     */
    public static function getLanguageUid(): int
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return (int)$context->getPropertyFromAspect('language', 'id');
    }

    /**
     * @return string
     * @throws AspectNotFoundException
     */
    public static function getFrontendUserGroupsList(): string
    {
        return implode(',', self::getFrontendUserGroups());
    }

    /**
     * @return array
     * @throws AspectNotFoundException
     */
    public static function getFrontendUserGroups(): array
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return $context->getPropertyFromAspect('frontend.user', 'groupIds');
    }

    /**
     * Returns the current execution time (formerly known as EXEC_TIME)
     * @return int
     * @throws AspectNotFoundException
     */
    public static function getExceptionTime(): int
    {
        $context = GeneralUtility::makeInstance(Context::class);
        return (int)$context->getPropertyFromAspect('date', 'timestamp');
    }
}
