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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Index\Queue\UpdateHandler;

use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\MountPagesUpdater;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\RecordMonitor\Helper\RootPageResolver;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\DataUpdateHandler;
use ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\GarbageHandler;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Testcase for the DataUpdateHandler class.
 */
class DataUpdateHandlerTest extends SetUpUpdateHandler
{
    private const DUMMY_PAGE_ID = 10;

    protected DataUpdateHandler|MockObject $dataUpdateHandler;

    protected MountPagesUpdater|MockObject $mountPagesUpdaterMock;

    protected RootPageResolver|MockObject $rootPageResolverMock;

    protected DataHandler|MockObject $dataHandlerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mountPagesUpdaterMock = $this->createMock(MountPagesUpdater::class);
        $this->rootPageResolverMock = $this->createMock(RootPageResolver::class);
        $this->dataHandlerMock = $this->createMock(DataHandler::class);

        $this->dataUpdateHandler = $this->getAccessibleMock(
            DataUpdateHandler::class,
            [
                'getRecord',
                'getValidatedPid',
            ],
            [
                $this->recordServiceMock,
                $this->frontendEnvironmentMock,
                $this->tcaServiceMock,
                $this->indexQueueMock,
                $this->mountPagesUpdaterMock,
                $this->rootPageResolverMock,
                $this->pagesRepositoryMock,
                $this->dataHandlerMock,
                $this->loggerMock,
            ],
        );
        $this->dataUpdateHandler
            ->expects(self::any())
            ->method('getRecord')
            ->willReturn([
                'uid' => self::DUMMY_PAGE_ID,
                'title' => 'dummy page on which dummy ce is placed',
                'sys_language_uid' => 0,
                'doktype' => 1,
            ]);
        // Mock getValidatedPid to return the dummy page ID (BackendUtility::getRecord is static and can't be mocked)
        $this->dataUpdateHandler
            ->expects(self::any())
            ->method('getValidatedPid')
            ->willReturn(self::DUMMY_PAGE_ID);
    }

    /**
     * Init the rootPageResolverMock to simulate a valid
     * root page (self::DUMMY_PAGE_ID)
     */
    protected function initRootPageResolverForValidDummyRootPage(): void
    {
        $this->rootPageResolverMock
            ->expects(self::any())
            ->method('getRootPageId')
            ->willReturn(self::DUMMY_PAGE_ID);

        $this->rootPageResolverMock
            ->expects(self::any())
            ->method('getIsRootPageId')
            ->willReturn(true);

        $this->rootPageResolverMock
            ->expects(self::any())
            ->method('getResponsibleRootPageIds')
            ->willReturn([self::DUMMY_PAGE_ID]);
    }

    #[Test]
    public function handleContentElementUpdateTriggersSinglePageProcessing(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page on which dummy ce is placed',
            'sys_language_uid' => 0,
        ];
        $this->initSiteForDummyConfiguration($dummyPageRecord['uid']);

        $rootineUtilityMock = $this->createMock(RootlineUtility::class);
        GeneralUtility::addInstance(RootlineUtility::class, $rootineUtilityMock);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
            );

        $this->indexQueueMock
            ->expects(self::once())
            ->method('deleteItem')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
            );

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('pages')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isLocalizedRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'pages',
                $dummyPageRecord,
                $dummyPageRecord['uid'],
            )
            ->willReturn($dummyPageRecord['uid']);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(true);

        $this->inject($this->dataUpdateHandler, 'updateSubPagesRecursiveTriggerConfiguration', []);
        $this->dataUpdateHandler->handleContentElementUpdate(123);
    }

    #[Test]
    public function handleContentElementUpdateTriggersInvalidPageProcessing(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'invalid page on which dummy ce is placed',
            'sys_language_uid' => 0,
        ];
        $this->initSiteForDummyConfiguration($dummyPageRecord['uid']);

        $rootineUtilityMock = $this->createMock(RootlineUtility::class);
        GeneralUtility::addInstance(RootlineUtility::class, $rootineUtilityMock);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects(self::never())
            ->method('containsItem');

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('pages')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn([]);

        $this->inject($this->dataUpdateHandler, 'updateSubPagesRecursiveTriggerConfiguration', []);
        $this->dataUpdateHandler->handleContentElementUpdate(123);
    }

    #[Test]
    public function handleContentElementDeletionTriggersPageUpdate(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $contextMock = $this->createMock(Context::class);
        $contextMock->expects(self::any())->method('getPropertyFromAspect')->willReturn(1641472388);
        GeneralUtility::setSingletonInstance(Context::class, $contextMock);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with(
                'pages',
                self::DUMMY_PAGE_ID,
                1641472388,
            )
            ->willReturn(1);

        $this->dataUpdateHandler->handleContentElementDeletion(123);
    }

    #[Test]
    public function handlePageUpdateTriggersSinglePageProcessing(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
        ];

        $this->initBasicPageUpdateExpectations($dummyPageRecord);

        $rootineUtilityMock = $this->createMock(RootlineUtility::class);
        GeneralUtility::addInstance(RootlineUtility::class, $rootineUtilityMock);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
            );

        $this->inject($this->dataUpdateHandler, 'updateSubPagesRecursiveTriggerConfiguration', []);
        $this->dataUpdateHandler->handlePageUpdate($dummyPageRecord['uid']);
    }

    /**
     * Init basic page update expectations
     *
     * @param array $dummyPageRecord
     */
    protected function initBasicPageUpdateExpectations(array $dummyPageRecord): void
    {
        $this->initSiteForDummyConfiguration($dummyPageRecord['uid']);
        $this->indexQueueMock
            ->expects(self::once())
            ->method('deleteItem')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
            );

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('pages')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isLocalizedRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'pages',
                $dummyPageRecord,
                $dummyPageRecord['uid'],
            )
            ->willReturn($dummyPageRecord['uid']);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(true);
    }

    #[Test]
    public function handlePageUpdateTriggersRecursivePageProcessing(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
            'extendToSubpages' => 1,
        ];

        $GLOBALS['TCA']['pages'] = ['columns' => []];

        $frontendCacheMock = $this->createMock(VariableFrontend::class);
        $frontendCacheMock->method('has')->willReturn(true);
        $frontendCacheMock->method('get')->willReturnCallback(static function (string $identifier): ?array {
            if (str_starts_with($identifier, 'rootline-localcache-')) {
                return ['uid' => 1, 'no_search_sub_entries' => false];
            }
            return null;
        });
        $cacheManagerMock = $this->createMock(CacheManager::class);
        $cacheManagerMock->method('getCache')->willReturn($frontendCacheMock);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManagerMock);
        GeneralUtility::addInstance(PageRepository::class, $this->createMock(PageRepository::class));

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $connectionPoolMock = $this->createMock(ConnectionPool::class);
        $connectionPoolMock->method('getQueryBuilderForTable')->willReturn($queryBuilderMock);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPoolMock);

        $this->pagesRepositoryMock
            ->expects(self::any())
            ->method('getTreeList')
            ->willReturn(implode(',', [$dummyPageRecord['uid'], 100, 200]));

        $this->pagesRepositoryMock
            ->expects(self::any())
            ->method('getPage')
            ->willReturn($dummyPageRecord);
        $this->pagesRepositoryMock
            ->expects(self::any())
            ->method('getBackendEnableFields')
            ->willReturn(' AND deleted=0');
        $this->inject($this->dataUpdateHandler, 'pagesRepository', $this->pagesRepositoryMock);
        $this->initBasicPageUpdateExpectations($dummyPageRecord);

        $matcher = self::exactly(3);
        $this->indexQueueMock
            ->expects($matcher)
            ->method('updateItem')
            ->willReturnCallback(static function (string $type, int $uid) use ($dummyPageRecord, $matcher): int {
                match ($matcher->numberOfInvocations()) {
                    1 => self::assertEquals(
                        [
                            'pages',
                            $dummyPageRecord['uid'],
                        ],
                        [$type, $uid],
                    ),
                    2 => self::assertEquals(
                        [
                            'pages',
                            100,
                        ],
                        [$type, $uid],
                    ),
                    3 => self::assertEquals(
                        [
                            'pages',
                            200,
                        ],
                        [$type, $uid],
                    ),
                    default => self::fail('Unexpected number of invocations: ' . $matcher->numberOfInvocations()),
                };

                return 1;
            });

        $this->dataUpdateHandler->handlePageUpdate($dummyPageRecord['uid'], ['hidden' => 0]);
    }

    /**
     * Tests if the processing of a page with no connection to a valid root page
     * triggers just the mount page updater
     */
    #[Test]
    public function handlePageUpdateTriggersUnconnectedPageProcessing(): void
    {
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
        ];

        $this->rootPageResolverMock
            ->expects(self::once())
            ->method('getAlternativeSiteRootPagesIds')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $dummyPageRecord['uid'],
            )
            ->willReturn([]);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->frontendEnvironmentMock
            ->expects(self::never())
            ->method('getSolrConfigurationFromPageId');

        $this->dataUpdateHandler->handlePageUpdate($dummyPageRecord['uid']);
    }

    #[Test]
    public function handleRecordUpdateTriggersRecordProcessing(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $this->initSiteForDummyConfiguration(self::DUMMY_PAGE_ID);
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID,
        ];

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyRecord);

        $this->indexQueueMock
            ->expects(self::never())
            ->method('deleteItem');

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isLocalizedRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
            )
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
                $dummyRecord['uid'],
            )
            ->willReturn($dummyRecord['uid']);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
            )
            ->willReturn(true);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
            );

        $this->dataUpdateHandler->handleRecordUpdate($dummyRecord['uid'], 'tx_foo_bar');
    }

    /**
     * Tests if the processing of a record that couldn't be found in database
     * triggers the removal from index and queue
     */
    #[Test]
    public function handleRecordUpdateTriggersInvalidRecordProcessing(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $this->initSiteForDummyConfiguration(self::DUMMY_PAGE_ID);
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID,
        ];

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn([]);

        $this->indexQueueMock
            ->expects(self::never())
            ->method('containsItem');

        $this->dataUpdateHandler->handleRecordUpdate($dummyRecord['uid'], 'tx_foo_bar');
    }

    #[Test]
    public function handleRecordUpdateTriggersMultipleRootPagesRecordProcessing(): void
    {
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID,
        ];
        $this->initSiteForDummyConfiguration($dummyRecord['pid']);
        $this->initSiteForDummyConfiguration(20);

        $this->rootPageResolverMock
            ->expects(self::once())
            ->method('getResponsibleRootPageIds')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
            )
            ->willReturn([self::DUMMY_PAGE_ID, 20]);

        $this->typoScriptConfigurationMock
            ->expects(self::exactly(2))
            ->method('getIndexQueueIsMonitoredTable')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::exactly(2))
            ->method('getRecord')
            ->willReturn($dummyRecord);

        $this->indexQueueMock
            ->expects(self::never())
            ->method('deleteItem');

        $this->tcaServiceMock
            ->expects(self::exactly(2))
            ->method('isLocalizedRecord')
            ->willReturn(false);

        $this->tcaServiceMock
            ->expects(self::exactly(2))
            ->method('getTranslationOriginalUidIfTranslated')
            ->willReturn($dummyRecord['uid']);

        $this->tcaServiceMock
            ->expects(self::exactly(2))
            ->method('isEnabledRecord')
            ->willReturn(true);

        $this->indexQueueMock
            ->expects(self::exactly(2))
            ->method('updateItem');

        $this->dataUpdateHandler->handleRecordUpdate($dummyRecord['uid'], 'tx_foo_bar');
    }

    #[Test]
    public function handleVersionSwapAppliesPageChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
        ];

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(true);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with('pages', $dummyPageRecord['uid']);

        $this->dataUpdateHandler->handleVersionSwap($dummyPageRecord['uid'], 'pages');
    }

    #[Test]
    public function handleVersionSwapAppliesContentElementChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyRecordId = 123;
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
        ];

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(true);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with('pages', $dummyPageRecord['uid']);

        $this->dataUpdateHandler->handleVersionSwap($dummyRecordId, 'tt_content');
    }

    #[Test]
    public function handleVersionSwapAppliesInvalidPageChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'invalid dummy page to be processed',
            'sys_language_uid' => 0,
        ];

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn([]);

        $this->indexQueueMock
            ->expects(self::never())
            ->method('containsItem');

        $garbageHandlerMock = $this->createMock(GarbageHandler::class);
        $garbageHandlerMock
            ->expects(self::once())
            ->method('collectGarbage')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
            );
        GeneralUtility::addInstance(GarbageHandler::class, $garbageHandlerMock);

        $this->dataUpdateHandler->handleVersionSwap($dummyPageRecord['uid'], 'pages');
    }

    #[Test]
    public function handleVersionSwapAppliesRecordChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID,
        ];

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
            )
            ->willReturn(true);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
                $dummyRecord['uid'],
            )
            ->willReturn($dummyRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with('tx_foo_bar', $dummyRecord['uid']);

        $this->dataUpdateHandler->handleVersionSwap($dummyRecord['uid'], 'tx_foo_bar');
    }

    #[Test]
    public function handleVersionSwapAppliesInvalidRecordChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID,
        ];

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
        ->expects(self::once())
        ->method('getRecord')
        ->with(
            'tx_foo_bar',
            $dummyRecord['uid'],
            $this->typoScriptConfigurationMock,
        )
        ->willReturn([]);

        $this->indexQueueMock
            ->expects(self::never())
            ->method('containsItem');

        $garbageHandlerMock = $this->createMock(GarbageHandler::class);
        $garbageHandlerMock
            ->expects(self::once())
            ->method('collectGarbage')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
            );
        GeneralUtility::addInstance(GarbageHandler::class, $garbageHandlerMock);

        $this->dataUpdateHandler->handleVersionSwap($dummyRecord['uid'], 'tx_foo_bar');
    }

    #[Test]
    public function handleMovedPageAppliesPageChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyPageRecord = [
            'uid' => self::DUMMY_PAGE_ID,
            'title' => 'dummy page to be processed',
            'sys_language_uid' => 0,
        ];

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'pages',
                $dummyPageRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyPageRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'pages',
                $dummyPageRecord,
            )
            ->willReturn(true);

        $this->mountPagesUpdaterMock
            ->expects(self::once())
            ->method('update')
            ->with($dummyPageRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with('pages', $dummyPageRecord['uid']);

        $this->dataUpdateHandler->handleMovedPage($dummyPageRecord['uid']);
    }

    #[Test]
    public function handleMovedRecordAppliesRecordChangesToQueue(): void
    {
        $this->initRootPageResolverForValidDummyRootPage();
        $dummyRecord = [
            'uid' => 789,
            'pid' => self::DUMMY_PAGE_ID,
        ];

        $this->typoScriptConfigurationMock
            ->expects(self::once())
            ->method('getIndexQueueIsMonitoredTable')
            ->with('tx_foo_bar')
            ->willReturn(true);

        $this->recordServiceMock
            ->expects(self::once())
            ->method('getRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord['uid'],
                $this->typoScriptConfigurationMock,
            )
            ->willReturn($dummyRecord);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('isEnabledRecord')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
            )
            ->willReturn(true);

        $this->tcaServiceMock
            ->expects(self::once())
            ->method('getTranslationOriginalUidIfTranslated')
            ->with(
                'tx_foo_bar',
                $dummyRecord,
                $dummyRecord['uid'],
            )
            ->willReturn($dummyRecord['uid']);

        $this->indexQueueMock
            ->expects(self::once())
            ->method('updateItem')
            ->with('tx_foo_bar', $dummyRecord['uid']);

        $this->dataUpdateHandler->handleMovedRecord($dummyRecord['uid'], 'tx_foo_bar');
    }

    /**
     * Inits a site repository and site mock to return a configuration mock
     *
     * @param int $pageId
     * @return MockObject
     */
    protected function initSiteForDummyConfiguration(int $pageId): MockObject
    {
        $siteMock = $this->createMock(Site::class);
        $siteMock
            ->expects(self::once())
            ->method('getSolrConfiguration')
            ->willReturn($this->typoScriptConfigurationMock);

        $siteRepositoryMock = $this->createMock(SiteRepository::class);
        $siteRepositoryMock
            ->expects(self::once())
            ->method('getSiteByPageId')
            ->with($pageId)
            ->willReturn($siteMock);

        GeneralUtility::addInstance(SiteRepository::class, $siteRepositoryMock);

        return $siteMock;
    }
}
