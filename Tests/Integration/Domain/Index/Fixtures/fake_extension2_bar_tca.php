<?php

return array(
    'ctrl' => array(
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
        'enablecolumns' => array(
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime'
        ),
        'searchFields' => 'uid,title',
    ),
    'interface' => array(
        'showRecordFieldList' => 'cruser_id,pid,sys_language_uid,l10n_parent,l10n_diffsource,hidden,starttime,endtime,title'
    ),
    'columns' => array(
        'sys_language_uid' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.language',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => array(
                    array(
                        'LLL:EXT:lang/locallang_general.xlf:LGL.allLanguages',
                        -1,
                        'flags-multiple'
                    ),
                ),
                'default' => 0,
            )
        ),
        'l10n_parent' => array(
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.l18n_parent',
            'config' => array(
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => array(
                    array('', 0),
                ),
                'foreign_table' => 'tx_fakeextension_domain_model_foo',
                'foreign_table_where' => 'AND tx_fakeextension_domain_model_foo.pid=###CURRENT_PID### AND tx_fakeextension_domain_model_foo.sys_language_uid IN (-1,0)',
                'showIconTable' => false
            )
        ),
        'l10n_diffsource' => array(
            'config' => array(
                'type' => 'passthrough',
                'default' => ''
            )
        ),
        'hidden' => array(
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
            'config' => array(
                'type' => 'check',
                'default' => 0
            )
        ),
        'cruser_id' => array(
            'label' => 'cruser_id',
            'config' => array(
                'type' => 'passthrough'
            )
        ),
        'pid' => array(
            'label' => 'pid',
            'config' => array(
                'type' => 'passthrough'
            )
        ),
        'crdate' => array(
            'label' => 'crdate',
            'config' => array(
                'type' => 'passthrough',
            )
        ),
        'tstamp' => array(
            'label' => 'tstamp',
            'config' => array(
                'type' => 'passthrough',
            )
        ),
        'sorting' => array(
            'label' => 'sorting',
            'config' => array(
                'type' => 'passthrough',
            )
        ),
        'starttime' => array(
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:cms/locallang_ttc.xlf:starttime_formlabel',
            'config' => array(
                'type' => 'input',
                'size' => 8,
                'max' => 20,
                'eval' => 'datetime',
                'default' => 0,
            )
        ),
        'endtime' => array(
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:cms/locallang_ttc.xlf:endtime_formlabel',
            'config' => array(
                'type' => 'input',
                'size' => 8,
                'max' => 20,
                'eval' => 'datetime',
                'default' => 0,
            )
        ),
        'title' => array(
            'exclude' => 0,
            'l10n_mode' => 'prefixLangTitle',
            'label' => 'LLL:EXT:cms/locallang_ttc.xlf:header_formlabel',
            'config' => array(
                'type' => 'input',
                'size' => 60,
                'eval' => 'required',
            )
        ),


        'editlock' => array(
            'exclude' => 1,
            'l10n_mode' => 'mergeIfNotBlank',
            'label' => 'LLL:EXT:lang/locallang_tca.xlf:editlock',
            'config' => array(
                'type' => 'check'
            )
        ),

        'tags' => array(
            'exclude' => 1,
            'label' => 'Tags:',
            'config' => array(
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
              )
         ),
        'category' => array(
            'exclude' => 1,
            'label' => 'Category',
            'config' => array(
                'type' => 'inline',
                'foreign_table' => 'tx_fakeextension_domain_model_directrelated',
                'maxitems' => 10,
                'appearance' => array(
                    'collapseAll' => 1,
                    'expandSingle' => 1,
                ),
            ),
        )

     ),
     'types' => array(
        '0' => array(
            'showitem' => 'l10n_parent, l10n_diffsource,title,tags'
        )
    )
);
