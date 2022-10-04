<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller;

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

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\Controller\SuggestController;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\Response;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Integration testcase to test for the SuggestController
 *
 * @author Timo Hund
 * @copyright (c) 2018 Timo Hund <timo.hund@dkd.de>
 * @group frontend
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

        $this->suggestController = $this->objectManager->get(SuggestController::class);
        $this->suggestRequest = $this->getPreparedRequest('Suggest', 'suggest');
        $this->suggestResponse = $this->getPreparedResponse();

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
    }

    public function tearDown()
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }
    /**
     * @test
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

    /**
     * @test
     */
    public function canSuggestWithUriSpecialChars()
    {
        $this->importDataSetFromFixture('can_suggest_with_uri_special_chars.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 4]);

        // @todo: add more variants
        // @TODO: Check why does solr return some/larg instead of some/large
        $testCases = [
            [
                'prefix' => 'Some/',
                'expected' => 'suggestions":{"some/":1,"some/larg":1,"some/large/path":1}'
            ],
            [
                'prefix' => 'Some/Large',
                'expected' => 'suggestions":{"some/large/path":1}'
            ],
        ];
        foreach ($testCases as $testCase) {
            $this->expectSuggested($testCase['prefix'], $testCase['expected']);
        }
    }

    protected function expectSuggested(string $prefix, string $expected)
    {
        $this->suggestRequest->setArgument('queryString', $prefix);
        $this->suggestRequest->setArgument('callback', 'rand');

        $this->suggestController->processRequest($this->suggestRequest, $this->suggestResponse);
        $result = $this->suggestResponse->getContent();

        //we assume to get suggestions like Sweatshirt
        $this->assertContains($expected, $result, 'Response did not contain expected suggestions: ' . $expected);
    }
}
