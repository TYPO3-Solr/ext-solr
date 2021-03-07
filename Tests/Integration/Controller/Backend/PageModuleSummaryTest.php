<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\Controller\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 Timo Hund <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Controller\Backend\PageModuleSummary;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\View\PageLayoutView;
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
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->getMockBuilder(LanguageService::class)
            ->disableOriginalConstructor()->getMock();
    }

    /**
     * @test
     */
    public function canGetSummary() {
        $flexFormData = $this->getFixtureContentByName('fakeFlexform.xml');

        $fakeRow = ['pi_flexform' => $flexFormData];
        $pageLayoutViewMock = $this->getMockBuilder(PageLayoutView::class)->disableOriginalConstructor()->getMock();
        $pageLayoutViewMock->expects($this->any())->method('linkEditContent')->will($this->returnValue('fakePluginLabel'));
        $data = [
            'row' => $fakeRow,
            'pObj' => $pageLayoutViewMock
        ];
        $summary = new PageModuleSummary();
        $result = $summary->getSummary($data);

        $this->assertContains('fakePluginLabel', $result, 'Summary did not contain plugin label');
        $this->assertContains('>Filter appKey</td>', $result, 'Summary did not contain filter label');
        $this->assertContains('<td>test</td>', $result, 'Summary did not contain filter value');
        $this->assertContains('<td>sorting</td>', $result, 'Summary did not contain sorting');
        $this->assertContains('<td>boostFunction</td>', $result, 'Summary did not contain boostFunction');
        $this->assertContains('<td>boostQuery</td>', $result, 'Summary did not contain boostQuery');
        $this->assertContains('<td>10</td>', $result, 'Summary did not contain resultsPerPage');
        $this->assertContains('<td>myTemplateFile.html</td>', $result, 'Templatefile not in summary');
    }
}
