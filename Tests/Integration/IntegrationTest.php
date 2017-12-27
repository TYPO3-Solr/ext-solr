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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageGenerator;
use TYPO3\CMS\Frontend\Utility\EidUtility;
use TYPO3\CMS\Install\Service\SqlExpectedSchemaService;
use TYPO3\CMS\Install\Service\SqlSchemaMigrationService;


/**
 * Base class for all integration tests in the EXT:solr project
 *
 * @author Timo Schmidt
 */
abstract class IntegrationTest extends FunctionalTestCase
{

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface The object manager
     */
    protected $objectManager;

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
     * @return void
     */
    public function setUp()
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        parent::setUp();

        //this is needed by the TYPO3 core.
        chdir(PATH_site);
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
        if(!Util::getIsTYPO3VersionBelow9())
        {
            $overlayPostFix = '.v9';
            $dotInFileName = strrpos($fixtureName,'.');
            $fileName = substr($fixtureName, 0, $dotInFileName);
            $fileExtension = substr($fixtureName, $dotInFileName);
            $overlayName = $fileName.$overlayPostFix.$fileExtension;


            if(file_exists($this->getFixtureRootPath() . $overlayName)) {
                return $this->getFixtureRootPath() . $overlayName;
            }
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


        if(!class_exists('TYPO3\\CMS\\Core\\Database\\Schema\\SchemaMigrator')) {
            // @todo this can be removed when we drop 8 LTS support
            // @deprecated
            /** @var $schemaMigrationService SqlSchemaMigrationService */
            $schemaMigrationService = $objectManager->get(SqlSchemaMigrationService::class);

            /** @var  $expectedSchemaService SqlExpectedSchemaService */
            $expectedSchemaService = $objectManager->get(SqlExpectedSchemaService::class);

            $expectedSchemaString = $expectedSchemaService->getTablesDefinitionString(true);
            $statements = $schemaMigrationService->getStatementArray($expectedSchemaString, true);
            list($_, $insertCount) = $schemaMigrationService->getCreateTables($statements, true);

            $fieldDefinitionsFile = $schemaMigrationService->getFieldDefinitions_fileContent($this->getFixtureContentByName($fixtureName));
            $fieldDefinitionsDatabase = $schemaMigrationService->getFieldDefinitions_database();
            $difference = $schemaMigrationService->getDatabaseExtra($fieldDefinitionsFile, $fieldDefinitionsDatabase);
            $updateStatements = $schemaMigrationService->getUpdateSuggestions($difference);

            $schemaMigrationService->performUpdateQueries($updateStatements['add'], $updateStatements['add']);
            $schemaMigrationService->performUpdateQueries($updateStatements['change'], $updateStatements['change']);
            $schemaMigrationService->performUpdateQueries($updateStatements['create_table'], $updateStatements['create_table']);

            $connection = $this->getDatabaseConnection();
            foreach ($insertCount as $table => $count) {
                $insertStatements = $schemaMigrationService->getTableInsertStatements($statements, $table);
                foreach ($insertStatements as $insertQuery) {
                    $insertQuery = rtrim($insertQuery, ';');
                    $connection->exec($insertQuery);
                }
            }
        } else {
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
        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class,
            $TYPO3_CONF_VARS, $id, $type, $no_cache, $cHash, $_2, $MP, $RDCT);


        EidUtility::initLanguage();

        $TSFE->id = $id;
        $TSFE->initFEuser();
        $TSFE->set_no_cache();
        $TSFE->checkAlternativeIdMethods();
        $TSFE->clear_preview();
        $TSFE->determineId();
        $TSFE->initTemplate();
        $TSFE->getConfigArray();
        $TSFE->config = array_merge($TSFE->config, $config);

        Bootstrap::getInstance();

        // only needed for FrontendGroupRestriction.php
        $GLOBALS['TSFE']->gr_list =  $TSFE->gr_list;
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        return $TSFE;
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
        $GLOBALS['TSFE']->gr_list = implode(',', $feUserGroupArray);
    }
}
