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
 *  the Free Software Foundation; either version 2 of the License, or
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

use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Tests\FunctionalTestCase as TYPO3IntegrationTest;
use TYPO3\CMS\Frontend\Page\PageGenerator;

/**
 * Base class for all integration tests in the EXT:solr project
 *
 * @author Timo Schmidt
 * @package TYPO3
 * @subpackage solr
 */
abstract class IntegrationTest extends TYPO3IntegrationTest
{

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface The object manager
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $testExtensionsToLoad = array('typo3conf/ext/solr');

    /**
     * @return void
     */
    public function setUp()
    {
        $this->objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');

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
        $this->importDataSet($this->getFixtureRootPath() . $fixtureName);
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
    protected function getFixturePath($fixtureName)
    {
        return $this->getFixtureRootPath() . $fixtureName;
    }

    /**
     * Returns the content of a fixture file.
     *
     * @param string $fixtureName
     * @return string
     */
    protected function getFixtureContent($fixtureName)
    {
        return file_get_contents($this->getFixturePath($fixtureName));
    }

    /**
     * @param string $fixtureName
     */
    protected function importDumpFromFixture($fixtureName)
    {
        /** @var $database  \TYPO3\CMS\Core\Database\DatabaseConnection */
        $database = $GLOBALS['TYPO3_DB'];
        $database->debugOutput = true;

        $dumpContent = $this->getFixtureContent($fixtureName);
        $dumpContent = str_replace(array("\r", "\n"), '', $dumpContent);

        $queries = GeneralUtility::trimExplode(';', $dumpContent, true);
        foreach ($queries as $query) {
            $database->sql_query($query);
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
        if (version_compare(TYPO3_branch, $version, '<')) {
            $this->markTestSkipped('This test requires TYPO3 ' . $version . ' or greater.');
        }
    }

    /**
     * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected function getConfiguredTSFE($TYPO3_CONF_VARS = array(), $id = 1, $type = 0)
    {
        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController',
            $TYPO3_CONF_VARS, $id, $type);

        \TYPO3\CMS\Frontend\Utility\EidUtility::initLanguage();
        $TSFE->initFEuser();
        $TSFE->set_no_cache();
        $TSFE->checkAlternativeIdMethods();
        $TSFE->determineId();
        $TSFE->initTemplate();
        $TSFE->getConfigArray();
        \TYPO3\CMS\Core\Core\Bootstrap::getInstance();
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        return $TSFE;
    }

    /**
     * @return void
     */
    protected function cleanUpSolrServerAndAssertEmpty()
    {
        // cleanup the solr server
        $result = file_get_contents("http://localhost:8080/solr/core_en/update?stream.body=<delete><query>*:*</query></delete>&commit=true");
        if (strpos($result, '<int name="QTime">') == false) {
            $this->fail('Could not empty solr test index');
        }

        // we wait to make sure the document will be deleted in solr
        sleep(1);

        $this->assertSolrIsEmpty();
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
        $solrContent = file_get_contents('http://localhost:8080/solr/core_en/select?q=*:*');
        $this->assertContains('"numFound":' . intval($documentCount), $solrContent, 'Solr contains unexpected amount of documents');
    }


    /**
     * @param string $fixture
     * @param array $importPageIds
     */
    protected function indexPageIdsFromFixture($fixture, $importPageIds)
    {
        $this->importDataSetFromFixture($fixture);

        foreach ($importPageIds as $importPageId) {
            $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
            $fakeTSFE = $this->getConfiguredTSFE(array(), $importPageId);
            $fakeTSFE->newCObj();

            $GLOBALS['TSFE'] = $fakeTSFE;

            PageGenerator::pagegenInit();
            PageGenerator::renderContent();

            /** @var $pageIndexer \ApacheSolrForTypo3\Solr\Typo3PageIndexer */
            $pageIndexer = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Typo3PageIndexer', $fakeTSFE);
            $pageIndexer->indexPage();
        }
        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance('TYPO3\CMS\Core\Authentication\BackendUserAuthentication');
        $GLOBALS['BE_USER'] = $beUser;
    }
}
