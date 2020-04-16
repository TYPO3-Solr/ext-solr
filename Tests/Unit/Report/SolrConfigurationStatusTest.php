<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Report\SolrConfigurationStatus;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use TYPO3\CMS\Reports\Status;


/**
 * Testcase for the SolrConfigurationStatus class.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrConfigurationStatusTest extends UnitTest
{

    /**
     * @var SolrConfigurationStatus
     */
    protected $report;


    public function setUp() {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr'] = [];
        // we mock the methods to external dependencies.

        $this->report = $this->getMockBuilder(SolrConfigurationStatus::class)->setMethods(
            [
                'getDomainRecordsForRootPagesIds',
                'getRootPages',
                'getIsSolrEnabled',
                'getIsIndexingEnabled',
                'initializeTSFE',
                'getRenderedReport'
            ]
        )->getMock();
    }

    /**
     * @test
     */
    public function canGetEmptyResultWhenEverythingIsOK() {
        $fakedRootPages =  [1 => ['uid' => 1, 'title' => 'My Siteroot']];

        $this->report->expects($this->any())->method('getRootPages')->will($this->returnValue($fakedRootPages));

        $this->report->expects($this->any())->method('getIsSolrEnabled')->will($this->returnValue(false));
        $this->report->expects($this->any())->method('getIsIndexingEnabled')->will($this->returnValue(false));

        // everything should be ok, so no report should be rendered
        $this->report->expects($this->never())->method('getRenderedReport');

        $this->report->getStatus();
    }

    /**
     * @test
     */
    public function canGetViolationWhenSolrIsEnabledButIndexingNot() {
        $fakedRootPages =  [1 => ['uid' => 1, 'title' => 'My Siteroot']];

        $this->report->expects($this->any())->method('getRootPages')->will($this->returnValue($fakedRootPages));

        $this->report->expects($this->any())->method('getIsSolrEnabled')->will($this->returnValue(true));
        $this->report->expects($this->any())->method('getIsIndexingEnabled')->will($this->returnValue(false));

        // one report should be rendered because solr is enabled but indexing not
        $this->report->expects($this->once())->method('getRenderedReport')->with(
            'SolrConfigurationStatusIndexing.html',
            ['pages' => [$fakedRootPages[1]]]
        )->will($this->returnValue('faked report output'));


        $states = $this->report->getStatus();

        $this->assertCount(1, $states, 'Expected to have one violation');

            /** @var $firstState Status */
        $firstState = $states[0];
        $this->assertSame(Status::WARNING, $firstState->getSeverity(), 'Expected to have one violation');
    }
}
