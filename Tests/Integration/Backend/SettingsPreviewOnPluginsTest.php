<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Backend;

use ApacheSolrForTypo3\Solr\Backend\SettingsPreviewOnPlugins;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Backend\View\PageLayoutContext;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Service\FlexFormService;

/**
 * EXT:solr offers a summary in the BE on search plugins, that summarizes the extension
 * configuration.
 * This testcase checks if the SummaryController produces the expected output.
 */
class SettingsPreviewOnPluginsTest extends IntegrationTest
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
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        parent::setUp();
    }

    /**
     * @test
     */
    public function printsPreviewOnExtSolrPluginsCorrectly()
    {
        $settingsPreviewOnPlugins = new SettingsPreviewOnPlugins(
            $this->getMockOfFlexFormService($this->flexFormArray)
        );
        $event = $this->getFakePageContentPreviewRenderingEvent(
            'tt_content',
            [
                'pid' => 11,
                'pi_flexform' => 'provided via mock return value $this->flexFormArray',
                'list_type' => 'solr_pi_results',
                'hidden' => 0,
            ]
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
        return new PageContentPreviewRenderingEvent(
            $table,
            $record,
            $this->createMock(PageLayoutContext::class)
        );
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
