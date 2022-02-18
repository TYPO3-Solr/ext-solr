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

namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Backend;

use ApacheSolrForTypo3\Solr\Controller\Backend\PageModuleSummary;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * EXT:solr offers a summary in the backend module that summarizes the extension
 * configuration. This testcase checks if the SummaryController produces the expected output.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class PageModuleSummaryTest extends IntegrationTest
{
    /**
     * @throws NoSuchCacheException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
    }

    /**
     * @test
     */
    public function canGetSummary()
    {
        $flexFormData = $this->getFixtureContentByName('fakeFlexform.xml');

        $fakeRow = ['pi_flexform' => $flexFormData];
        $pageLayoutViewMock = $this->createMock(PageLayoutView::class);
        $pageLayoutViewMock->expects(self::any())->method('linkEditContent')->willReturn('fakePluginLabel');
        $data = [
            'row' => $fakeRow,
            'pObj' => $pageLayoutViewMock,
        ];
        $summary = new PageModuleSummary();
        $result = $summary->getSummary($data);

        self::assertStringContainsString('fakePluginLabel', $result, 'Summary did not contain plugin label');
        self::assertStringContainsString('>Filter appKey</td>', $result, 'Summary did not contain filter label');
        self::assertStringContainsString('<td>test</td>', $result, 'Summary did not contain filter value');
        self::assertStringContainsString('<td>sorting</td>', $result, 'Summary did not contain sorting');
        self::assertStringContainsString('<td>boostFunction</td>', $result, 'Summary did not contain boostFunction');
        self::assertStringContainsString('<td>boostQuery</td>', $result, 'Summary did not contain boostQuery');
        self::assertStringContainsString('<td>10</td>', $result, 'Summary did not contain resultsPerPage');
        self::assertStringContainsString('<td>myTemplateFile.html</td>', $result, 'Templatefile not in summary');
    }
}
