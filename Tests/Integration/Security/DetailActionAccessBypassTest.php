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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Controller\SearchController;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteHashService;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageFieldMappingIndexer;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
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
 * Regression guard for SST #2026052010000011 (CVE-2026-56093): {@link SearchController::detailAction()}
 * must not disclose an access-restricted document to an anonymous visitor.
 *
 * `SearchResultSetService::getDocumentById()` built the by-id lookup query directly, without running
 * any search component, so `AccessComponent`'s `siteHash`/frontend-user-group filters never applied
 * to it — unlike the regular search path, where `initializeRegisteredSearchComponents()` applies them.
 *
 * 11.2 specifics: the restricted document is injected directly (like
 * {@see AdditionalFiltersSiteHashBypassTest}), not indexed via a real page render — this branch's
 * TSFE test bootstrap never establishes an actual frontend-user login, so a `fe_group`-restricted
 * page's own `determineId()` access check always falls back to the site root.
 *
 * @group frontend
 * @group security
 */
class DetailActionAccessBypassTest extends AbstractFrontendControllerTest
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

        $this->importDataSetFromFixture('detailaction_access_bypass.xml');
    }

    protected function tearDown(): void
    {
        $this->cleanUpSolrServerAndAssertEmpty();
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    /**
     * PRIMARY regression guard.
     *
     * @test
     */
    public function detailActionMustNotDiscloseAccessRestrictedDocumentToAnonymousVisitor()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([2]);
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(1);

        $restrictedDocumentId = $this->addRestrictedDocument();
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(2);

        $request = $this->getPreparedRequest();
        $response = $this->getPreparedResponse();
        $request->setControllerActionName('detail');
        $request->setArgument('documentId', $restrictedDocumentId);

        $this->searchController->processRequest($request, $response);
        $content = $response->getContent();

        self::assertStringNotContainsString(
            'RestrictedSecretAreaSST235573',
            $content,
            'CVE-2026-56093: detailAction disclosed the title of an access-restricted document to an anonymous visitor.'
        );
        self::assertStringNotContainsString(
            'classifiedbankdetailsmarker235573',
            $content,
            'CVE-2026-56093: detailAction disclosed the body of an access-restricted document to an anonymous visitor.'
        );
    }

    /**
     * COMPARISON BASELINE: the same restricted document is not disclosed via the normal (non-cacheable)
     * results action either, because AccessComponent already applies there. Confirms the primary test's
     * failure (pre-fix) is specific to the detailAction/getDocumentById() path, not a general indexing bug.
     *
     * @test
     */
    public function normalResultsActionDoesNotDiscloseAccessRestrictedDocumentToAnonymousVisitor()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        $GLOBALS['TSFE'] = $this->getConfiguredTSFE(1);
        $this->indexPages([2]);
        $this->waitToBeVisibleInSolr();
        $this->addRestrictedDocument();
        $this->waitToBeVisibleInSolr();
        $this->assertSolrContainsDocumentCount(2);

        $_GET['q'] = '*';
        $request = $this->getPreparedRequest();
        $response = $this->getPreparedResponse();
        $this->searchController->processRequest($request, $response);
        $content = $response->getContent();

        self::assertStringNotContainsString(
            'RestrictedSecretAreaSST235573',
            $content,
            'Normal resultsAction unexpectedly disclosed the title of the restricted document — the comparison baseline is invalid.'
        );
        self::assertStringNotContainsString(
            'classifiedbankdetailsmarker235573',
            $content,
            'Normal resultsAction unexpectedly disclosed a marker from the restricted document body — the comparison baseline is invalid.'
        );
    }

    /**
     * Injects a document with an `access` field requiring frontend user group 1, matching the
     * `<pageUid>:<feGroup>` format {@see \ApacheSolrForTypo3\Solr\Access\Rootline} produces for page 3
     * in the fixture (`fe_group=1`).
     */
    protected function addRestrictedDocument(): string
    {
        $siteHash = GeneralUtility::makeInstance(SiteHashService::class)->getSiteHashForDomain('testone.site');
        $documentId = $siteHash . '/pages/3/0/0/1';

        $document = GeneralUtility::makeInstance(Document::class);
        $document->setField('id', $documentId);
        $document->setField('appKey', 'EXT:solr');
        $document->setField('type', 'pages');
        $document->setField('uid', 3);
        $document->setField('pid', 1);
        $document->setField('site', 'testone.site');
        $document->setField('siteHash', $siteHash);
        $document->setField('access', '3:1');
        $document->setField('title', 'RestrictedSecretAreaSST235573');
        $document->setField('content', 'classifiedbankdetailsmarker235573 content that must never reach an anonymous visitor');
        $document->setField('url', 'http://testone.site/restricted');

        $connection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId(1, 0);
        $writeService = $connection->getWriteService();
        $writeService->addDocuments([$document]);
        $writeService->commit();

        return $documentId;
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
