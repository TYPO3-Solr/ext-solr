<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Backend;

use ApacheSolrForTypo3\Solr\Backend\SettingsPreviewOnPlugins;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTestBase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;

/**
 * EXT:solr offers a summary in the BE on search plugins, that summarizes the extension
 * configuration.
 * This testcase checks if the SummaryController produces the expected output.
 */
class SettingsPreviewOnPluginsTest extends IntegrationTestBase
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
                        'field' =>
                            [
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
        parent::setUp();
        $GLOBALS['LANG'] = $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
    }

    #[Test]
    public function printsPreviewOnExtSolrPluginsCorrectly(): void
    {
        $settingsPreviewOnPlugins = new SettingsPreviewOnPlugins(
            $this->getMockOfFlexFormService($this->flexFormArray),
            $this->getMockOfBackendViewFactory(),
        );
        $event = $this->getFakePageContentPreviewRenderingEvent(
            'tt_content',
            [
                'pid' => 11,
                'pi_flexform' => 'provided via mock return value $this->flexFormArray',
                'CType' => 'solr_pi_results',
                'hidden' => 0,
            ],
        );
        $settingsPreviewOnPlugins->__invoke($event);
        $result = $event->getPreviewContent();

        self::assertStringContainsString('ERROR: page is gone', $result, 'Summary did not contain plugin label');
        self::assertStringContainsString('>Filter appKey</td>', $result, 'Summary did not contain filter label');
        self::assertStringContainsString('<td>test</td>', $result, 'Summary did not contain filter value');
        self::assertStringContainsString('<td>sorting</td>', $result, 'Summary did not contain sorting');
        self::assertStringContainsString('<td>boostFunction</td>', $result, 'Summary did not contain boostFunction');
        self::assertStringContainsString('<td>boostQuery</td>', $result, 'Summary did not contain boostQuery');
        self::assertStringContainsString('<td>10</td>', $result, 'Summary did not contain resultsPerPage');
        self::assertStringContainsString('<td>myTemplateFile.html</td>', $result, 'Templatefile not in settingsPreviewOnPlugins');
    }

    protected function getFakePageContentPreviewRenderingEvent(string $table = 'tt_content', array $record = []): PageContentPreviewRenderingEvent
    {
        $recordMock = $this->createMock(RecordInterface::class);
        $recordMock->method('toArray')->willReturn($record);

        $requestMock = $this->createMock(ServerRequestInterface::class);
        $pageLayoutContextMock = $this->createMock(PageLayoutContext::class);
        $pageLayoutContextMock->method('getCurrentRequest')->willReturn($requestMock);

        return new PageContentPreviewRenderingEvent(
            $table,
            (string)($record[(string)($GLOBALS['TCA'][$table]['ctrl']['type'] ?? '')] ?? ''),
            $recordMock,
            $pageLayoutContextMock,
        );
    }

    protected function getMockOfBackendViewFactory(): MockObject|BackendViewFactory
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<table><tr><td>ERROR: page is gone</td></tr><tr><td>>Filter appKey</td><td>test</td></tr><tr><td>sorting</td></tr><tr><td>boostFunction</td></tr><tr><td>boostQuery</td></tr><tr><td>10</td></tr><tr><td>myTemplateFile.html</td></tr></table>');

        $backendViewFactoryMock = $this->createMock(BackendViewFactory::class);
        $backendViewFactoryMock->method('create')->willReturn($viewMock);

        return $backendViewFactoryMock;
    }

    protected function getMockOfFlexFormService(array $expectedFlexFormArray = []): MockObject|FlexFormService
    {
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
