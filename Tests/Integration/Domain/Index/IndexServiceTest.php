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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Domain\Index;

use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\Fixtures\IndexingServiceForTesting;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Traversable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Testcase for the record indexer
 */
class IndexServiceTest extends IntegrationTestBase
{
    /**
     * @inheritdoc
     * @todo: Remove unnecessary fixtures and remove that property as intended.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = true;

    protected array $testExtensionsToLoad = [
        'apache-solr-for-typo3/solr',
        '../vendor/apache-solr-for-typo3/solr/Tests/Integration/Fixtures/Extensions/fake_extension2',
    ];

    protected ?Queue $indexQueue = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeDefaultSolrTestSiteConfiguration();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);

        // Replace IndexingService with a test subclass that provides the
        // typo3.testing.context attribute required by the testing-framework's
        // FrontendUserHandler middleware (which doesn't null-check the attribute).
        /** @var \Symfony\Component\DependencyInjection\Container $container */
        $container = GeneralUtility::getContainer();
        $container->set(
            IndexingService::class,
            IndexingServiceForTesting::fromProductionService($container->get(IndexingService::class)),
        );
    }

    protected function addToIndexQueue(string $table, int $uid): void
    {
        // write an index queue item
        $this->indexQueue->updateItem($table, $uid, time());
    }

    public static function canResolveBaseAsPrefixDataProvider(): Traversable
    {
        yield 'absRefPrefixIsFoo' => [
            'absRefPrefix' => 'foo',
            'expectedUrl' => '/foo/en/?tx_ttnews%5Btt_news%5D=111&cHash=c3abc77c306e40ad619c3defe2f15950352874798840d7ff2bd8d341dd4291d7',
        ];
    }

    #[DataProvider('canResolveBaseAsPrefixDataProvider')]
    #[Test]
    public function canResolveBaseAsPrefix(string $absRefPrefix, string $expectedUrl): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_withBasePrefix_' . $absRefPrefix . '.csv');

        $this->mergeSiteConfiguration('integration_tree_one', ['base' => 'http://testone.site/' . $absRefPrefix . '/']);

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);

        // run the indexer
        $indexService->indexItems(1);

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Could not index document into solr');
        self::assertStringContainsString('"url":"' . $expectedUrl, $solrContent, 'Generated unexpected url with absRefPrefix = auto');
    }

    #[Test]
    public function subRequestsRestoreWorkingDirectory(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_withBasePrefix_foo.csv');

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        // Simulate CLI context: CWD is the project root, not the public directory.
        $originalCwd = getcwd();
        chdir(Environment::getProjectPath());

        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getFirstAvailableSite();
        $indexService = GeneralUtility::makeInstance(IndexService::class, $site);

        $indexService->indexItems(1);

        self::assertSame(
            Environment::getProjectPath(),
            getcwd(),
            'Working directory was not restored after sub-request indexing',
        );

        // Restore original CWD for test framework cleanup
        chdir($originalCwd);

        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents($this->getSolrCoreUrl('core_en') . '/select?q=*:*');
        self::assertStringContainsString('"numFound":1', $solrContent, 'Indexing failed when CWD was not the public directory');
    }

    /**
     * Reproduces #4628: when the IndexQueueWorker scheduler task runs in BE web
     * context, the frontend sub-request must not clobber $GLOBALS['BE_USER'] or
     * $GLOBALS['TYPO3_REQUEST'] — otherwise the scheduler module crashes when
     * rendering its list view after the task (ModuleTemplate::getBackendUser()
     * returns null -> TypeError).
     */
    #[Test]
    public function subRequestsRestoreBackendUserAndRequestGlobals(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_withBasePrefix_foo.csv');

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        // Simulate BE web context: $GLOBALS['BE_USER'] and $GLOBALS['TYPO3_REQUEST']
        // are set when the scheduler module dispatches the task.
        $sentinelBackendUser = $this->createMock(BackendUserAuthentication::class);
        $sentinelRequest = new ServerRequest('http://testone.site/typo3/module/system/scheduler');
        $previousBackendUser = $GLOBALS['BE_USER'] ?? null;
        $previousRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $GLOBALS['BE_USER'] = $sentinelBackendUser;
        $GLOBALS['TYPO3_REQUEST'] = $sentinelRequest;

        try {
            $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
            $site = $siteRepository->getFirstAvailableSite();
            $indexService = GeneralUtility::makeInstance(IndexService::class, $site);

            $indexService->indexItems(1);

            self::assertSame(
                $sentinelBackendUser,
                $GLOBALS['BE_USER'] ?? null,
                '$GLOBALS[\'BE_USER\'] was not restored after sub-request indexing — '
                . 'breaks scheduler module list rendering in BE web context (#4628).',
            );
            self::assertSame(
                $sentinelRequest,
                $GLOBALS['TYPO3_REQUEST'] ?? null,
                '$GLOBALS[\'TYPO3_REQUEST\'] was not restored after sub-request indexing.',
            );
        } finally {
            $GLOBALS['BE_USER'] = $previousBackendUser;
            $GLOBALS['TYPO3_REQUEST'] = $previousRequest;
        }
    }

    /**
     * Reproduces #4628 follow-up: even after BE_USER/TYPO3_REQUEST were
     * preserved, the BE module's styles were broken because the frontend
     * sub-request replaces the AssetCollector and PageRenderer singletons'
     * state and overrides $GLOBALS['LANG'] (LanguageService). The BE module
     * loses its registered CSS/JS/labels and renders without styles.
     */
    #[Test]
    public function subRequestsRestoreAssetCollectorPageRendererAndLanguageService(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_withBasePrefix_foo.csv');

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        $assetCollector = GeneralUtility::makeInstance(AssetCollector::class);
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);

        // Add a sentinel BE-style asset that should still be present after
        // the sub-request returns. Without the save/restore, the frontend
        // replaces the singleton state and the sentinel is gone.
        $assetCollector->addInlineStyleSheet('solr-test-be-style', '/* be sentinel */');
        $pageRenderer->addCssInlineBlock('solr-test-be-page-css', '/* page-renderer be sentinel */');
        $assetCollectorStateBefore = $assetCollector->getState();
        $pageRendererStateBefore = $pageRenderer->getState();

        // Simulate BE web context: $GLOBALS['LANG'] is set to a sentinel
        // LanguageService that the BE module would use for label translation.
        $sentinelLanguageService = $this->createMock(LanguageService::class);
        $previousLanguageService = $GLOBALS['LANG'] ?? null;
        $GLOBALS['LANG'] = $sentinelLanguageService;

        try {
            $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
            $site = $siteRepository->getFirstAvailableSite();
            $indexService = GeneralUtility::makeInstance(IndexService::class, $site);

            $indexService->indexItems(1);

            self::assertSame(
                $assetCollectorStateBefore,
                $assetCollector->getState(),
                'AssetCollector singleton state was not restored after sub-request — '
                . 'breaks BE module CSS/JS rendering (#4628 follow-up).',
            );
            self::assertSame(
                $pageRendererStateBefore,
                $pageRenderer->getState(),
                'PageRenderer singleton state was not restored after sub-request — '
                . 'breaks BE module CSS/JS rendering (#4628 follow-up).',
            );
            self::assertSame(
                $sentinelLanguageService,
                $GLOBALS['LANG'] ?? null,
                '$GLOBALS[\'LANG\'] was not restored after sub-request — '
                . 'breaks BE module label localisation (#4628 follow-up).',
            );
        } finally {
            $GLOBALS['LANG'] = $previousLanguageService;
            // Drop the sentinel BE assets so subsequent tests get a clean
            // singleton state. AssetCollector has no public remove API,
            // so we updateState() with a snapshot taken without the sentinel.
            $assetCollector->updateState(
                array_diff_key($assetCollectorStateBefore, ['inlineStyleSheets' => true])
                + ['inlineStyleSheets' => []],
            );
        }
    }

    /**
     * Reproduces the CLI multi-task scenario from #4628 (and PR #4646): when
     * $GLOBALS['BE_USER'], 'LANG' or 'TYPO3_REQUEST' were not set before the
     * sub-request, the restore must leave the keys absent — not assign them
     * to null. Otherwise downstream code that distinguishes "key missing"
     * from "key set to null" (e.g. via array_key_exists()) sees a state that
     * never existed before and may take wrong code paths (cf. CLI scheduler
     * running multiple tasks where the second task crashes in DataHandler
     * because it expects $GLOBALS['BE_USER'] to be a real BackendUserAuthentication
     * or be absent — not null).
     */
    #[Test]
    public function subRequestsLeaveUnsetGlobalsUnsetAfterSubRequest(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/can_index_custom_record_withBasePrefix_foo.csv');

        $this->addToIndexQueue('tx_fakeextension_domain_model_bar', 111);

        // Simulate CLI context: ensure the three globals are entirely absent.
        $previousBackendUser = $GLOBALS['BE_USER'] ?? null;
        $previousLanguageService = $GLOBALS['LANG'] ?? null;
        $previousRequest = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $hadBackendUser = array_key_exists('BE_USER', $GLOBALS);
        $hadLanguageService = array_key_exists('LANG', $GLOBALS);
        $hadRequest = array_key_exists('TYPO3_REQUEST', $GLOBALS);
        unset($GLOBALS['BE_USER'], $GLOBALS['LANG'], $GLOBALS['TYPO3_REQUEST']);

        try {
            $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
            $site = $siteRepository->getFirstAvailableSite();
            $indexService = GeneralUtility::makeInstance(IndexService::class, $site);

            $indexService->indexItems(1);

            self::assertFalse(
                array_key_exists('BE_USER', $GLOBALS),
                '$GLOBALS[\'BE_USER\'] was unset before the sub-request and must remain absent afterwards '
                . '(not be assigned null) — otherwise CLI multi-task scheduler runs see a fake null value '
                . 'and crash in downstream code (#4628 / PR #4646).',
            );
            self::assertFalse(
                array_key_exists('LANG', $GLOBALS),
                '$GLOBALS[\'LANG\'] was unset before the sub-request and must remain absent afterwards.',
            );
            self::assertFalse(
                array_key_exists('TYPO3_REQUEST', $GLOBALS),
                '$GLOBALS[\'TYPO3_REQUEST\'] was unset before the sub-request and must remain absent afterwards.',
            );
        } finally {
            if ($hadBackendUser) {
                $GLOBALS['BE_USER'] = $previousBackendUser;
            }
            if ($hadLanguageService) {
                $GLOBALS['LANG'] = $previousLanguageService;
            }
            if ($hadRequest) {
                $GLOBALS['TYPO3_REQUEST'] = $previousRequest;
            }
        }
    }
}
