<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\EventListener\Backend;

use ApacheSolrForTypo3\Solr\EventListener\Backend\SettingsPreviewOnPluginsEventListener;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\TypoScript\PageTsConfigFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EXT:solr offers a summary in the BE on search plugins, that summarizes the extension
 * configuration.
 * This testcase checks if the SummaryController produces the expected output.
 */
class SettingsPreviewOnPluginsEventListenerTest extends IntegrationTestBase
{
    private const FLEX_FORM_ARRAY = [
        'search' => [
            'targetPage' => '4711',
            'initializeWithEmptyQuery' => '0',
            'showResultsOfInitialEmptyQuery' => '0',
            'initializeWithQuery' => 'test',
            'showResultsOfInitialQuery' => '0',
            'query' => [
                'sortBy' => 'sorting',
                'boostFunction' => 'boostFunction',
                'boostQuery' => 'boostQuery',
                'filter' => [
                    '5947784b97b1e034174380' => [
                        'field' => [
                            'field' => 'appKey',
                            'value' => 'test',
                        ],
                    ],
                    '59477a29d19e9226739710' => [
                        'field' => [
                            'field' => 'changed',
                            'value' => '1',
                        ],
                    ],
                ],
            ],
            'results' => [
                'resultsPerPage' => '10',
            ],
        ],
        'view' => [
            'templateFiles' => [
                'results' => 'myTemplateFile.html',
            ],
        ],
    ];

    private LanguageService|MockObject $languageServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languageServiceMock = $this->createMock(LanguageService::class);
    }

    public static function invalidCTypeDataProvider(): array
    {
        return [
            'Test with CType "text"' => ['text'],
            'Test with CType "textmedia"' => ['textmedia'],
            'Test with CType "news_pi1"' => ['news_pi1'],
        ];
    }

    #[Test]
    #[DataProvider(
        methodName: 'invalidCTypeDataProvider',
    )]
    public function previewWithInvalidCTypeWillNotBeRendered(string $cType): void
    {
        $subject = new SettingsPreviewOnPluginsEventListener(
            $this->get(BackendViewFactory::class),
        );

        $event = $this->createFakeEvent(['CType' => $cType]);
        $subject->__invoke($event);

        self::assertNull(
            $event->getPreviewContent(),
        );
    }

    #[Test]
    public function previewWithValidCTypeWillRenderPreview(): void
    {
        // Needed to translate the label from TSConfig of RootLine
        $this->languageServiceMock
            ->expects(self::atLeastOnce())
            ->method('sL')
            ->with('LLL:EXT:solr:label')
            ->willReturn('Search (solr_pi_results)');

        $GLOBALS['LANG'] = $this->languageServiceMock;

        $rootLine = [
            [
                'uid' => 11,
                'TSconfig' => 'TCEFORM.tt_content.CType.addItems.solr_pi_results = LLL:EXT:solr:label',
            ],
        ];

        /** @var PageTsConfigFactory $subject */
        $subject = $this->get(PageTsConfigFactory::class);
        $pageTsConfig = $subject->create($rootLine, new NullSite());

        // Inject Page TSConfig to the runtime cache to prevent a DB query in BackendUtility
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('runtime');
        $cache->set('pageTsConfig-pid-to-hash-11', 'foo');
        $cache->set('pageTsConfig-hash-to-object-foo', $pageTsConfig);

        $subject = new SettingsPreviewOnPluginsEventListener(
            $this->get(BackendViewFactory::class),
        );

        $event = $this->createFakeEvent([
            'pid' => 11,
            'pi_flexform' => new FlexFormFieldValues(['sDEF' => self::FLEX_FORM_ARRAY]),
            'CType' => 'solr_pi_results',
            'hidden' => 0,
        ]);
        $subject->__invoke($event);

        $pluginPreviewContent = $event->getPreviewContent();

        self::assertStringContainsString(
            'Search (solr_pi_results)',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '[4711] ERROR: page is gone',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>test</td>',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>1</td>',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>sorting</td>',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>10</td>',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>boostFunction</td>',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>boostQuery</td>',
            $pluginPreviewContent,
        );
        self::assertStringContainsString(
            '<td>myTemplateFile.html</td>',
            $pluginPreviewContent,
        );

        self::assertStringNotContainsString(
            'Tie Breaker',
            $pluginPreviewContent,
            'Empty values should not be added',
        );
    }

    private function createFakeEvent(array $record): PageContentPreviewRenderingEvent
    {
        $recordMock = $this->createMock(RecordInterface::class);
        $recordMock
            ->expects(self::atLeastOnce())
            ->method('toArray')
            ->willReturn($record);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $pageLayoutContextMock = $this->createMock(PageLayoutContext::class);
        $pageLayoutContextMock
            ->expects(self::any())
            ->method('getCurrentRequest')
            ->willReturn($requestMock);

        return new PageContentPreviewRenderingEvent(
            'tt_content',
            (string)($record[(string)($GLOBALS['TCA']['tt_content']['ctrl']['type'] ?? '')] ?? ''),
            $recordMock,
            $pageLayoutContextMock,
        );
    }
}
