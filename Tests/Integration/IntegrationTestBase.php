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

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Index\IndexService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeIndexingSubRequestIsPreparedEvent;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\Exception\InvalidConnectionException;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingInstructions;
use ApacheSolrForTypo3\Solr\IndexQueue\IndexingService;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\System\Cache\TwoLevelCache;
use ApacheSolrForTypo3\Solr\System\Util\SiteUtility;
use ApacheSolrForTypo3\Solr\Task\EventQueueWorkerTask;
use ApacheSolrForTypo3\Solr\Tests\Integration\Fixtures\IndexingServiceForTesting;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Symfony\Component\DependencyInjection\Container;
use Throwable;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Scheduler;
use TYPO3\CMS\Scheduler\Task\TaskSerializer;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for all integration tests in the EXT:solr project
 */
abstract class IntegrationTestBase extends FunctionalTestCase
{
    use SiteBasedTestTrait;
    private int $previousErrorReporting;

    protected array $coreExtensionsToLoad = [
        'typo3/cms-install',
        'typo3/cms-reports',
        'typo3/cms-scheduler',
        'typo3/cms-tstemplate',
        'typo3/cms-fluid-styled-content',
    ];

    /**
     * @var array<string, array{id: int, title: string, locale: string}|array{id: int, title: string, locale: string, fallbackType: string|null, fallbacks: string|null}>
     */
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
        'DE' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF8', 'fallbackType' => 'fallback', 'fallbacks' => 'EN'],
        'DA' => ['id' => 2, 'title' => 'Danish', 'locale' => 'da_DA.UTF8', 'fallbackType' => 'strict'],
    ];

    protected array $testExtensionsToLoad = [
        'apache-solr-for-typo3/solr',
    ];

    protected array $testSolrCores = [
        'core_en',
        'core_de',
        'core_da',
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' =>  [
            'exceptionalErrors' =>  E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
        ],
    ];

    /**
     * If set to true in subclasses, the import of configured root pages will be skipped.
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousErrorReporting = error_reporting();
        $this->failWhenSolrDeprecationIsCreated();

        // Clean Solr cores at the START of each test to prevent cross-contamination from previous tests
        $this->cleanUpAllCoresOnSolrServerAndAssertEmpty();
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        error_reporting($this->previousErrorReporting);

        // Reset static caches that survive GeneralUtility::purgeInstances()
        ConnectionManager::resetConnections();
        SiteUtility::reset();
        TwoLevelCache::flushAllCaches();

        parent::tearDown();
    }

    /**
     * Override getInstanceIdentifier to support paratest worker-specific test instances.
     * Each worker gets its own test instance directory to prevent site config and Solr core sharing.
     */
    protected static function getInstanceIdentifier(): string
    {
        $baseIdentifier = parent::getInstanceIdentifier();
        $token = getenv('TEST_TOKEN');
        if ($token !== false && $token !== '') {
            // Paratest uses 1-based numbering; convert to 0-based worker index
            $workerIndex = (int)$token - 1;
            return $baseIdentifier . '_w' . $workerIndex;
        }
        return $baseIdentifier;
    }

    /**
     * Override getInstancePath to ensure worker-specific paths are used.
     * Necessary to guarantee $this->getInstancePath() (line 315 of FunctionalTestCase::setUp)
     * returns the worker-specific path.
     */
    protected static function getInstancePath(): string
    {
        $identifier = static::getInstanceIdentifier();
        return ORIGINAL_ROOT . 'typo3temp/var/tests/functional-' . $identifier;
    }

    /**
     * @throws InvalidArgumentException
     *
     * Please don't use that method, except you really want to clean a single core.
     *
     * @internal
     */
    protected function cleanUpSolrServerAndAssertEmpty(string $coreName = 'core_en'): void
    {
        $this->validateTestCoreName($coreName);

        // cleanup the solr server
        $resolvedCoreName = $this->resolveCoreName($coreName);
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $response = $requestFactory->request(
            $this->getSolrConnectionUriAuthority() . '/solr/' . $resolvedCoreName . '/update?commit=true',
            'POST',
            [
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => '<delete><query>*:*</query></delete>',
            ],
        );
        $result = $response->getBody()->getContents();

        if (!str_contains($result, '<int name="QTime">')) {
            self::fail('Could not empty solr test index');
        }

        $this->assertSolrIsEmpty($coreName);
    }

    protected function cleanUpAllCoresOnSolrServerAndAssertEmpty(): void
    {
        foreach ($this->testSolrCores as $coreName) {
            try {
                $this->cleanUpSolrServerAndAssertEmpty($coreName);
            } catch (InvalidArgumentException) {
                // no wrong cores can be passed there, nothing to do.
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function waitToBeVisibleInSolr(string $coreName = 'core_en'): void
    {
        $this->validateTestCoreName($coreName);
        $resolvedCoreName = $this->resolveCoreName($coreName);
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $requestFactory->request(
            $this->getSolrConnectionUriAuthority() . '/solr/' . $resolvedCoreName . '/update?commit=true',
            'POST',
            [
                'headers' => ['Content-Type' => 'application/xml'],
                'body' => '<commit/>',
            ],
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateTestCoreName(string $coreName): void
    {
        if (!in_array($coreName, $this->testSolrCores, true)) {
            throw new InvalidArgumentException(
                'No valid test core passed',
                1104133825,
            );
        }
    }

    /**
     * Assertion to check if the solr server is empty.
     */
    protected function assertSolrIsEmpty(string $coreName = 'core_en'): void
    {
        $this->assertSolrContainsDocumentCount(0, coreName: $coreName);
    }

    /**
     * Assertion to check if the solr server contains an expected count of documents.
     */
    protected function assertSolrContainsDocumentCount(
        int $documentCount,
        ?string $message = null,
        string $coreName = 'core_en',
    ): void {
        $resolvedCoreName = $this->resolveCoreName($coreName);
        $solrContent = file_get_contents(
            $this->getSolrConnectionUriAuthority() . '/solr/' . $resolvedCoreName . '/select?q=*:*',
        );
        self::assertStringContainsString(
            '"numFound":' . $documentCount,
            $solrContent,
            $message ?? 'Solr contains unexpected amount of documents',
        );
    }

    /**
     * Writes default site-config.yaml files for testing sites one, two and three.
     * The records for root pages(incl. translations) and TypoScript templates will be imported by default.
     *
     * To skip the import of records for root pages, the property {@link skipImportRootPagesAndTemplatesForConfiguredSites} must be set to false.
     *
     * To add or override TypoScript setting please use following typo3/testing-framework methods:
     * * {@link addTypoScriptToTemplateRecord()}
     * * {@link setUpFrontendRootPage()}
     */
    protected function writeDefaultSolrTestSiteConfiguration(): void
    {
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort($solrConnectionInfo['scheme'], $solrConnectionInfo['host'], $solrConnectionInfo['port']);
    }

    /**
     * @internal Don't use that method in tests, except you want to simulate the misconfiguration.
     */
    protected function writeDefaultSolrTestSiteConfigurationForHostAndPort(
        ?string $scheme = 'http',
        ?string $host = 'localhost',
        ?int $port = 8983,
        ?bool $disableDefaultLanguage = false,
    ): void {
        $defaultLanguage = $this->buildDefaultLanguageConfiguration('EN', '/en/');
        $defaultLanguage['solr_core_read'] = $this->resolveCoreName('core_en');

        if ($disableDefaultLanguage === true) {
            $defaultLanguage['enabled'] = 0;
        }

        $german = $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'fallback');
        $german['solr_core_read'] = $this->resolveCoreName('core_de');

        $danish = $this->buildLanguageConfiguration('DA', '/da/');
        $danish['solr_core_read'] = $this->resolveCoreName('core_da');

        $this->writeSiteConfiguration(
            'integration_tree_one',
            $this->buildSiteConfiguration(1, 'http://testone.site/'),
            [
                $defaultLanguage, $german, $danish,
            ],
            $this->buildErrorHandlingConfiguration('Fluid', [404]),
        );

        $this->writeSiteConfiguration(
            'integration_tree_two',
            $this->buildSiteConfiguration(111, 'http://testtwo.site/'),
            [
                $defaultLanguage, $german, $danish,
            ],
            $this->buildErrorHandlingConfiguration('Fluid', [404]),
        );

        $this->writeSiteConfiguration(
            'integration_tree_three',
            $this->buildSiteConfiguration(211, 'http://testthree.site/'),
            [$defaultLanguage],
        );

        $globalSolrSettings = [
            'solr_scheme_read' => $scheme,
            'solr_host_read' => $host,
            'solr_port_read' => $port,
            'solr_timeout_read' => 20,
            'solr_path_read' => '/',
            'solr_use_write_connection' => false,
        ];
        $this->mergeSiteConfiguration('integration_tree_one', $globalSolrSettings);
        $this->mergeSiteConfiguration('integration_tree_two', $globalSolrSettings);
        // disable solr for site three
        $this->mergeSiteConfiguration('integration_tree_three', ['solr_enabled_read' => false]);

        $this->importRootPagesAndTemplatesForConfiguredSites();

        clearstatcache();
    }

    /**
     * Imports the root pages and TypoScript templates for configured sites.
     *
     * Note: This method is executed by default.
     *       The execution of this method call can be skipped for subclasses by setting
     *       {@link skipImportRootPagesAndTemplatesForConfiguredSites} property to false.
     */
    private function importRootPagesAndTemplatesForConfiguredSites(): void
    {
        if ($this->initializeDatabase === false) {
            $this->skipImportRootPagesAndTemplatesForConfiguredSites = true;
            return;
        }
        if ($this->skipImportRootPagesAndTemplatesForConfiguredSites === true) {
            return;
        }
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sites_setup_and_data_set/01_integration_tree_one.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sites_setup_and_data_set/02_integration_tree_two.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sites_setup_and_data_set/03_integration_tree_three.csv');
    }

    /**
     * This method registers an error handler that fails the testcase when an E_USER_DEPRECATED error
     * is thrown with the prefix solr:deprecation
     */
    protected function failWhenSolrDeprecationIsCreated(): ?callable
    {
        error_reporting(error_reporting() & ~E_USER_DEPRECATED);
        return set_error_handler(
            function (int $id, string $msg, string $file, int $line): bool {
                if ($id === E_USER_DEPRECATED && str_starts_with($msg, 'solr:deprecation: ')) {
                    $this->fail("Executed deprecated EXT:solr code: in $file:$line" . PHP_EOL . $msg);
                }
                return true;
            },
        );
    }

    protected function getSolrConnectionInfo(): array
    {
        return [
            'scheme' => getenv('TESTING_SOLR_SCHEME') ?: 'http',
            'host' => getenv('TESTING_SOLR_HOST') ?: 'localhost',
            'port' => getenv('TESTING_SOLR_PORT') ?: 8983,
        ];
    }

    /**
     * Returns solr connection URI authority as string as
     * scheme://host:port
     */
    protected function getSolrConnectionUriAuthority(): string
    {
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        return $solrConnectionInfo['scheme'] . '://' . $solrConnectionInfo['host'] . ':' . $solrConnectionInfo['port'];
    }

    /**
     * Returns the paratest worker token (0-indexed), or null when not running in parallel.
     */
    protected function getParatestWorkerToken(): ?int
    {
        $token = getenv('TEST_TOKEN');
        if ($token === false || $token === '') {
            return null;
        }
        return (int)$token;
    }

    /**
     * Maps a logical core name (e.g. 'core_en') to its worker-specific variant
     * (e.g. 'core_en_3') when running under paratest. Worker 0 uses the base core
     * without suffix. Returns the name unchanged for sequential runs (no TEST_TOKEN).
     *
     * Note: Paratest uses 1-based worker numbering (TEST_TOKEN=1-8 for 8 workers),
     * so we subtract 1 to match our 0-based core naming (core_en is base, core_en_1-7 are workers).
     */
    protected function resolveCoreName(string $coreName): string
    {
        $token = $this->getParatestWorkerToken();
        if ($token === null) {
            return $coreName;
        }
        // Paratest uses 1-based numbering; subtract 1 to get 0-based worker index
        $token = $token - 1;
        if ($token === 0) {
            return $coreName;  // Worker 0 uses the base core
        }
        return $coreName . '_' . $token;
    }

    /**
     * Returns the full Solr core base URL, resolved to the current worker's core.
     * Example: http://solr-tests:8985/solr/core_en_3
     */
    protected function getSolrCoreUrl(string $coreName = 'core_en'): string
    {
        return $this->getSolrConnectionUriAuthority() . '/solr/' . $this->resolveCoreName($coreName);
    }

    /**
     * Returns inaccessible(private/protected/etc.) property from given object.
     */
    protected function getInaccessiblePropertyFromObject(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        try {
            return $reflection->getProperty($property)->getValue($object);
        } catch (ReflectionException $exception) {
            self::fail(sprintf(
                'Can not read property "%s" from object of type "%s": %s',
                $property,
                $object::class,
                $exception->getMessage(),
            ));
        }
    }

    /*
        Nimut testing framework goodies, copied from https://github.com/Nimut/testing-framework
     */

    /**
     * Injects $dependency into property $name of $target
     *
     * This is a convenience method for setting a protected or private property in
     * a test subject for the purpose of injecting a dependency.
     *
     * Copied from https://github.com/Nimut/testing-framework/blob/3d0573b23fe16157460b4e73e51e1cc0903ea35c/src/TestingFramework/TestCase/AbstractTestCase.php#L247-L284
     *
     * @param object $target The instance which needs the dependency
     * @param string $name Name of the property to be injected
     * @param mixed $dependency The dependency to inject - usually an object but can also be any other type
     */
    protected function inject(
        object $target,
        string $name,
        mixed $dependency,
    ): void {
        $objectReflection = new ReflectionObject($target);
        $methodNamePart = strtoupper($name[0]) . substr($name, 1);
        if ($objectReflection->hasMethod('set' . $methodNamePart)) {
            $methodName = 'set' . $methodNamePart;
            $target->$methodName($dependency);
        } elseif ($objectReflection->hasMethod('inject' . $methodNamePart)) {
            $methodName = 'inject' . $methodNamePart;
            $target->$methodName($dependency);
        } elseif ($objectReflection->hasProperty($name)) {
            $objectReflection->getProperty($name)->setValue($target, $dependency);
        } else {
            self::fail('Could not inject ' . $name . ' into object of type ' . $target::class);
        }
    }

    /**
     * Helper function to call protected or private methods
     *
     * Copied from https://github.com/Nimut/testing-framework/blob/3d0573b23fe16157460b4e73e51e1cc0903ea35c/src/TestingFramework/TestCase/AbstractTestCase.php#L227-L245
     *
     * @param object $object The object to be invoked
     * @param string $name the name of the method to call
     * @throws ReflectionException
     */
    protected function callInaccessibleMethod(object $object, string $name): mixed
    {
        // Remove first two arguments ($object and $name)
        $arguments = func_get_args();
        array_splice($arguments, 0, 2);

        $reflectionObject = new ReflectionObject($object);
        $reflectionMethod = $reflectionObject->getMethod($name);

        return $reflectionMethod->invokeArgs($object, $arguments);
    }

    /**
     * Adds TypoScript setup snippet to the existing template record
     *
     * @throws DBALException
     */
    protected function addTypoScriptConstantsToTemplateRecord(int $pageId, string $constants): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('sys_template');
        $statement = $connection->select(['*'], 'sys_template', ['pid' => $pageId, 'root' => 1]);
        $template = $statement->fetchAssociative();

        if (empty($template)) {
            self::fail('Cannot find root template on page with id: "' . $pageId . '"');
        }
        $updateFields['constants'] = $template['constants'] . LF . $constants;
        $connection->update(
            'sys_template',
            $updateFields,
            ['uid' => $template['uid']],
        );
    }

    /**
     * Queues the given pages and indexes them the way the scheduler task does, so that
     * everything between IndexService and the sub-request is covered by the test.
     *
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     * @throws DBALException
     * @throws NoSuchCacheException
     * @throws InvalidConnectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function indexPages(array $importPageIds): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $queuedItemsPerRootPage = [];
        foreach ($importPageIds as $importPageId) {
            $site = $siteFinder->getSiteByPageId($importPageId);
            $this->addPageToIndexQueue($importPageId, $site);
            $rootPageId = $site->getRootPageId();
            $queuedItemsPerRootPage[$rootPageId] = ($queuedItemsPerRootPage[$rootPageId] ?? 0) + 1;
        }

        foreach ($queuedItemsPerRootPage as $rootPageId => $queuedItems) {
            $this->indexQueuedItems($queuedItems, $rootPageId);
        }

        $this->waitToBeVisibleInSolr();
    }

    /**
     * @throws InvalidArgumentException
     * @throws DBALException
     */
    protected function indexPageQueueItem(Item $item, int $language = 0, string $coreName = 'core_en'): bool
    {
        $parameters = [];
        if ($item->hasIndexingProperty('isMountedPage')) {
            $parameters['MP'] = $item->getIndexingProperty('mountPageSource')
                . '-' . $item->getIndexingProperty('mountPageDestination');
        }

        if ($language > 0) {
            $parameters['_language'] = $language;
        }

        $frontendUrl = $item->getSite()->getTypo3SiteObject()->getRouter()->generateUri(
            $item->getRecordUid(),
            $parameters,
        );

        $response = $this->executePageIndexer((string)$frontendUrl, $item);

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_template');
        $connection->update(
            'tx_solr_indexqueue_item',
            ['indexed' => time()],
            ['uid' => $item->getIndexQueueUid()],
        );

        return $response->getStatusCode() === 200;
    }

    /**
     * Executes a Frontend sub-request to trigger page indexing via the new
     * IndexingInstructions pipeline (SolrIndexingMiddleware).
     */
    protected function executePageIndexer(string $url, Item $item, ?int $frontendUserId = null): ResponseInterface
    {
        $instructions = new IndexingInstructions(
            items: [$item],
            action: IndexingInstructions::ACTION_INDEX_PAGE,
            language: 0,
            accessRootline: (string)Rootline::getAccessRootlineByPageId($item->getRecordUid()),
            parameters: ['item' => $item->getIndexQueueUid()],
        );

        $request = new InternalRequest($url);
        $request = $request->withAttribute('solr.indexingInstructions', $instructions);

        $requestContext = null;
        if ($frontendUserId !== null) {
            $requestContext = (new InternalRequestContext())->withFrontendUserId($frontendUserId);
        }

        $response = $this->executeFrontendSubRequest($request, $requestContext);

        /** @var VariableFrontend $runtimeCache */
        $runtimeCache = GeneralUtility::makeInstance(CacheManager::class)->getCache('runtime');
        $runtimeCache->flush();

        $response->getBody()->rewind();
        return $response;
    }

    /**
     * Indexes queued items through the production pipeline, which runs one real frontend
     * sub-request per item, unlike indexPages(), which fakes that sub-request.
     *
     * IndexingService is swapped for a test subclass providing the typo3.testing.context
     * attribute that the testing-framework's FrontendUserHandler middleware expects.
     *
     * @throws DBALException
     * @throws InvalidArgumentException
     * @throws SiteNotFoundException
     * @throws InvalidConnectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function indexQueuedItems(int $limit, int $rootPageId = 1): bool
    {
        $this->useIndexingServiceForTesting();

        $site = GeneralUtility::makeInstance(SiteRepository::class)->getSiteByRootPageId($rootPageId);
        return GeneralUtility::makeInstance(IndexService::class, $site)->indexItems($limit);
    }

    /**
     * Indexes one queue item the way IndexService does it, so that the item's indexing
     * instructions come from production: the access rootline including the mount point
     * parameter, and one sub-request per language and content access group.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function indexQueuedItem(Item $item): bool
    {
        return $this->useIndexingServiceForTesting()->indexItems([$item]);
    }

    /**
     * Replaces IndexingService with the test subclass that supplies the typo3.testing.context
     * attribute the testing-framework's FrontendUserHandler middleware expects.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function useIndexingServiceForTesting(): IndexingService
    {
        /** @var Container $container */
        $container = $this->getContainer();
        GeneralUtility::setContainer($container);
        $indexingService = $container->get(IndexingService::class);
        // The container refuses to replace an already initialized service, and a test may index
        // more than once.
        if (!$indexingService instanceof IndexingServiceForTesting) {
            $indexingService = IndexingServiceForTesting::fromProductionService($indexingService);
            $container->set(IndexingService::class, $indexingService);
        }

        return $indexingService;
    }

    /**
     * Does what IndexingService does before every sub-request, so that shared services can be
     * asserted to drop the state of the previous one.
     */
    protected function dispatchBeforeIndexingSubRequestIsPreparedEvent(): void
    {
        $item = new Item([
            'uid' => 1,
            'root' => 1,
            'item_type' => 'pages',
            'item_uid' => 1,
            'changed' => 1,
            'indexing_configuration' => 'pages',
        ]);

        $this->get(EventDispatcherInterface::class)->dispatch(
            new BeforeIndexingSubRequestIsPreparedEvent(
                $item,
                0,
                new IndexingInstructions([$item], IndexingInstructions::ACTION_INDEX_PAGE),
            ),
        );
    }

    /**
     * Adds a page to the queue (into DB table tx_solr_indexqueue_item) so it can
     * be fetched via a frontend sub-request
     *
     * @throws DBALException
     */
    protected function addPageToIndexQueue(int $pageId, Site $site): Item
    {
        $queueItemSearchCriteria = [
            'root' => $site->getRootPageId(),
            'item_type' => 'pages',
            'item_uid' => $pageId,
            'indexing_configuration' => 'pages',
        ];
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');
        // Check if item (type + Page ID) is already in index, if so update it
        $row = $connection->select(['*'], 'tx_solr_indexqueue_item', $queueItemSearchCriteria)->fetchAssociative();
        if (is_array($row)) {
            // Resetting "indexed" is what makes the item pending again: IndexService picks up
            // items with changed > indexed, and an item queued here may already carry the
            // timestamp of an earlier indexing run.
            $connection->update(
                'tx_solr_indexqueue_item',
                [
                    'changed' => 1007007007,
                    'indexed' => 0,
                    'errors' => '',
                ],
                [
                    'uid' => $row['uid'],
                ],
            );
            $queueItem = array_merge(
                $row,
                [
                    'changed' => 1007007007,
                    'indexed' => 0,
                    'errors' => '',
                ],
            );
        } else {
            $queueItem = $queueItemSearchCriteria
                + [
                    'changed' => 1007007007,
                    'errors' => '',
                ];
            $connection->insert('tx_solr_indexqueue_item', $queueItem);
            $queueItem['uid'] = (int)$connection->lastInsertId();
            $queueItem = $connection->select(['*'], 'tx_solr_indexqueue_item', ['uid' => $queueItem['uid']])->fetchAssociative();
        }
        return new Item($queueItem);
    }

    /**
     * Returns the Item for given index queue uid
     *
     * @throws DBALException
     */
    protected function getIndexQueueItem(int $itemUid): Item
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');
        $itemData = $connection->select(['*'], 'tx_solr_indexqueue_item', ['uid' => $itemUid])->fetchAssociative();
        return new Item($itemData);
    }

    /**
     * Triggers event queue processing
     *
     * @throws Throwable
     */
    protected function processEventQueue(): void
    {
        /** @var EventQueueWorkerTask $task */
        $task = GeneralUtility::makeInstance(EventQueueWorkerTask::class);

        /** @var Scheduler $scheduler */
        $scheduler = GeneralUtility::makeInstance(
            Scheduler::class,
            $this->createMock(NullLogger::class),
            $this->createMock(TaskSerializer::class),
            $this->createMock(SchedulerTaskRepository::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $scheduler->executeTask($task);
    }

    protected function addSimpleFrontendRenderingToTypoScriptRendering(int $templateRecord, string $additionalContent = ''): void
    {
        $this->addTypoScriptToTemplateRecord($templateRecord, '
page = PAGE
page.typeNum = 0
config.index_enable = 1

# very simple rendering
page.10 = CONTENT
page.10 {
  table = tt_content
  select.orderBy = sorting
  select.where = colPos=0
  renderObj = COA
  renderObj {
    10 = TEXT
    10.field = bodytext
  }
}
' . $additionalContent);
    }
}
