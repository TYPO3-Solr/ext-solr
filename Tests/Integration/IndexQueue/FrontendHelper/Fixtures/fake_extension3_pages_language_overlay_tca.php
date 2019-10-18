<?php

return [
    'columns' => [
        'page_relations' => [
            'exclude' => 1,
            'label' => 'Page relations',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'enableMultiSelectFilterTextfield' => true,
                'foreign_table' => 'pages',
                'MM' => 'tx_fakeextension3_pages_mm',
                'MM_opposite_field' => 'relations',
                'MM_match_fields' => [
                    'tablenames' => 'pages',
                    'fieldname' => 'page_relations'
                ],
                'size' => 10,
                'autoSizeMax' => 30,
                'maxitems' => 9999,
                'readOnly' => true,
            ],
        ],
        'relations' => [
            'label' => 'Used as relation to',
            'config' => [
                'type' => 'group',
                'default' => '',
                'allowed' => '*',
                'internal_type' => 'db',
                'MM' => 'tx_fakeextension3_pages_mm',
                'MM_oppositeUsage' => [
                    'pages' => [
                        'page_relations',
                    ],
                ],
                'readOnly' => true
            ]
        ]
    ]
];
