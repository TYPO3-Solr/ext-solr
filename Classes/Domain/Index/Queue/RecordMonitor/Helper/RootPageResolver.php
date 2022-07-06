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

namespace ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper;

use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Configuration\ExtensionConfiguration;
use ApacheSolrForTypo3\Solr\System\Page\Rootline;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * RootPageResolver.
 *
 * Responsibility: The RootPageResolver is responsible to determine all relevant site root page id's
 * for a certain records, by table and uid.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class RootPageResolver implements SingletonInterface
{
    /**
     * @var ConfigurationAwareRecordService
     */
    protected $recordService;

    /**
     * @var TwoLevelCache
     */
    protected $runtimeCache;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * RootPageResolver constructor.
     * @param ConfigurationAwareRecordService|null $recordService
     * @param TwoLevelCache|null $twoLevelCache
     */
    public function __construct(
        ConfigurationAwareRecordService $recordService = null,
        TwoLevelCache $twoLevelCache = null
    ) {
        $this->recordService = $recordService ?? GeneralUtility::makeInstance(ConfigurationAwareRecordService::class);
        $this->runtimeCache = $twoLevelCache ?? GeneralUtility::makeInstance(TwoLevelCache::class, /** @scrutinizer ignore-type */ 'runtime');
        $this->extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
    }

    /**
     * This method determines the responsible site roots for a record by getting the rootPage of the record and checking
     * if the pid is references in another site with additionalPageIds and returning those rootPageIds as well.
     * The result is cached by the caching framework.
     *
     * @param string $table
     * @param int $uid
     * @return array
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getResponsibleRootPageIds(string $table, int $uid): array
    {
        $cacheId = 'RootPageResolver' . '_' . 'getResponsibleRootPageIds' . '_' . $table . '_' . $uid;
        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = $this->buildResponsibleRootPageIds($table, $uid);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * Checks if the passed pageId is a root page.
     *
     * @param int $pageId Page ID
     * @return bool TRUE if the page is marked as root page, FALSE otherwise
     */
    public function getIsRootPageId(int $pageId): bool
    {
        // Page 0 can never be a root page
        if ($pageId === 0) {
            return false;
        }

        // Page -1 is a workspace thing
        if ($pageId === -1) {
            return false;
        }

        $cacheId = 'RootPageResolver' . '_' . 'getIsRootPageId' . '_' . $pageId;
        $isSiteRoot = $this->runtimeCache->get($cacheId);

        if (!empty($isSiteRoot)) {
            return $isSiteRoot;
        }

        $page = $this->getPageRecordByPageId($pageId);
        if (empty($page)) { // @todo: 1636120156 See \ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\FrontendHelper\PageIndexerTest::phpProcessDoesNotDieIfPageIsNotAvailable()
            throw new InvalidArgumentException(
                'The page for the given page ID \'' . $pageId
                . '\' could not be found in the database and can therefore not be used as site root page.',
                1487171426
            );
        }

        $isSiteRoot = Site::isRootPage($page);
        $this->runtimeCache->set($cacheId, $isSiteRoot);

        return $isSiteRoot;
    }

    /**
     * @param int $pageId
     * @param string $fieldList
     * @return array
     */
    protected function getPageRecordByPageId(int $pageId, string $fieldList = 'is_siteroot'): array
    {
        return BackendUtility::getRecord('pages', $pageId, $fieldList) ?? [];
    }

    /**
     * Determines the root page ID for a given page.
     *
     * @param int $pageId A page ID somewhere in a tree.
     * @param bool $forceFallback Force the explicit detection and do not use the current frontend root line
     * @param string $mountPointIdentifier
     * @return int The page's tree branch's root page ID
     */
    public function getRootPageId(
        int $pageId = 0,
        bool $forceFallback = false,
        string $mountPointIdentifier = ''
    ): int {
        if ($pageId === 0) {
            return 0;
        }

        /** @var Rootline $rootLine */
        $rootLine = GeneralUtility::makeInstance(Rootline::class);

        // frontend
        if (!empty($GLOBALS['TSFE']->rootLine)) {
            $rootLine->setRootLineArray($GLOBALS['TSFE']->rootLine);
        }

        // fallback, backend
        if ($forceFallback || !$rootLine->getHasRootPage()) {
            $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId, $mountPointIdentifier);
            try {
                $rootLineArray = $rootlineUtility->get();
            } catch (RuntimeException $e) {
                $rootLineArray = [];
            }
            $rootLine->setRootLineArray($rootLineArray);
        }

        return $rootLine->getRootPageId() ?: $pageId;
    }

    /**
     * This method determines the responsible site roots for a record by getting the rootPage of the record and checking
     * if the pid is references in another site with additionalPageIds and returning those rootPageIds as well.
     *
     * @param string $table
     * @param int $uid
     * @return array
     * @throws DBALDriverException
     * @throws Throwable
     */
    protected function buildResponsibleRootPageIds(string $table, int $uid): array
    {
        $rootPages = [];
        $rootPageId = $this->getRootPageIdByTableAndUid($table, $uid);
        if ($this->getIsRootPageId($rootPageId)) {
            $rootPages[] = $rootPageId;
        }
        if ($this->extensionConfiguration->getIsUseConfigurationTrackRecordsOutsideSiteroot()) {
            $recordPageId = $this->getRecordPageId($table, $uid);
            if ($recordPageId === 0) {
                return $rootPages;
            }
            $alternativeSiteRoots = $this->getAlternativeSiteRootPagesIds($table, $uid, $recordPageId);
            $rootPages = array_merge($rootPages, $alternativeSiteRoots);
        }

        return $rootPages;
    }

    /**
     * This method checks if the record is a pages record or another one and determines the rootPageId from the records
     * rootline.
     *
     * @param string $table
     * @param int $uid
     * @return int
     */
    protected function getRootPageIdByTableAndUid(string $table, int $uid): int
    {
        if ($table === 'pages') {
            return $this->getRootPageId($uid);
        }
        $recordPageId = $this->getRecordPageId($table, $uid);
        return $this->getRootPageId($recordPageId, true);
    }

    /**
     * Returns the pageId of the record or 0 when no valid record was found.
     *
     * @param string $table
     * @param int $uid
     * @return int
     */
    protected function getRecordPageId(string $table, int $uid): int
    {
        $record = BackendUtility::getRecord($table, $uid, 'pid');
        return (int)$record['pid'] ?? 0;
    }

    /**
     * When no root page can be determined we check if the pageIdOf the record is configured as additionalPageId in the index
     * configuration of another site, if so we return the rootPageId of this site.
     * The result is cached by the caching framework.
     *
     * @param string $table
     * @param int $uid
     * @param int $recordPageId
     * @return array
     * @throws DBALDriverException
     * @throws Throwable
     */
    public function getAlternativeSiteRootPagesIds(string $table, int $uid, int $recordPageId): array
    {
        $siteRootsByObservedPageIds = $this->getSiteRootsByObservedPageIds($table, $uid);
        if (!isset($siteRootsByObservedPageIds[$recordPageId])) {
            return [];
        }

        return $siteRootsByObservedPageIds[$recordPageId];
    }

    /**
     * Retrieves an optimized array structure with the monitored pageId as key and the relevant site rootIds as value.
     *
     * @param string $table
     * @param int $uid
     * @return array
     * @throws DBALDriverException
     * @throws Throwable
     */
    protected function getSiteRootsByObservedPageIds(string $table, int $uid): array
    {
        $cacheId = 'RootPageResolver' . '_' . 'getSiteRootsByObservedPageIds' . '_' . $table . '_' . $uid;
        $methodResult = $this->runtimeCache->get($cacheId);
        if (!empty($methodResult)) {
            return $methodResult;
        }

        $methodResult = $this->buildSiteRootsByObservedPageIds($table, $uid);
        $this->runtimeCache->set($cacheId, $methodResult);

        return $methodResult;
    }

    /**
     * This method builds an array with observer page id as key and rootPageIds as values to determine which root pages
     * are responsible for this record by referencing the pageId in additionalPageIds configuration.
     *
     * @param string $table
     * @param int $uid
     * @return array
     * @throws DBALDriverException
     * @throws Throwable
     */
    protected function buildSiteRootsByObservedPageIds(string $table, int $uid): array
    {
        $siteRootByObservedPageIds = [];
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $allSites = $siteRepository->getAvailableSites();

        foreach ($allSites as $site) {
            $solrConfiguration = $site->getSolrConfiguration();
            $indexingConfigurationName = $this->recordService->getIndexingConfigurationName($table, $uid, $solrConfiguration);
            if ($indexingConfigurationName === null) {
                continue;
            }
            $observedPageIdsOfSiteRoot = $solrConfiguration->getIndexQueueAdditionalPageIdsByConfigurationName($indexingConfigurationName);
            foreach ($observedPageIdsOfSiteRoot as $observedPageIdOfSiteRoot) {
                $siteRootByObservedPageIds[$observedPageIdOfSiteRoot][] = $site->getRootPageId();
            }
        }

        return $siteRootByObservedPageIds;
    }
}
