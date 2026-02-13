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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Backend;

use ApacheSolrForTypo3\Solr\Backend\SettingsPreviewOnPlugins;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Domain\FlexFormFieldValues;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\View\ViewInterface;

/**
 * EXT:solr offers a summary in the BE on search plugins, that summarizes the extension
 * configuration.
 * This testcase checks if the SummaryController produces the expected output.
 */
class SettingsPreviewOnPluginsTest extends SetUpUnitTestCase
{
    protected array $flexFormArray = [
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

    protected function setUp(): void
    {
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        parent::setUp();
    }

    #[Test]
    public function doesNotRenderPreviewForNonSolrCTypes(): void
    {
        $backendViewFactoryMock = $this->createMock(BackendViewFactory::class);
        $backendViewFactoryMock->expects(self::never())->method('create');

        $settingsPreview = new SettingsPreviewOnPlugins($backendViewFactoryMock);
        $event = $this->createFakeEvent(['CType' => 'textmedia']);
        $settingsPreview->__invoke($event);

        self::assertNull($event->getPreviewContent());
    }

    #[Test]
    public function extractsFlexformSettingsCorrectly(): void
    {
        $capturedData = [];
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->expects(self::once())
            ->method('assignMultiple')
            ->willReturnCallback(function (array $data) use (&$capturedData, $viewMock): ViewInterface {
                $capturedData = $data;
                return $viewMock;
            });
        $viewMock->method('render')->willReturn('rendered content');

        $backendViewFactoryMock = $this->createMock(BackendViewFactory::class);
        $backendViewFactoryMock->method('create')->willReturn($viewMock);

        // Anonymous subclass to avoid BackendUtility::getLabelFromItemListMerged() DB access in getPluginLabel()
        $settingsPreview = new class ($backendViewFactoryMock) extends SettingsPreviewOnPlugins {
            protected function getPluginLabel(): string
            {
                return 'Search (solr_pi_results)';
            }
        };

        $event = $this->createFakeEvent([
            'pid' => 11,
            'pi_flexform' => new FlexFormFieldValues(['sDEF' => $this->flexFormArray]),
            'CType' => 'solr_pi_results',
            'hidden' => 0,
        ]);
        $settingsPreview->__invoke($event);

        // Verify flexform values were correctly extracted into settings
        $settings = $capturedData['settings'];
        self::assertSame('[4711] ERROR: page is gone', $settings['Target Page']);
        self::assertSame('test', $settings['Filter appKey']);
        self::assertSame('1', $settings['Filter changed']);
        self::assertSame('sorting', $settings['Sorting']);
        self::assertSame('10', $settings['Results per Page']);
        self::assertSame('boostFunction', $settings['Boost Function']);
        self::assertSame('boostQuery', $settings['Boost Query']);
        self::assertSame('myTemplateFile.html', $settings['Template']);
        self::assertArrayNotHasKey('Tie Breaker', $settings, 'Empty values should not be added');

        // Verify plugin label and hidden state are passed to view
        self::assertSame('Search (solr_pi_results)', $capturedData['pluginLabel']);
        self::assertSame(0, $capturedData['hidden']);

        // Verify preview content was set on event
        self::assertSame('rendered content', $event->getPreviewContent());
    }

    protected function createFakeEvent(array $record): PageContentPreviewRenderingEvent
    {
        $recordMock = $this->createMock(RecordInterface::class);
        $recordMock->method('toArray')->willReturn($record);

        $requestMock = $this->createMock(ServerRequestInterface::class);
        $pageLayoutContextMock = $this->createMock(PageLayoutContext::class);
        $pageLayoutContextMock->method('getCurrentRequest')->willReturn($requestMock);

        return new PageContentPreviewRenderingEvent(
            'tt_content',
            (string)($record[(string)($GLOBALS['TCA']['tt_content']['ctrl']['type'] ?? '')] ?? ''),
            $recordMock,
            $pageLayoutContextMock,
        );
    }
}
