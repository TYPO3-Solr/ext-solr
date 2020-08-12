<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2018 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\Typo3PageIndexer;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Controller\SuggestController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Response;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Fluid\View\Exception\InvalidTemplateResourceException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageGenerator;

/**
 * Integration testcase to test for the SuggestController
 *
 * @author Timo Hund
 */
class SuggestControllerTest extends AbstractFrontendControllerTest
{
    /**
     * @var ObjectManagerInterface The object manager
     */
    protected $objectManager;

    /**
     * @var SuggestController
     */
    protected $suggestController;

    /**
     * @var Request
     */
    protected $suggestRequest;

    /**
     * @var Response
     */
    protected $suggestResponse;

    public function setUp()
    {
        parent::setUp();

        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();

        /** @var  $searchController SearchController */
        $this->suggestController = $this->objectManager->get(SuggestController::class);
        $this->suggestRequest = $this->getPreparedRequest('Suggest', 'suggest');
        $this->suggestResponse = $this->getPreparedResponse();

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;

    }

    /**
     * @test
     * @group frontend
     */
    public function canDoABasicSuggest()
    {
        $this->importDataSetFromFixture('can_render_suggest_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 4, 5, 6, 7, 8]);

        $this->suggestRequest->setArgument('queryString', 'Sweat');
        $this->suggestRequest->setArgument('callback', 'rand');

        $this->suggestController->processRequest($this->suggestRequest, $this->suggestResponse);
        $result = $this->suggestResponse->getContent();

        //we assume to get suggestions like Sweatshirt
        $this->assertContains('suggestions":{"sweatshirts":2}', $result, 'Response did not contain sweatshirt suggestions');
    }
}