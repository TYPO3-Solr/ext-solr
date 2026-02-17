<?php

return [
    'ctrl' => [
        'title' => 'tx_fakeextension_domain_model_bar',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
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
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'sys_language_uid',
            'config' => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'l10n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => '', 'value' => 0],
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
                'type' => 'datetime',
                'format' => 'datetime',
                'searchable' => false,
                ['behaviour' => ['allowLanguageSynchronization' => true]],
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'endtime',
            'config' => [
                'type' => 'datetime',
                'format' => 'datetime',
                'searchable' => false,
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
                'required' => true,
                'searchable' => false,
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
        'inline_relation_parent' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'mm_assignments' => [
            'exclude' => 0,
            'label' => 'mm_assignments',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'tx_fakeextension_domain_model_foo',
                'MM' => 'tx_fakeextension_foo_bar_mm',
                'MM_opposite_field' => 'mm_assignments',
                'foreign_table_where' => 'AND (tx_fakeextension_domain_model_foo.sys_language_uid = 0 OR tx_fakeextension_domain_model_foo.l10n_parent = 0)',
                'size' => 10,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'l10n_parent, l10n_diffsource,title,main_category,mm_assignments',
        ],
    ],
];
