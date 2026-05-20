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

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Utility class for tx_solr
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
     *
     * @return string The document id for that page
     *
     * @throws DBALException
     */
    public static function getPageDocumentId(
        int $uid,
        int $typeNum = 0,
        int $language = 0,
        string $accessGroups = '0,-1',
        string $mountPointParameter = '',
    ): string {
        $additionalParameters = $typeNum . '/' . $language . '/' . $accessGroups;

        if ($mountPointParameter !== '') {
            $additionalParameters = $mountPointParameter . '/' . $additionalParameters;
        }

        $rootPageResolver = GeneralUtility::makeInstance(RootPageResolver::class);
        $rootPageId = $rootPageResolver->getRootPageId($uid);

        return self::getDocumentId('pages', $rootPageId, $uid, $additionalParameters);
    }

    /**
     * Generates a document id in the form $siteHash/$type/$uid.
     *
     * @param string $table The record's table name
     * @param int $rootPageId The record's site root id
     * @param int $uid The record's uid
     * @param string $additionalIdParameters Additional ID parameters
     *
     * @return string A document id
     *
     * @throws DBALException
     */
    public static function getDocumentId(
        string $table,
        int $rootPageId,
        int $uid,
        string $additionalIdParameters = '',
    ): string {
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByRootPageId($rootPageId);
        $siteHash = $site->getSiteHash();

        $documentId = $siteHash . '/' . $table . '/' . $uid;
        if (!empty($additionalIdParameters)) {
            $documentId .= '/' . $additionalIdParameters;
        }

        return $documentId;
    }

    /**
     * Shortcut to retrieve the TypoScript configuration for EXT:solr
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
     *
     * @return bool TRUE if the record is in a draft workspace, FALSE if it's a LIVE record
     */
    public static function isDraftRecord(
        string $table,
        int $uid,
    ): bool {
        $isWorkspaceRecord = false;

        if ((ExtensionManagementUtility::isLoaded('workspaces')) && (BackendUtility::isTableWorkspaceEnabled($table))) {
            $record = BackendUtility::getRecord($table, $uid, 'pid, t3ver_state, t3ver_oid');

            // \TYPO3\CMS\Core\Versioning\VersionState for an explanation of the t3ver_state field
            // if it is >0, it is a draft record or
            // if it is "0" (DEFAULT_STATE), could also be draft if t3ver_oid points to any uid (modified record)
            if ($record !== null
                && ($record['pid'] == '-1' || $record['t3ver_state'] > 0 || (int)$record['t3ver_oid'] > 0)
            ) {
                $isWorkspaceRecord = true;
            }
        }

        return $isWorkspaceRecord;
    }

    public static function skipHooksForRecord(
        string $table,
        int $uid,
        ?int $pid
    ): bool {
        if (is_null($pid) && MathUtility::canBeInterpretedAsInteger($uid)) {
            $recordInfo = BackendUtility::getRecord($table, $uid, 'pid');
            if (!is_null($recordInfo)) {
                $pid = $recordInfo['pid'] ?? null;
            }
        }

        if (!is_null($pid)) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            try {
                $site = $siteFinder->getSiteByPageId($pid);
            } catch (SiteNotFoundException) {
                return false;
            }
            if ((bool)($site->getConfiguration()['solr_skip_hooks'] ?? false)) {
                return true;
            }
        }

        return false;
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
     */
    public static function containsOneOfTheStrings(
        string $haystack,
        array $needles,
    ): bool {
        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle);
            if ($position !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws AspectNotFoundException
     */
    public static function getFrontendUserGroups(): array
    {
        return self::getTYPO3CoreContext()->getPropertyFromAspect('frontend.user', 'groupIds');
    }

    /**
     * Returns the current execution time (formerly known as EXEC_TIME)
     *
     * @throws AspectNotFoundException
     */
    public static function getExecutionTime(): int
    {
        return (int)self::getTYPO3CoreContext()->getPropertyFromAspect('date', 'timestamp');
    }

    protected static function getTYPO3CoreContext(): Context
    {
        return GeneralUtility::makeInstance(Context::class);
    }
}
