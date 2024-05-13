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
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for all integration tests in the EXT:solr project
 *
 * @author Timo Schmidt
 */
abstract class IntegrationTestBase extends FunctionalTestCase
{
    use SiteBasedTestTrait;
    private $previousErrorHandler;

    protected array $coreExtensionsToLoad = [
        'typo3/cms-scheduler',
        'typo3/cms-fluid-styled-content',
    ];

    /**
     * @var array<string, array{id: int, title: string, locale: string}|array{id: int, title: string, locale: string, fallbackType: string|null, fallbacks: string|null}>
     */
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
        'DE' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF8', 'fallbackType' => 'fallback', 'fallbacks' => 'EN'],
        'DA' => ['id' => 2, 'title' => 'Danish', 'locale' => 'da_DA.UTF8'],
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/solr',
    ];

    protected array $testSolrCores = [
        'core_en',
        'core_de',
        'core_dk',
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
        //this is needed by the TYPO3 core.
        chdir(Environment::getPublicPath() . '/');
        $this->instancePath = $this->getInstancePath();
        $this->previousErrorHandler = $this->failWhenSolrDeprecationIsCreated();
    }

    protected function tearDown(): void
    {
        set_error_handler($this->previousErrorHandler);
        parent::tearDown();
    }

    /**
     * @param string|null $coreName
     */
    protected function cleanUpSolrServerAndAssertEmpty(?string $coreName = 'core_en'): void
    {
        $this->validateTestCoreName($coreName);

        // cleanup the solr server
        $result = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/' . $coreName . '/update?stream.body=<delete><query>*:*</query></delete>&commit=true');
        if (!str_contains($result, '<int name="QTime">')) {
            self::fail('Could not empty solr test index');
        }

        // we wait to make sure the document will be deleted in solr
        $this->waitToBeVisibleInSolr();

        $this->assertSolrIsEmpty();
    }

    /**
     * @param string|null $coreName
     *
     * @return array|false
     */
    protected function waitToBeVisibleInSolr(?string $coreName = 'core_en'): array|false
    {
        $this->validateTestCoreName($coreName);
        $url = $this->getSolrConnectionUriAuthority() . '/solr/' . $coreName . '/update?softCommit=true';
        return get_headers($url);
    }

    protected function validateTestCoreName(string $coreName): void
    {
        if (!in_array($coreName, $this->testSolrCores, true)) {
            throw new InvalidArgumentException('No valid test core passed');
        }
    }

    /**
     * Assertion to check if the solr server is empty.
     */
    protected function assertSolrIsEmpty(): void
    {
        $this->assertSolrContainsDocumentCount(0);
    }

    /**
     * Assertion to check if the solr server contains an expected count of documents.
     */
    protected function assertSolrContainsDocumentCount(int $documentCount): void
    {
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":' . $documentCount, $solrContent, 'Solr contains unexpected amount of documents');
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

    protected static string $lastSiteCreated = '';

    /**
     * @internal Don't use that method in tests, except you want to simulate the misconfiguration.
     */
    protected function writeDefaultSolrTestSiteConfigurationForHostAndPort(
        ?string $scheme = 'http',
        ?string $host = 'localhost',
        ?int $port = 8983,
        ?bool $disableDefaultLanguage = false,
    ): void {
        $siteCreatedHash = md5($scheme . $host . $port . $disableDefaultLanguage);
        if (self::$lastSiteCreated === $siteCreatedHash) {
            return;
        }

        $defaultLanguage = $this->buildDefaultLanguageConfiguration('EN', '/en/');
        $defaultLanguage['solr_core_read'] = 'core_en';

        if ($disableDefaultLanguage === true) {
            $defaultLanguage['enabled'] = 0;
        }

        $german = $this->buildLanguageConfiguration('DE', '/de/', ['EN'], 'fallback');
        $german['solr_core_read'] = 'core_de';

        $danish = $this->buildLanguageConfiguration('DA', '/da/');
        $danish['solr_core_read'] = 'core_da';

        $this->writeSiteConfiguration(
            'integration_tree_one',
            $this->buildSiteConfiguration(1, 'http://testone.site/'),
            [
                $defaultLanguage, $german, $danish,
            ],
            $this->buildErrorHandlingConfiguration('Fluid', [404])
        );

        $this->writeSiteConfiguration(
            'integration_tree_two',
            $this->buildSiteConfiguration(111, 'http://testtwo.site/'),
            [
                $defaultLanguage, $german, $danish,
            ],
            $this->buildErrorHandlingConfiguration('Fluid', [404])
        );

        $this->writeSiteConfiguration(
            'integration_tree_three',
            $this->buildSiteConfiguration(211, 'http://testthree.site/'),
            [$defaultLanguage]
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
        usleep(500);
        self::$lastSiteCreated = $siteCreatedHash;
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
        return set_error_handler(function(int $id, string $msg, string $file, int $line): bool {
            if ($id === E_USER_DEPRECATED && str_starts_with($msg, 'solr:deprecation: ')) {
                $this->fail('Executed deprecated EXT:solr code: ' . $msg);
            }
            return true;
        });
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
     * Returns inaccessible(private/protected/etc.) property from given object.
     */
    protected function getInaccessiblePropertyFromObject(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        try {
            $property = $reflection->getProperty($property);
        } catch (ReflectionException $e) {
            return null;
        }
        return $property->getValue($object);
    }

    /*
        Nimut testing framework goodies, copied from https://github.com/Nimut/testing-framework
     */

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
            ['uid' => $template['uid']]
        );
    }

    /**
     * @throws SiteNotFoundException
     */
    protected function indexPages(array $importPageIds, int $frontendUserId = null)
    {
        // Mark the pages as items to index
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        foreach ($importPageIds as $importPageId) {
            $site = $siteFinder->getSiteByPageId($importPageId);
            $queueItem = $this->addPageToIndexQueue($importPageId, $site);
            $frontendUrl = $site->getRouter()->generateUri($importPageId);
            $this->executePageIndexer($frontendUrl, $queueItem, $frontendUserId);
        }
        $this->waitToBeVisibleInSolr();
    }

    /**
     * Adds a page to the queue (into DB table tx_solr_indexqueue_item) so it can
     * be fetched via a frontend subrequest
     */
    protected function addPageToIndexQueue(int $pageId, Site $site): Item
    {
        $queueItem = [
            'root' => $site->getRootPageId(),
            'item_type' => 'pages',
            'item_uid' => $pageId,
            'indexing_configuration' => 'pages',
        ];
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');
        // Check if item (type + Page ID) is already in index, if so update it
        $row = $connection->select(['*'], 'tx_solr_indexqueue_item', $queueItem)->fetchAssociative();
        if (is_array($row)) {
            $connection->update('tx_solr_indexqueue_item', $queueItem + ['errors' => ''], ['uid' => $row['uid']]);
            $queueItem['uid'] = $row['uid'];
        } else {
            $connection->insert('tx_solr_indexqueue_item', $queueItem + ['errors' => '']);
            $queueItem['uid'] = (int)$connection->lastInsertId();
            $queueItem = $connection->select(['*'], 'tx_solr_indexqueue_item', ['uid' => $queueItem['uid']])->fetchAssociative();
        }
        return new Item($queueItem);
    }

    /**
     * Returns the Item for given index queue uid
     */
    protected function getIndexQueueItem(int $itemUid): Item
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_solr_indexqueue_item');
        $itemData = $connection->select(['*'], 'tx_solr_indexqueue_item', ['uid' => $itemUid])->fetchAssociative();
        return new Item($itemData);
    }

    /**
     * Executes a Frontend request within the same PHP process to trigger the indexing of a page.
     */
    protected function executePageIndexer(string $url, Item $item, int $frontendUserId = null): ResponseInterface
    {
        $request = new InternalRequest($url);
        $requestContext = null;

        // Now add the headers for item to the request
        $indexerRequest = GeneralUtility::makeInstance(PageIndexerRequest::class);
        $indexerRequest->setIndexQueueItem($item);
        $accessRootline = Rootline::getAccessRootlineByPageId($item->getRecordUid());
        $indexerRequest->setParameter('accessRootline', (string)$accessRootline);
        $indexerRequest->setParameter('item', $item->getIndexQueueUid());
        $indexerRequest->addAction('indexPage');
        $headers = $indexerRequest->getHeaders();

        foreach ($headers as $header) {
            [$headerName, $headerValue] = GeneralUtility::trimExplode(':', $header, true, 2);
            $request = $request->withAddedHeader($headerName, $headerValue);
        }
        if ($frontendUserId !== null) {
            $requestContext = (new InternalRequestContext())->withFrontendUserId($frontendUserId);
        }
        $response = $this->executeFrontendSubRequest($request, $requestContext);
        $response->getBody()->rewind();
        return $response;
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
