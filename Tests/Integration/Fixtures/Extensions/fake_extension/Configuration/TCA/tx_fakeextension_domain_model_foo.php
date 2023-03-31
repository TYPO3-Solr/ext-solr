<?php

return [
    'ctrl' => [
        'title' => 'tx_fakeextension_domain_model_foo',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'editlock' => 'editlock',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'uid',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'sys_language_uid',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages',
                        -1,
                        'flags-multiple',
                    ],
                ],
                'default' => 0,
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'l10n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_fakeextension_domain_model_foo',
                'foreign_table_where' => 'AND tx_fakeextension_domain_model_foo.pid=###CURRENT_PID### AND tx_fakeextension_domain_model_foo.sys_language_uid IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'hidden',
            'config' => [
                'type' => 'check',
                'default' => 0,
            ],
        ],
        'cruser_id' => [
            'label' => 'cruser_id',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'pid' => [
            'label' => 'pid',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'sorting' => [
            'label' => 'sorting',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'starttime' => [
            'exclude' => 1,
            'label' => 'starttime',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'eval' => 'datetime',
                'default' => 0,
                'renderType' => 'inputDateTime',
                ['behaviour' => ['allowLanguageSynchronization' => true]],
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'endtime',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'eval' => 'datetime',
                'default' => 0,
                'renderType' => 'inputDateTime',
                ['behaviour' => ['allowLanguageSynchronization' => true]],
            ],
        ],
        'title' => [
            'exclude' => 0,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'title',
            'config' => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'required',
            ],
        ],
        'main_category' => [
            'exclude' => 0,
            'label' => 'main_category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'sys_category',
                'foreign_table_where' => ' AND (sys_category.sys_language_uid = 0 OR sys_category.l10n_parent = 0)',
                'size' => 1,
                'minitems' => 0,
                'maxitems' => 1,
                ['behaviour' => ['allowLanguageSynchronization' => true]],
            ],
        ],
        'mm_assignments' => [
            'exclude' => 0,
            'label' => 'mm_assignments',
            'config' => [
                'type' => 'group',
                'foreign_table' => 'tx_fakeextension_domain_model_bar',
                'allowed' => 'tx_fakeextension_domain_model_bar',
                'MM' => 'tx_fakeextension_foo_bar_mm',
                'maxitems' => 99999,
            ],
        ],
        'inline_relations' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'label' => 'inline_relations',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_fakeextension_domain_model_bar',
                'foreign_field' => 'inline_relation_parent_foo',
                'maxitems' => 9999,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'l10n_parent, l10n_diffsource,title,main_category,bar_assignments,inline_relations',
        ],
    ],
];
