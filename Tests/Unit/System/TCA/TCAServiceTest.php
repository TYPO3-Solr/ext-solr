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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\System\TCA;

use ApacheSolrForTypo3\Solr\System\TCA\TCAService;
use ApacheSolrForTypo3\Solr\Tests\Unit\UnitTest;
use DateTimeImmutable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
    public function getIsEnabledRecordDetectDeletedRecord()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted',
                ],
            ],
        ];

        $fakePageRecord = [
            'deleted' => 1,
        ];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        self::assertFalse($isVisible);
    }

    /**
     * When a record is passed that is not deleted we should detect that.
     *
     * @test
     */
    public function getIsEnabledRecordDetectNonDeletedRecord()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted',
                ],
            ],
        ];

        $fakePageRecord = [
            'title' => 'hello world',
        ];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        self::assertTrue($isVisible);
    }

    /**
     * When a page record is passed with the field no_search = 1 it should be detected is invisible
     *
     * @test
     */
    public function getIsEnabledRecordDetectsPageConfiguredWithNoSearch()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted',
                ],
            ],
        ];

        $fakePageRecord = [
            'no_search' => 1,
        ];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        self::assertFalse($isVisible);
    }

    /**
     * When a page record is passed with the field no_search = 1 it should be detected is invisible
     *
     * @test
     */
    public function getIsEnabledRecordEmptyRecord()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'delete' => 'deleted',
                ],
            ],
        ];

        $fakePageRecord = [];

        $tcaService = new TCAService($fakeTCA);
        $isVisible = $tcaService->isEnabledRecord('pages', $fakePageRecord);

        self::assertFalse($isVisible);
    }

    /**
     * @test
     */
    public function isEndTimeInPastCanDetectedEndtimeThatIsInPast()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'endtime' => 'end',
                    ],
                ],
            ],
        ];

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new DateTimeImmutable('@1000')));
        $fakePageRecord = [
            'end' => 999,
        ];
        $tcaService = new TCAService($fakeTCA);
        $isEndTimeInPast = $tcaService->isEndTimeInPast('pages', $fakePageRecord);

        self::assertTrue($isEndTimeInPast, 'Endtime in past was not detected as endtime in past');
    }

    /**
     * @test
     */
    public function isEndTimeInPastCanDetectedEndtimeThatIsNotInPast()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'endtime' => 'end',
                    ],
                ],
            ],
        ];

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new DateTimeImmutable('@1000')));
        $fakePageRecord = [
            'end' => 1001,
        ];
        $tcaService = new TCAService($fakeTCA);
        $isEndTimeInPast = $tcaService->isEndTimeInPast('pages', $fakePageRecord);

        self::assertFalse($isEndTimeInPast, 'Endtime in future, was detected as endtime in past');
    }

    /**
     * @test
     */
    public function isEndTimeInPastCanDetectedEndtimeIsEmpty()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'endtime' => 'end',
                    ],
                ],
            ],
        ];

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new DateTimeImmutable('@1000')));
        $fakePageRecord = [
            'end' => 0,
        ];

        $tcaService = new TCAService($fakeTCA);
        $isEndTimeInPast = $tcaService->isEndTimeInPast('pages', $fakePageRecord);

        self::assertFalse($isEndTimeInPast, 'Not set endtime(default 0), was detected as endtime in past.');
    }

    /**
     * @test
     */
    public function isStartTimeInFutureCanDetectedStartTimeInFuture()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'starttime' => 'start',
                    ],
                ],
            ],
        ];

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new DateTimeImmutable('@1000')));
        $fakePageRecord = [
            'start' => 1001,
        ];
        $tcaService = new TCAService($fakeTCA);
        $isStartTimeInFuture = $tcaService->isStartTimeInFuture('pages', $fakePageRecord);

        self::assertTrue($isStartTimeInFuture, 'Starttime in future was not detected as start time in future');
    }

    /**
     * @test
     */
    public function isStartTimeInFutureCanDetectedStartTimeNotInFuture()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'starttime' => 'start',
                    ],
                ],
            ],
        ];

        GeneralUtility::makeInstance(Context::class)
            ->setAspect('date', new DateTimeAspect(new DateTimeImmutable('@1000')));
        $fakePageRecord = [
            'start' => 999,
        ];
        $tcaService = new TCAService($fakeTCA);
        $isStartTimeInFuture = $tcaService->isStartTimeInFuture('pages', $fakePageRecord);

        self::assertFalse($isStartTimeInFuture, 'Start time in past was detected as starttime in future');
    }

    /**
     * @test
     */
    public function isHiddenCanDetectHiddenRecord()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'disabled' => 'hidden',
                    ],
                ],
            ],
        ];

        $fakePageRecord = [
            'hidden' => 1,
        ];
        $tcaService = new TCAService($fakeTCA);
        $isHidden = $tcaService->isHidden('pages', $fakePageRecord);

        self::assertTrue($isHidden, 'Page was expected to be hidden');
    }

    /**
     * @test
     */
    public function isHiddenCanDetectNonHiddenRecord()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'disabled' => 'hidden',
                    ],
                ],
            ],
        ];

        $fakePageRecord = [
            'hidden' => 0,
        ];
        $tcaService = new TCAService($fakeTCA);
        $isHidden = $tcaService->isHidden('pages', $fakePageRecord);

        self::assertFalse($isHidden, 'Page was not expected to be hidden');
    }

    /**
     * @test
     */
    public function canNormalizeFrontendGroupField()
    {
        $fakeTCA = [
            'pages' => [
                'ctrl' => [
                    'enablecolumns' => [
                        'fe_group' => 'fe_groups',
                    ],
                ],
            ],
        ];

        $fakePageRecord = [];
        $tcaService = new TCAService($fakeTCA);
        $normalizedRecord = $tcaService->normalizeFrontendGroupField('pages', $fakePageRecord);

        self::assertSame($normalizedRecord['fe_groups'], '0', 'Empty fe_group field was not normalized to 0');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnDefaultFieldsWhenNoTCAIsConfigured()
    {
        $tcaService = new TCAService([]);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('pages');

        self::assertStringContainsString('doktype', $visibilityFields, 'Expected to have doktype as visibility affecting field as default for pages');
        self::assertStringContainsString('no_search', $visibilityFields, 'Expected to have no_search as visibility affecting field as default for pages');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnUidAndPidForNormalRecordTable()
    {
        $tcaService = new TCAService([]);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('tx_domain_model_faketable');
        self::assertEquals('uid, pid', $visibilityFields, 'TCA Service should return uid and pid of visibility affecting fields for record table where no TCA is configured');
    }

    /**
     * @test
     */
    public function getVisibilityAffectingFieldsByTableCanReturnConfiguredDeleteField()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'delete' => 'deleted',
                ],
            ],
        ];

        $tcaService = new TCAService($fakeTCA);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('tx_domain_model_faketable');
        self::assertStringContainsString('deleted', $visibilityFields, 'The deleted field should be retrieved as visibility affecting field');
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
                        'fe_group' => 'fe_groups',
                    ],
                ],
            ],
        ];

        $tcaService = new TCAService($fakeTCA);
        $visibilityFields = $tcaService->getVisibilityAffectingFieldsByTable('tx_domain_model_faketable');
        self::assertStringContainsString('fe_groups', $visibilityFields, 'The field fe_groups should be retrieved as visbility affecting field');
    }

    /**
     * @test
     */
    public function getTranslationOriginalUid()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'transOrigPointerField' => 'l10n_parent',
                ],
            ],
        ];

        $tcaService = new TCAService($fakeTCA);
        $fakeRecord = ['l10n_parent' => 999];

        $l10nParentUid = $tcaService->getTranslationOriginalUid('tx_domain_model_faketable', $fakeRecord);
        self::assertSame(999, $l10nParentUid, 'l10nParentUid should be null when the data is not set in the record');
    }

    /**
     * @test
     */
    public function getTranslationOriginalUidReturnsNullWhenFieldIsEmpty()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'transOrigPointerField' => 'l10n_parent',
                ],
            ],
        ];

        $tcaService = new TCAService($fakeTCA);
        $fakeRecord = [];

        $l10nParentUid = $tcaService->getTranslationOriginalUid('tx_domain_model_faketable', $fakeRecord);
        self::assertNull($l10nParentUid, 'l10nParentUid should be null when the data is not set in the record');
    }

    /**
     * @test
     */
    public function getTranslationOriginalUidReturnsNullWhenPointerFieldIsNotConfigured()
    {
        $tcaService = new TCAService([]);
        $fakeRecord = [];
        $l10nParentUid = $tcaService->getTranslationOriginalUid('tx_domain_model_faketable', $fakeRecord);
        self::assertNull($l10nParentUid, 'l10nParentUid should be null when the data is not set in the record');
    }

    /**
     * @test
     */
    public function isLocalizedRecord()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'transOrigPointerField' => 'l10n_parent',
                ],
            ],
        ];

        $tcaService = new TCAService($fakeTCA);

        self::assertFalse($tcaService->isLocalizedRecord('tx_domain_model_faketable', ['l10n_parent' => 0]), 'Item with l10n_parent => 0 should not be indicated as translation');
        self::assertTrue($tcaService->isLocalizedRecord('tx_domain_model_faketable', ['l10n_parent' => 9999]), 'Item with l10n_parent => 9999 should be indicated as translation');
        self::assertFalse($tcaService->isLocalizedRecord('tx_domain_model_faketable_withouttca', ['l10n_parent' => 9999]), 'Item without tca should not be indicated as translation');
    }

    /**
     * @test
     */
    public function getTranslationOriginalUidIfTranslated()
    {
        $fakeTCA = [
            'tx_domain_model_faketable' => [
                'ctrl' => [
                    'transOrigPointerField' => 'l10n_parent',
                ],
            ],
        ];

        $tcaService = new TCAService($fakeTCA);

        self::assertSame(4711, $tcaService->getTranslationOriginalUidIfTranslated('tx_domain_model_faketable', ['l10n_parent' => 0], 4711), 'No translation, original uid should be returned');
        self::assertSame(9999, $tcaService->getTranslationOriginalUidIfTranslated('tx_domain_model_faketable', ['l10n_parent' => 9999], 4711), 'Valid translation, uid of parent should be returned');
        self::assertSame(4711, $tcaService->getTranslationOriginalUidIfTranslated('tx_domain_model_faketable_withouttca', ['l10n_parent' => 9999], 4711), 'No translation, original uid should be returned');
    }
}
