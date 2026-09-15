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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Security;

use ApacheSolrForTypo3\Solr\Controller\SearchController;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\Tests\Integration\Controller\AbstractFrontendControllerTest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager as ExtbaseConfigurationManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Service\EnvironmentService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Regression guard for SST #2026050810000025:
 * the highlighter must not surface `field:*` queries as a Solr HTTP 500 field-existence oracle.
 *
 * @group frontend
 * @group security
 */
class QueryInjectionViaRawSolrSyntaxTest extends AbstractFrontendControllerTest
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var SearchController
     */
    protected $searchController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
        $this->fakeSingletonsForFrontendContext();
        $GLOBALS['TT'] = $this->getMockBuilder(TimeTracker::class)->disableOriginalConstructor()->getMock();
        $this->fakeBEUser(1);

        $this->searchController = $this->objectManager->get(SearchController::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument'][PageFieldMappingIndexer::class] = PageFieldMappingIndexer::class;
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $this->importDataSetFromFixture('can_render_search_controller.xml');
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([1, 2, 3, 22, 23, 24]);
    }

    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    /**
     * @test
     * @group frontend
     */
    public function literalTokenSearchReturnsExpectedDocument()
    {
        $result = $this->executeSearch('prices');

        self::assertStringContainsString(
            'pages/3/0/0/0',
            $result,
            'Sanity check failed: literal-token search for "prices" did not return the expected document.'
        );
    }

    /**
     * Second control: a selector against a field that *is* in `qf` must keep working.
     * The literal-token control above never parses a `field:value` selector, so it cannot
     * tell "selector neutralised into a literal term" apart from "selectors stopped working" —
     * i.e. a `uf` whitelist set too narrowly. Green before and after the fix.
     *
     * @test
     */
    public function fieldSelectorOnWhitelistedFieldStillReturnsExpectedDocument(): void
    {
        $response = $this->executeSearch('title:markeralpha235567');
        self::assertStringContainsString(
            'markeralpha235567',
            $response,
            'Selector on the whitelisted field "title" no longer returns the alpha document — the uf whitelist is too narrow.'
        );
    }

    /**
     * PDF §3.1 — `siteHash:*` must not enumerate documents via field selector.
     *
     * @test
     */
    public function wildcardFieldEnumerationOperatorMustNotEnumerateIndexedDocuments(): void
    {
        $response = $this->executeSearch('siteHash:*');
        foreach (['markeralpha235567', 'markerbeta235567', 'markergamma235567'] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $response,
                'SST 235567 / PDF §3.1: siteHash:* field enumeration leaked ' . $marker
            );
        }
    }

    /**
     * Combined field-selector + range against the hex-hash siteHash field must not match the test corpus.
     *
     * @test
     */
    public function fieldSelectorOperatorMustNotTargetArbitraryIndexedField(): void
    {
        $response = $this->executeSearch('siteHash:[0 TO 9]');
        foreach (['markeralpha235567', 'markerbeta235567', 'markergamma235567'] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $response,
                'SST 235567: field-selector range query against siteHash leaked ' . $marker
            );
        }
    }

    /**
     * PDF §3.2 — prefix wildcard must not enable per-character value extraction.
     *
     * @test
     */
    public function prefixWildcardOperatorMustNotEnableBlindValueExtraction(): void
    {
        $response = $this->executeSearch('title:markeralpha*');
        self::assertStringNotContainsString(
            'markeralpha235567',
            $response,
            'SST 235567 / PDF §3.2: prefix-wildcard (title:markeralpha*) leaked the alpha document.'
        );
    }

    /**
     * PDF §3.3 — `?` wildcard must not enable length detection on indexed values.
     *
     * @test
     */
    public function singleCharWildcardOperatorMustNotEnableLengthDetection(): void
    {
        $response = $this->executeSearch('title:markeralpha?35567');
        self::assertStringNotContainsString(
            'markeralpha235567',
            $response,
            'SST 235567 / PDF §3.3: single-character wildcard (?) leaked the alpha document.'
        );
    }

    /**
     * PDF §3.4 — range query must not enable binary-search value extraction.
     *
     * @test
     */
    public function rangeQueryOperatorMustNotEnableBinarySearchExtraction(): void
    {
        $response = $this->executeSearch('title:[m TO n]');
        foreach (['markeralpha235567', 'markerbeta235567', 'markergamma235567'] as $marker) {
            self::assertStringNotContainsString(
                $marker,
                $response,
                'SST 235567 / PDF §3.4: range query (title:[m TO n]) leaked ' . $marker
            );
        }
    }

    /**
     * @test
     */
    public function existingFieldWildcardMustNotTriggerSolrCommunicationException()
    {
        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $configuration = new TypoScriptConfiguration([
            'plugin.' => ['tx_solr.' => ['search.' => [
                'query.' => ['queryFields' => 'content,title'],
                'results.' => [
                    'resultsHighlighting' => 1,
                    'resultsHighlighting.' => [
                        'fragmentSize' => 50,
                        'wrap' => '<mark>|</mark>',
                    ],
                ],
            ]]],
        ]);
        $query = (new QueryBuilder($configuration))->buildSearchQuery('siteHash:*');
        try {
            GeneralUtility::makeInstance(Search::class)->search($query);
        } catch (SolrCommunicationException $e) {
            self::fail('SST 235567: highlighter raised ' . get_class($e) . ' — ' . $e->getMessage());
        }
    }

    protected function executeSearch(string $rawQuery): string
    {
        $_GET['q'] = $rawQuery;
        $request = $this->getPreparedRequest();
        $response = $this->getPreparedResponse();

        $this->searchController->processRequest($request, $response);

        return $response->getContent();
    }

    /**
     * In this method we initialize a few singletons with mocked classes to be able to generate links
     * for the frontend in the testing context.
     */
    protected function fakeSingletonsForFrontendContext()
    {
        $environmentServiceMock = $this->getMockBuilder(EnvironmentService::class)->setMethods([])->disableOriginalConstructor()->getMock();
        $environmentServiceMock->expects(self::any())->method('isEnvironmentInFrontendMode')->willReturn(true);
        $environmentServiceMock->expects(self::any())->method('isEnvironmentInBackendMode')->willReturn(false);

        $configurationManagerMock = $this->getMockBuilder(ExtbaseConfigurationManager::class)->setMethods(['getContentObject'])
            ->setConstructorArgs([$this->objectManager, $environmentServiceMock])->getMock();

        $configurationManagerMock->expects(self::any())->method('getContentObject')->willReturn(GeneralUtility::makeInstance(ContentObjectRenderer::class));

        GeneralUtility::setSingletonInstance(EnvironmentService::class, $environmentServiceMock);
        GeneralUtility::setSingletonInstance(ExtbaseConfigurationManager::class, $configurationManagerMock);
    }
}
