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

namespace ApacheSolrForTypo3\Solr\Tests\Unit;

use ApacheSolrForTypo3\Solr\Typo3PageContentExtractor;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\PageTitle\PageTitleProviderManager;
use TYPO3\CMS\Core\PageTitle\RecordPageTitleProvider;
use TYPO3\CMS\Core\PageTitle\RecordTitleProvider;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\CMS\Frontend\Page\PageParts;

/**
 * Tests that page title extraction does not leak title state between
 * in-process indexing sub-requests.
 *
 * The PageTitleProviderManager is a stateful singleton: during indexing many
 * pages are rendered within the same PHP process, and its per-provider cache
 * still holds the titles of the previously indexed page when the next page's
 * document is built.
 */
class Typo3PageContentExtractorPageTitleTest extends SetUpUnitTestCase
{
    protected PageTitleProviderManager $pageTitleProviderManager;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->set(RecordTitleProvider::class, new RecordTitleProvider());
        $container->set(RecordPageTitleProvider::class, new RecordPageTitleProvider());

        $this->pageTitleProviderManager = new PageTitleProviderManager(
            $container,
            new DependencyOrderingService(),
            new TypoScriptService(),
            new NullLogger(),
        );
        GeneralUtility::setSingletonInstance(PageTitleProviderManager::class, $this->pageTitleProviderManager);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function getPageTitleDoesNotLeakTitleStateOfPreviouslyIndexedPage(): void
    {
        // Stale cache entry left behind by the previously indexed page.
        $this->pageTitleProviderManager->setPageTitleCache([
            RecordTitleProvider::class => 'Title of the previously indexed page',
        ]);

        $GLOBALS['TYPO3_REQUEST'] = $this->buildRequest(new PageParts());

        $contentExtractor = GeneralUtility::makeInstance(
            Typo3PageContentExtractor::class,
            '<html><body>content</body></html>',
        );

        self::assertSame('Title of the current page', $contentExtractor->getPageTitle());
    }

    #[Test]
    public function getPageTitleUsesPersistedTitleStateOfCurrentPage(): void
    {
        // Stale cache entry left behind by the previously indexed page.
        $this->pageTitleProviderManager->setPageTitleCache([
            RecordTitleProvider::class => 'Title of the previously indexed page',
        ]);

        // Persisted title state of the current page, as restored from the
        // page's own cache row when it is served from the page cache.
        $pageParts = new PageParts();
        $pageParts->setPageTitle([
            RecordTitleProvider::class => 'Persisted title of the current page',
        ]);

        $GLOBALS['TYPO3_REQUEST'] = $this->buildRequest($pageParts);

        $contentExtractor = GeneralUtility::makeInstance(
            Typo3PageContentExtractor::class,
            '<html><body>content</body></html>',
        );

        self::assertSame('Persisted title of the current page', $contentExtractor->getPageTitle());
    }

    protected function buildRequest(PageParts $pageParts): ServerRequest
    {
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setConfigArray([
            'pageTitleProviders.' => [
                'recordTitle.' => [
                    'provider' => RecordTitleProvider::class,
                    'before' => 'record',
                ],
                'record.' => [
                    'provider' => RecordPageTitleProvider::class,
                ],
            ],
        ]);

        $pageInformation = new PageInformation();
        $pageInformation->setPageRecord([
            'uid' => 4711,
            'title' => 'Title of the current page',
        ]);

        return (new ServerRequest('https://example.com/'))
            ->withAttribute('frontend.typoscript', $frontendTypoScript)
            ->withAttribute('frontend.page.parts', $pageParts)
            ->withAttribute('frontend.page.information', $pageInformation);
    }
}
