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
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Service\FlexFormService;

/**
 * EXT:solr offers a summary in the BE on search plugins, that summarizes the extension
 * configuration.
 * This testcase checks if the SummaryController produces the expected output.
 */
class SettingsPreviewOnPluginsTest extends SetUpUnitTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        parent::setUp();
    }

    #[Test]
    public function doesNotPrintPreviewOnNonExtSolrPlugins(): void
    {
        $settingPreviewMock = $this->getMockOfSettingsPreviewOnPlugins(['getPreviewContent']);
        $settingPreviewMock
            ->expects(self::never())
            ->method('getPreviewContent');
        $settingPreviewMock->__invoke(
            $this->getFakePageContentPreviewRenderingEvent(
                'tt_content',
                [
                    'CType' => 'some_other_CE',
                ],
            ),
        );
    }

    protected function getMockOfSettingsPreviewOnPlugins(array $methods = []): MockObject|SettingsPreviewOnPlugins
    {
        return $this->getMockBuilder(SettingsPreviewOnPlugins::class)
            ->setConstructorArgs([
                'flexFormService' => $this->getMockOfFlexFormService(),
                'backendViewFactory' => $this->createMock(BackendViewFactory::class),
            ])
            ->onlyMethods($methods)
            ->getMock();
    }

    protected function getFakePageContentPreviewRenderingEvent(
        string $table = 'tt_content',
        array $record = [],
    ): PageContentPreviewRenderingEvent {
        $recordMock = $this->createMock(RecordInterface::class);
        $recordMock->method('toArray')->willReturn($record);

        /** @var PageLayoutContext|MockObject $pageLayoutContextMock */
        $pageLayoutContextMock = $this->createMock(PageLayoutContext::class);
        return new PageContentPreviewRenderingEvent(
            $table,
            (string)($record[(string)($GLOBALS['TCA'][$table]['ctrl']['type'] ?? '')] ?? ''),
            $recordMock,
            $pageLayoutContextMock,
        );
    }

    protected function getMockOfFlexFormService(
        array $expectedFlexFormArray = [],
    ): MockObject|FlexFormService {
        $flexFormServiceMock =  $this->getMockBuilder(FlexFormService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'convertFlexFormContentToArray',
            ])
            ->getMock();
        $flexFormServiceMock
            ->expects(self::any())
            ->method('convertFlexFormContentToArray')
            ->willReturn($expectedFlexFormArray);

        return $flexFormServiceMock;
    }
}
