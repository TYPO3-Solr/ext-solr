<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\TCA;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;

/**
 * Testcase to test the TCAService.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TCAServiceTest extends UnitTest
{

    /**
     * When a deleted record is passed (has 1 in the TCA deleted field, this should be detected).
     *
     * @test
     */
    public function getIsEnabledRecordDetectDeletedRecord() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted'
                ]
            ]
        ];

        $fakePageRecord = [
            'deleted' => 1
        ];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        $this->assertFalse($isVisible);
    }

    /**
     * When a record is passed that is not deleted we should detect that.
     *
     * @test
     */
    public function getIsEnabledRecordDetectNonDeletedRecord() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted'
                ]
            ]
        ];

        $fakePageRecord = [
            'title' => 'hello world'
        ];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        $this->assertTrue($isVisible);
    }

    /**
     * When a page record is passed with the field no_search = 1 it should be detected is invisible
     *
     * @test
     */
    public function getIsEnabledRecordDetectsPageConfiguredWithNoSearch() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted'
                ]
            ]
        ];

        $fakePageRecord = [
            'no_search' => 1
        ];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        $this->assertFalse($isVisible);
    }

    /**
     * When a page record is passed with the field no_search = 1 it should be detected is invisible
     *
     * @test
     */
    public function getIsEnabledRecordEmptyRecord() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted'
                ]
            ]
        ];

        $fakePageRecord = [];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        $this->assertFalse($isVisible);
    }

    /**
     * @test
     */
    public function isEndTimeInPastCanDetectedEndtimeThatIsInPast() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'endtime' => 'end'
                    ]
                ]
            ]
        ];

        $GLOBALS['EXEC_TIME'] = 1000;
        $fakePageRecord = [
            'end' => 999
        ];
        $tcaService = new TCAService($fakeTCA);
        $isEndTimeInPast = $tcaService->isEndTimeInPast('pages', $fakePageRecord);

        $this->assertTrue($isEndTimeInPast, 'Endtime in past was not detected as endtime in past');
    }


    /**
     * @test
     */
    public function isEndTimeInPastCanDetectedEndtimeThatIsNotInPast() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'endtime' => 'end'
                    ]
                ]
            ]
        ];

        $GLOBALS['EXEC_TIME'] = 1000;
        $fakePageRecord = [
            'end' => 1001
        ];
        $tcaService = new TCAService($fakeTCA);
        $isEndTimeInPast = $tcaService->isEndTimeInPast('pages', $fakePageRecord);

        $this->assertFalse($isEndTimeInPast, 'Endtime in future, was detected as endtime in past');
    }

    /**
     * @test
     */
    public function isEndTimeInPastCanDetectedEndtimeIsEmpty(){
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'endtime' => 'end'
                    ]
                ]
            ]
        ];

        $GLOBALS['EXEC_TIME'] = 1000;
        $fakePageRecord = [
            'end' => 0
        ];

        $tcaService = new TCAService($fakeTCA);
        $isEndTimeInPast = $tcaService->isEndTimeInPast('pages', $fakePageRecord);

        $this->assertFalse($isEndTimeInPast, 'Not set endtime(default 0), was detected as endtime in past.');
    }

    /**
     * @test
     */
    public function isStartTimeInFutureCanDetectedStartTimeInFuture() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'starttime' => 'start'
                    ]
                ]
            ]
        ];

        $GLOBALS['EXEC_TIME'] = 1000;
        $fakePageRecord = [
            'start' => 1001
        ];
        $tcaService = new TCAService($fakeTCA);
        $isStartTimeInFuture = $tcaService->isStartTimeInFuture('pages', $fakePageRecord);

        $this->assertTrue($isStartTimeInFuture, 'Starttime in future was not detected as start time in future');
    }

    /**
     * @test
     */
    public function isStartTimeInFutureCanDetectedStartTimeNotInFuture() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'starttime' => 'start'
                    ]
                ]
            ]
        ];

        $GLOBALS['EXEC_TIME'] = 1000;
        $fakePageRecord = [
            'start' => 999
        ];
        $tcaService = new TCAService($fakeTCA);
        $isStartTimeInFuture = $tcaService->isStartTimeInFuture('pages', $fakePageRecord);

        $this->assertFalse($isStartTimeInFuture, 'Start time in past was detected as starttime in future');
    }

    /**
     * @test
     */
    public function isHiddenCanDetectHiddenRecord() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'disabled' => 'hidden'
                    ]
                ]
            ]
        ];

        $fakePageRecord = [
            'hidden' => 1
        ];
        $tcaService = new TCAService($fakeTCA);
        $isHidden = $tcaService->isHidden('pages', $fakePageRecord);

        $this->assertTrue($isHidden, 'Page was expected to be hidden');
    }

    /**
     * @test
     */
    public function isHiddenCanDetectNonHiddenRecord() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'disabled' => 'hidden'
                    ]
                ]
            ]
        ];

        $fakePageRecord = [
            'hidden' => 0
        ];
        $tcaService = new TCAService($fakeTCA);
        $isHidden = $tcaService->isHidden('pages', $fakePageRecord);

        $this->assertFalse($isHidden, 'Page was not expected to be hidden');
    }

    /**
     * @test
     */
    public function canNormalizeFrontendGroupField() {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'fe_group' => 'fe_groups'
                    ]
                ]
            ]
        ];

        $fakePageRecord = [];
        $tcaService = new TCAService($fakeTCA);
        $normalizedRecord = $tcaService->normalizeFrontendGroupField('pages', $fakePageRecord);

        $this->assertSame($normalizedRecord['fe_groups'], '0', 'Empty fe_group field was not normalized to 0');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnDefaultFieldsWhenNoTCAIsConfigured()
    {
        $tcaService = new TCAService([]);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('pages');

        $this->assertContains('doktype', $visibilityFields, 'Expected to have doktype as visibility affecting field as default for pages');
        $this->assertContains('no_search', $visibilityFields, 'Expected to have no_search as visibility affecting field as default for pages');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnUidAndPidForNormalRecordTable()
    {
        $tcaService = new TCAService([]);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('tx_domain_model_faketable');
        $this->assertEquals('uid, pid', $visibilityFields, 'TCA Service should return uid and pid of visibility affecting fields for record table where no TCA is configured');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnConfiguredDeleteField()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'delete' => 'deleted'
                ]
            ]
        ];

        $tcaService = new TCAService($fakeTCA);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('tx_domain_model_faketable');
        $this->assertContains('deleted', $visibilityFields, 'The deleted field should be retrieved as visibility affecting field');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnConfiguredEnableConfiguredEnabledColumnFields()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'fe_group' => 'fe_groups'
                    ]
                ]
            ]
        ];

        $tcaService = new TCAService($fakeTCA);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('tx_domain_model_faketable');
        $this->assertContains('fe_groups', $visibilityFields, 'The field fe_groups should be retrieved as visbility affecting field');
    }
}