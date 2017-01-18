<?php

return [
    'ctrl' => [
        'title' => $ll . 'tx_fakeextension_domain_model_bar',
        'descriptionColumn' => 'tag',
        'label' => 'title',
        'hideAtCopy' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'versioningWS' => true,
        'origUid' => 't3_origuid',
        'editlock' => 'editlock',
        'type' => 'type',
        'dividers2tabs' => true,
        'useColumnsForDefaultValues' => 'type',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'default_sortby' => 'ORDER BY datetime DESC',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime'
        ],
        'searchFields' => 'uid,title',
    ],
    'interface' => [
        'showRecordFieldList' => 'cruser_id,pid,sys_language_uid,l10n_parent,l10n_diffsource,hidden,starttime,endtime,title'
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    [
                        'LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages',
                        -1,
                        'flags-multiple'
                    ],
                ],
                'default' => 0,
            ]
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_fakeextension_domain_model_foo',
                'foreign_table_where' => 'AND tx_fakeextension_domain_model_foo.pid=###CURRENT_PID### AND tx_fakeextension_domain_model_foo.sys_language_uid IN (-1,0)',
                'showIconTable' => false
            ]
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => ''
            ]
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
                'default' => 0
            ]
        ],
        'cruser_id' => [
            'label' => 'cruser_id',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'pid' => [
            'label' => 'pid',
            'config' => [
                'type' => 'passthrough'
            ]
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'sorting' => [
            'label' => 'sorting',
            'config' => [
                'type' => 'passthrough',
            ]
        ],
        'starttime' => [
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:cms/locallang_ttc.xlf:starttime_formlabel',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'max' => 20,
                'eval' => 'datetime',
                'default' => 0,
            ]
        ],
        'endtime' => [
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:cms/locallang_ttc.xlf:endtime_formlabel',
            'config' => [
                'type' => 'input',
                'size' => 8,
                'max' => 20,
                'eval' => 'datetime',
                'default' => 0,
            ]
        ],
        'title' => [
            'exclude' => 0,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'LLL:EXT:cms/locallang_ttc.xlf:header_formlabel',
            'config' => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'required',
            ]
        ],
        'editlock' => [
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:editlock',
            'config' => [
                'type' => 'check'
            ]
        ],
        'tags' => [
            'exclude' => 1,
            'label' => 'Tags:',
            'config' => [
                'type' => 'group',
                'internal_type' => 'db',
                'allowed' => 'tx_fakeextension_domain_model_mmrelated',
                'MM' => 'tx_fakeextension_domain_model_related_mm',
                //@todo is the really as needed in the typo3 core?
                'foreign_table' => 'tx_fakeextension_domain_model_mmrelated',
                'size' => '5',
                'maxitems' => '200',
                'minitems' => '0',
                'show_thumbs' => '1'
              ]
         ],
        'category' => [
            'exclude' => 1,
            'label' => 'Category',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_fakeextension_domain_model_directrelated',
                'maxitems' => 10,
                'appearance' => [
                    'collapseAll' => 1,
                    'expandSingle' => 1,
                ],
            ],
        ]
     ],
     'types' => [
        '0' => [
            'showitem' => 'l10n_parent, l10n_diffsource,title,tags'
        ]
    ]
];
