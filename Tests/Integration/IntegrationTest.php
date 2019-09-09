<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@dkd.de>
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

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Cache\CacheManager;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageGenerator;
use TYPO3\CMS\Install\Service\SqlExpectedSchemaService;
use TYPO3\CMS\Install\Service\SqlSchemaMigrationService;
use TYPO3\CMS\Core\Tests\Functional\SiteHandling\SiteBasedTestTrait;

/**
 * Base class for all integration tests in the EXT:solr project
 *
 * @author Timo Schmidt
 */
abstract class IntegrationTest extends FunctionalTestCase
{

    use SiteBasedTestTrait;

    /**
     * @var array
     */
    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
        'DE' => ['id' => 1, 'title' => 'German', 'locale' => 'de_DE.UTF8'],
        'DA' => ['id' => 2, 'title' => 'Danish', 'locale' => 'da_DA.UTF8']
    ];

    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3conf/ext/solr'
    ];

    /**
     * @var array
     */
    protected $testSolrCores = [
        'core_en',
        'core_de',
        'core_dk'
    ];

    /**
     * @var array
     */
    protected $configurationToUseInTestInstance = [
       'SYS' =>  [
           'exceptionalErrors' =>  E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED
       ]
    ];

    /**
     * @var string
     */
    protected $instancePath;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        //this is needed by the TYPO3 core.
        chdir(Environment::getPublicPath() . '/');

        // during the tests we don't want the core to cache something in cache_core
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
        $coreCache = $cacheManager->getCache('cache_core');
        $coreCache->flush();

        $this->instancePath = $this->getInstancePath();

        $this->failWhenSolrDeprecationIsCreated();
    }

    /**
     * Loads a Fixture from the Fixtures folder beside the current test case.
     *
     * @param $fixtureName
     * @throws \TYPO3\CMS\Core\Tests\Exception
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
    protected function getFixtureRootPath()
    {
        return $this->getRuntimeDirectory() . '/Fixtures/';
    }

    /**
     * Returns the absolute path to a fixture file.
     *
     * @param $fixtureName
     * @return string
     */
    protected function getFixturePathByName($fixtureName)
    {
        $overlayPostFix = '.v9';
        $dotInFileName = strrpos($fixtureName,'.');
        $fileName = substr($fixtureName, 0, $dotInFileName);
        $fileExtension = substr($fixtureName, $dotInFileName);
        $overlayName = $fileName.$overlayPostFix.$fileExtension;


        if(file_exists($this->getFixtureRootPath() . $overlayName)) {
            return $this->getFixtureRootPath() . $overlayName;
        }

        return $this->getFixtureRootPath() . $fixtureName;
    }

    /**
     * Returns the content of a fixture file.
     *
     * @param string $fixtureName
     * @return string
     */
    protected function getFixtureContentByName($fixtureName)
    {
        return file_get_contents($this->getFixturePathByName($fixtureName));
    }

    /**
     * @param string $fixtureName
     */
    protected function importDumpFromFixture($fixtureName)
    {
        $dumpContent = $this->getFixtureContentByName($fixtureName);
        $dumpContent = str_replace(["\r", "\n"], '', $dumpContent);
        $queries = GeneralUtility::trimExplode(';', $dumpContent, true);

        $connection = $this->getDatabaseConnection();
        foreach ($queries as $query) {
            $connection->exec($query);
        }
    }

    /**
     * Imports an ext_tables.sql definition as done by the install tool.
     *
     * @param string $fixtureName
     */
    protected function importExtTablesDefinition($fixtureName)
    {
        // create fake extension database table and TCA
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $schemaMigrationService = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\Schema\\SchemaMigrator');
        $sqlReader = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\Schema\\SqlReader');
        $sqlCode = $this->getFixtureContentByName($fixtureName);

        $createTableStatements = $sqlReader->getCreateTableStatementArray($sqlCode);

        $updateResult = $schemaMigrationService->install($createTableStatements);
        $failedStatements = array_filter($updateResult);
        $result = array();
        foreach ($failedStatements as $query => $error) {
            $result[] = 'Query "' . $query . '" returned "' . $error . '"';
        }

        if (!empty($result)) {
            throw new \RuntimeException(implode("\n", $result), 1505058450);
        }

        $insertStatements = $sqlReader->getInsertStatementArray($sqlCode);
        $schemaMigrationService->importStaticData($insertStatements);
    }

    /**
     * Returns the directory on runtime.
     *
     * @return string
     */
    protected function getRuntimeDirectory()
    {
        $rc = new \ReflectionClass(get_class($this));
        return dirname($rc->getFileName());
    }

    /**
     * @param string $version
     */
    protected function skipInVersionBelow($version)
    {
        if ($this->getIsTYPO3VersionBelow($version)) {
            $this->markTestSkipped('This test requires TYPO3 ' . $version . ' or greater.');
        }
    }

    /**
     * @param string $version
     * @return mixed
     */
    protected function getIsTYPO3VersionBelow($version)
    {
        return version_compare(TYPO3_branch, $version, '<');
    }

    /**
     * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected function getConfiguredTSFE($TYPO3_CONF_VARS = [], $id = 1, $type = 0, $no_cache = '', $cHash = '', $_2 = null, $MP = '', $RDCT = '', $config = [])
    {
            /** @var TSFETestBootstrapper $bootstrapper */
        $bootstrapper = GeneralUtility::makeInstance(TSFETestBootstrapper::class);
        $result = $bootstrapper->run($TYPO3_CONF_VARS, $id, $type, $no_cache, $cHash, $_2, $MP, $RDCT, $config);
        return $result->getTsfe();
    }

    /**
     * @param string $coreName
     * @return void
     */
    protected function cleanUpSolrServerAndAssertEmpty($coreName = 'core_en')
    {
        $this->validateTestCoreName($coreName);

        // cleanup the solr server
        $result = file_get_contents('http://localhost:8999/solr/' . $coreName . '/update?stream.body=<delete><query>*:*</query></delete>&commit=true');
        if (strpos($result, '<int name="QTime">') == false) {
            $this->fail('Could not empty solr test index');
        }

        // we wait to make sure the document will be deleted in solr
        $this->waitToBeVisibleInSolr();

        $this->assertSolrIsEmpty();
    }

    /**
     * @param string $coreName
     * @return void
     */
    protected function waitToBeVisibleInSolr($coreName = 'core_en')
    {
        $this->validateTestCoreName($coreName);
        $url = 'http://localhost:8999/solr/' . $coreName . '/update?softCommit=true';
        get_headers($url);
    }

    /**
     * @param string $coreName
     * @throws \InvalidArgumentException
     */
    protected function validateTestCoreName($coreName)
    {
        if(!in_array($coreName, $this->testSolrCores)) {
            throw new \InvalidArgumentException('No valid testcore passed');
        }
    }

    /**
     * Assertion to check if the solr server is empty.
     *
     * @return void
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
    protected function assertSolrContainsDocumentCount($documentCount)
    {
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":' . intval($documentCount), $solrContent, 'Solr contains unexpected amount of documents');
    }

    /**
     * @param string $fixture
     * @param array $importPageIds
     * @param array $feUserGroupArray
     */
    protected function indexPageIdsFromFixture($fixture, $importPageIds, $feUserGroupArray = [0])
    {
        $this->importDataSetFromFixture($fixture);
        $this->indexPageIds($importPageIds, $feUserGroupArray);
        $this->fakeBEUser();
    }

    /**
     * @param array $importPageIds
     * @param array $feUserGroupArray
     */
    protected function indexPageIds($importPageIds, $feUserGroupArray = [0])
    {
        foreach ($importPageIds as $importPageId) {
            $fakeTSFE = $this->fakeTSFE($importPageId, $feUserGroupArray);

            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
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
    protected function fakeBEUser($isAdmin = 0, $workspace = 0) {
        /** @var $beUser  BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $beUser->user['admin'] = $isAdmin;
        $beUser->workspace = $workspace;
        $GLOBALS['BE_USER'] = $beUser;

        return $beUser;
    }

    /**
     * @param integer $pageId
     * @param array $feUserGroupArray
     * @return TypoScriptFrontendController
     */
    protected function fakeTSFE($pageId, $feUserGroupArray = [0])
    {
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
        $_SERVER['HTTP_HOST'] = 'test.local.typo3.org';
        $_SERVER['REQUEST_URI'] = '/search.html';

        $fakeTSFE = $this->getConfiguredTSFE([], $pageId);
        $fakeTSFE->newCObj();

        $GLOBALS['TSFE'] = $fakeTSFE;
        $this->simulateFrontedUserGroups($feUserGroupArray);

        $fakeTSFE->preparePageContentGeneration();
        PageGenerator::renderContent();
        return $fakeTSFE;
    }

    /**
     * @param array $feUserGroupArray
     */
    protected function simulateFrontedUserGroups(array $feUserGroupArray)
    {
        /** @var  $context \TYPO3\CMS\Core\Context\Context::class */
        $context = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class);
        $userAspect = $this->getMockBuilder(\TYPO3\CMS\Core\Context\UserAspect::class)->setMethods([])->getMock();
        $userAspect->expects($this->any())->method('get')->willReturnCallback(function($key) use($feUserGroupArray){
            if ($key === 'groupIds') {
                return $feUserGroupArray;
            }

            if ($key === 'isLoggedIn') {
                return true;
            }
        });
        $userAspect->expects($this->any())->method('getGroupIds')->willReturn($feUserGroupArray);
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
     * @return \TYPO3\CMS\Core\DataHandling\DataHandler
     */
    protected function getDataHandler()
    {
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    /**
     * @return void
     */
    protected function writeDefaultSolrTestSiteConfiguration() {
        $this->writeDefaultSolrTestSiteConfigurationForHostAndPort();
    }

    /**
     * @return void
     */
    protected function writeDefaultSolrTestSiteConfigurationForHostAndPort($host = 'localhost', $port = 8999)
    {
        $defaultLanguage = $this->buildDefaultLanguageConfiguration('EN', '/en/');
        $defaultLanguage['solr_core_read'] = 'core_en';

        $german = $this->buildLanguageConfiguration('DE', '/de/');
        $german['solr_core_read'] = 'core_de';

        $danish = $this->buildLanguageConfiguration('DA', '/da/');
        $danish['solr_core_read'] = 'core_da';

        $this->writeSiteConfiguration(
            'integration_tree_one',
            $this->buildSiteConfiguration(1, 'http://testone.site/'),
            [
                $defaultLanguage, $german, $danish
            ],
            [
                $this->buildErrorHandlingConfiguration('Fluid', [404])
            ]
        );

        $this->writeSiteConfiguration(
            'integration_tree_two',
            $this->buildSiteConfiguration(111, 'http://testtwo.site/'),
            [
                $defaultLanguage, $german, $danish
            ],
            [
                $this->buildErrorHandlingConfiguration('Fluid', [404])
            ]
        );

        $globalSolrSettings = [
            'solr_scheme_read' => 'http',
            'solr_host_read' => $host,
            'solr_port_read' => $port,
            'solr_timeout_read' => 20,
            'solr_path_read' => '/solr/',
            'solr_use_write_connection' => false,
        ];
        $this->mergeSiteConfiguration('integration_tree_one', $globalSolrSettings);
        $this->mergeSiteConfiguration('integration_tree_two', $globalSolrSettings);

        clearstatcache();
        usleep(500);
    }

    /**
     * This method registers an error handler that fails the testcase when a E_USER_DEPRECATED error
     * is thrown with the prefix solr:deprecation
     *
     * @return void
     */
    protected function failWhenSolrDeprecationIsCreated(): void
    {
        error_reporting(error_reporting() & ~E_USER_DEPRECATED);
        set_error_handler(function ($id, $msg) {
            if ($id === E_USER_DEPRECATED && strpos($msg, 'solr:deprecation: ') === 0) {
                $this->fail("Executed deprecated EXT:solr code: " . $msg);
            }
        });
    }
}
