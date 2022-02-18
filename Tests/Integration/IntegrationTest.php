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
use ApacheSolrForTypo3\Solr\Tests\Unit\Helper\FakeObjectManager;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Doctrine\DBAL\Schema\SchemaException;
use function getenv;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use RuntimeException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Schema\Exception\StatementException;
use TYPO3\CMS\Core\Database\Schema\Exception\UnexpectedSignalReturnValueTypeException;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Http\RequestHandler;
use TYPO3\TestingFramework\Core\Exception as TestingFrameworkCoreException;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for all integration tests in the EXT:solr project
 *
 * @author Timo Schmidt
 */
abstract class IntegrationTest extends FunctionalTestCase
{
    use SiteBasedTestTrait;

    protected $coreExtensionsToLoad = [
        'scheduler',
        'fluid_styled_content',
    ];

    /**
     * @var array
     */
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
        'DE' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF8', 'fallbackType' => 'fallback', 'fallbacks' => 'EN'],
        'DA' => ['id' => 2, 'title' => 'Danish', 'locale' => 'da_DA.UTF8'],
    ];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/solr',
    ];

    /**
     * @var array
     */
    protected array $testSolrCores = [
        'core_en',
        'core_de',
        'core_dk',
    ];

    /**
     * @var array
     */
    protected $configurationToUseInTestInstance = [
       'SYS' =>  [
           'exceptionalErrors' =>  E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
       ],
    ];

    /**
     * @var string
     */
    protected $instancePath;

    /**
     * If set to true in subclasses, the import of configured root pages will be skipped.
     *
     * @var bool
     */
    protected bool $skipImportRootPagesAndTemplatesForConfiguredSites = false;

    /**
     * @throws NoSuchCacheException
     * @throws DBALException
     */
    protected function setUp(): void
    {
        parent::setUp();

        //this is needed by the TYPO3 core.
        chdir(Environment::getPublicPath() . '/');

        // during the tests we don't want the core to cache something in cache_core
        /* @var CacheManager $cacheManager */
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $coreCache = $cacheManager->getCache('core');
        $coreCache->flush();

        $this->instancePath = $this->getInstancePath();

        $this->failWhenSolrDeprecationIsCreated();
    }

    /**
     * Loads a Fixture from the Fixtures folder beside the current test case.
     *
     * @param $fixtureName
     * @throws TestingFrameworkCoreException
     */
    protected function importDataSetFromFixture($fixtureName)
    {
        $this->importDataSet($this->getFixturePathByName($fixtureName));
    }

    /**
     * Returns the absolute root path to the fixtures.
     *
     * @return string
     */
    protected function getFixtureRootPath(): string
    {
        return $this->getRuntimeDirectory() . '/Fixtures/';
    }

    /**
     * Returns the absolute path to a fixture file.
     *
     * @param $fixtureName
     * @return string
     */
    protected function getFixturePathByName($fixtureName): string
    {
        return $this->getFixtureRootPath() . $fixtureName;
    }

    /**
     * Returns the content of a fixture file.
     *
     * @param string $fixtureName
     * @return string
     */
    protected function getFixtureContentByName(string $fixtureName): string
    {
        return file_get_contents($this->getFixturePathByName($fixtureName));
    }

    /**
     * Imports an ext_tables.sql definition as done by the install tool.
     *
     * @param string $fixtureName
     * @throws DoctrineDBALException
     * @throws SchemaException
     * @throws StatementException
     * @throws UnexpectedSignalReturnValueTypeException
     */
    protected function importExtTablesDefinition(string $fixtureName)
    {
        // create fake extension database table and TCA

        $schemaMigrationService = GeneralUtility::makeInstance(SchemaMigrator::class);
        $sqlReader = GeneralUtility::makeInstance(SqlReader::class);
        $sqlCode = $this->getFixtureContentByName($fixtureName);

        $createTableStatements = $sqlReader->getCreateTableStatementArray($sqlCode);

        $updateResult = $schemaMigrationService->install($createTableStatements);
        $failedStatements = array_filter($updateResult);
        $result = [];
        foreach ($failedStatements as $query => $error) {
            $result[] = 'Query "' . $query . '" returned "' . $error . '"';
        }

        if (!empty($result)) {
            throw new RuntimeException(implode("\n", $result), 1505058450);
        }

        $insertStatements = $sqlReader->getInsertStatementArray($sqlCode);
        $schemaMigrationService->importStaticData($insertStatements);
    }

    /**
     * Returns the directory on runtime.
     *
     * @return string
     */
    protected function getRuntimeDirectory(): string
    {
        $rc = new ReflectionClass(get_class($this));
        return dirname($rc->getFileName());
    }

    /**
     * @param string $version
     * @return bool
     */
    protected function getIsTYPO3VersionBelow(string $version): bool
    {
        return (bool)version_compare(GeneralUtility::makeInstance(Typo3Version::class)->getBranch(), $version, '<');
    }

    /**
     * @param int $id
     * @param string $MP
     * @param int $language
     * @return TypoScriptFrontendController
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     *
     * @deprecated Do not try to set up and configure TSFE in any way by self.
     */
    protected function getConfiguredTSFE(int $id = 1, string $MP = '', int $language = 0): TypoScriptFrontendController
    {
        /* @var TSFETestBootstrapper $bootstrapper */
        $bootstrapper = GeneralUtility::makeInstance(TSFETestBootstrapper::class);

        $result = $bootstrapper->bootstrap($id, $MP, $language);
        return $result->getTsfe();
    }

    /**
     * @param string|null $coreName
     */
    protected function cleanUpSolrServerAndAssertEmpty(?string $coreName = 'core_en')
    {
        $this->validateTestCoreName($coreName);

        // cleanup the solr server
        $result = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/' . $coreName . '/update?stream.body=<delete><query>*:*</query></delete>&commit=true');
        if (strpos($result, '<int name="QTime">') == false) {
            self::fail('Could not empty solr test index');
        }

        // we wait to make sure the document will be deleted in solr
        $this->waitToBeVisibleInSolr();

        $this->assertSolrIsEmpty();
    }

    /**
     * @param string|null $coreName
     */
    protected function waitToBeVisibleInSolr(?string $coreName = 'core_en')
    {
        $this->validateTestCoreName($coreName);
        $url = $this->getSolrConnectionUriAuthority() . '/solr/' . $coreName . '/update?softCommit=true';
        get_headers($url);
    }

    /**
     * @param string $coreName
     * @throws InvalidArgumentException
     */
    protected function validateTestCoreName(string $coreName)
    {
        if (!in_array($coreName, $this->testSolrCores)) {
            throw new InvalidArgumentException('No valid test core passed');
        }
    }

    /**
     * Assertion to check if the solr server is empty.
     */
    protected function assertSolrIsEmpty()
    {
        $this->assertSolrContainsDocumentCount(0);
    }

    /**
     * Assertion to check if the solr server contains an expected count of documents.
     *
     * @param int $documentCount
     */
    protected function assertSolrContainsDocumentCount(int $documentCount)
    {
        $solrContent = file_get_contents($this->getSolrConnectionUriAuthority() . '/solr/core_en/select?q=*:*');
        self::assertStringContainsString('"numFound":' . (int)$documentCount, $solrContent, 'Solr contains unexpected amount of documents');
    }

    /**
     * @param string $fixture
     * @param array $importPageIds
     * @param array|null $feUserGroupArray
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    protected function indexPageIdsFromFixture(string $fixture, array $importPageIds, array $feUserGroupArray = [0])
    {
        $this->importDataSetFromFixture($fixture);
        $this->indexPageIds($importPageIds, $feUserGroupArray);
        $this->fakeBEUser();
    }

    /**
     * @param array $importPageIds
     * @param array|null $feUserGroupArray
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    protected function indexPageIds(array $importPageIds, array $feUserGroupArray = [0])
    {
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $this->fakeTSFE($importPageId, $feUserGroupArray);

            /** @var $pageIndexer Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance(Typo3PageIndexer::class, $fakeTSFE);
            $pageIndexer->setPageAccessRootline(Rootline::getAccessRootlineByPageId($importPageId));
            $pageIndexer->indexPage();
        }

        // reset to group 0
        $this->simulateFrontedUserGroups([0]);
    }

    /**
     * @param int $isAdmin
     * @param int $workspace
     * @return BackendUserAuthentication
     */
    protected function fakeBEUser(int $isAdmin = 0, int $workspace = 0): BackendUserAuthentication
    {
        /** @var $beUser  BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $beUser->user['admin'] = $isAdmin;
        $beUser->workspace = $workspace;
        $GLOBALS['BE_USER'] = $beUser;

        return $beUser;
    }

    /**
     * @param int $pageId
     * @param array $feUserGroupArray
     * @return TypoScriptFrontendController
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     *
     * @deprecated Do not try to set up and configure TSFE in any way by self.
     */
    protected function fakeTSFE(int $pageId, array $feUserGroupArray = [0]): TypoScriptFrontendController
    {
        $GLOBALS['TT'] = $this->createMock(TimeTracker::class);
        $_SERVER['HTTP_HOST'] = 'test.local.typo3.org';
        $_SERVER['REQUEST_URI'] = '/search.html';

        $fakeTSFE = $this->getConfiguredTSFE($pageId);
        $fakeTSFE->newCObj();

        $GLOBALS['TSFE'] = $fakeTSFE;
        $this->simulateFrontedUserGroups($feUserGroupArray);

        $request = $GLOBALS['TYPO3_REQUEST'];
        $requestHandler = GeneralUtility::makeInstance(RequestHandler::class);
        $requestHandler->handle($request);

        return $fakeTSFE;
    }

    /**
     * @param array $feUserGroupArray
     */
    protected function simulateFrontedUserGroups(array $feUserGroupArray)
    {
        /** @var  $context Context::class */
        $context = GeneralUtility::makeInstance(Context::class);
        $userAspect = $this->getMockBuilder(UserAspect::class)
            ->onlyMethods([
                'get',
                'getGroupIds',
            ])->getMock();
        $userAspect->expects(self::any())->method('get')->willReturnCallback(function ($key) use ($feUserGroupArray) {
            if ($key === 'groupIds') {
                return $feUserGroupArray;
            }

            if ($key === 'isLoggedIn') {
                return true;
            }

            /* @var UserAspect $originalUserAspect */
            $originalUserAspect = GeneralUtility::makeInstance(UserAspect::class);
            return $originalUserAspect->get($key);
        });
        $userAspect->expects(self::any())->method('getGroupIds')->willReturn($feUserGroupArray);
        /* @var UserAspect $userAspect */
        $context->setAspect('frontend.user', $userAspect);
    }

    /**
     * Applies in CMS 9.2 introduced error handling.
     */
    protected function applyUsingErrorControllerForCMS9andAbove()
    {
        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest();
    }

    /**
     * Returns the data handler
     *
     * @return DataHandler
     */
    protected function getDataHandler(): DataHandler
    {
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
        /* @retrun  DataHandler */
        return GeneralUtility::makeInstance(DataHandler::class);
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
     *
     * @throws TestingFrameworkCoreException
     */
    protected function writeDefaultSolrTestSiteConfiguration()
    {
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort($solrConnectionInfo['scheme'], $solrConnectionInfo['host'], $solrConnectionInfo['port']);
    }

    /**
     * @var string
     */
    protected static $lastSiteCreated = '';

    /**
     * @param string|null $scheme
     * @param string|null $host
     * @param int|null $port
     * @param bool|null $disableDefaultLanguage
     * @throws TestingFrameworkCoreException
     */
    protected function writeDefaultSolrTestSiteConfigurationForHostAndPort(
        ?string $scheme = 'http',
        ?string $host = 'localhost',
        ?int $port = 8999,
        ?bool $disableDefaultLanguage = false
    ) {
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
            'solr_path_read' => '/solr/',
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
     *
     * @throws TestingFrameworkCoreException
     */
    private function importRootPagesAndTemplatesForConfiguredSites(): void
    {
        if ($this->skipImportRootPagesAndTemplatesForConfiguredSites === true) {
            return;
        }
        $rc = new ReflectionClass(self::class);
        $path = dirname($rc->getFileName());
        $this->importDataSet($path . '/Fixtures/sites_setup_and_data_set/01_integration_tree_one.xml');
        $this->importDataSet($path . '/Fixtures/sites_setup_and_data_set/02_integration_tree_two.xml');
        $this->importDataSet($path . '/Fixtures/sites_setup_and_data_set/03_integration_tree_three.xml');
    }

    /**
     * This method registers an error handler that fails the testcase when a E_USER_DEPRECATED error
     * is thrown with the prefix solr:deprecation
     */
    protected function failWhenSolrDeprecationIsCreated(): void
    {
        error_reporting(error_reporting() & ~E_USER_DEPRECATED);
        set_error_handler(function ($id, $msg) {
            if ($id === E_USER_DEPRECATED && strpos($msg, 'solr:deprecation: ') === 0) {
                $this->fail('Executed deprecated EXT:solr code: ' . $msg);
            }
        });
    }

    protected function getSolrConnectionInfo(): array
    {
        return [
            'scheme' => getenv('TESTING_SOLR_SCHEME') ?: 'http',
            'host' => getenv('TESTING_SOLR_HOST') ?: 'localhost',
            'port' => getenv('TESTING_SOLR_PORT') ?: 8999,
        ];
    }

    /**
     * Returns solr connection URI authority as string as
     * scheme://host:port
     *
     * @return string
     */
    protected function getSolrConnectionUriAuthority(): string
    {
        $solrConnectionInfo = $this->getSolrConnectionInfo();
        return $solrConnectionInfo['scheme'] . '://' . $solrConnectionInfo['host'] . ':' . $solrConnectionInfo['port'];
    }

    /**
     * @return ObjectManagerInterface
     */
    protected function getFakeObjectManager(): ObjectManagerInterface
    {
        return new FakeObjectManager();
    }

    /**
     * Returns inaccessible(private/protected/etc.) property from given object.
     *
     * @param object $object
     * @param string $property
     * @return ?mixed
     */
    protected function getInaccessiblePropertyFromObject(object $object, string $property)
    {
        $reflection = new ReflectionClass($object);
        try {
            $property = $reflection->getProperty($property);
        } catch (ReflectionException $e) {
            return null;
        }
        $property->setAccessible(true);
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
     * @return mixed
     * @throws ReflectionException
     */
    protected function callInaccessibleMethod($object, $name)
    {
        // Remove first two arguments ($object and $name)
        $arguments = func_get_args();
        array_splice($arguments, 0, 2);

        $reflectionObject = new ReflectionObject($object);
        $reflectionMethod = $reflectionObject->getMethod($name);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $arguments);
    }

    /**
     * Adds TypoScript setup snippet to the existing template record
     *
     * @param int $pageId
     * @param string $constants
     * @throws DBALDriverException
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
}
