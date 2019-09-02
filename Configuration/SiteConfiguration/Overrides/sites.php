<?php

/**
 * Global Solr Connection Settings
 */

$GLOBALS['SiteConfiguration']['site']['columns']['solr_scheme_read'] = [
    'label' => 'Scheme',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => [
            ['http', 'http'],
            ['https', 'https'],
        ],
        'size' => 1,
        'minitems' => 0,
        'maxitems' => 1
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['solr_host_read'] = [
    'label' => 'Scheme',
    'config' => [
        'type' => 'input',
        'default' => 'localhost',
        'placeholder' => 'localhost',
        'size' => 10
    ],
];


$GLOBALS['SiteConfiguration']['site']['columns']['solr_port_read'] = [
    'label' => 'Port',
    'config' => [
        'type' => 'input',
        'eval' => 'int,required',
        'size' => 5,
        'default' => 8983
    ],
];

$GLOBALS['SiteConfiguration']['site']['columns']['solr_path_read'] = [
    'label' => 'Path to cores (e.g. /solr/)',
    'config' => [
        'type' => 'input',
        'eval' => 'required',
        'default' => '/solr/'
    ],
];


$GLOBALS['SiteConfiguration']['site']['columns']['solr_use_write_connection'] = [
    'label' => 'Use different write connection',
    'onChange' => 'reload',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 0,
        'items' => [
            [
                0 => '',
                1 => ''
            ]
        ]
    ],
];


// write TCA
$GLOBALS['SiteConfiguration']['site']['columns']['solr_scheme_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['solr_scheme_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['solr_scheme_write']['displayCond'] = 'FIELD:solr_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['columns']['solr_port_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['solr_port_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['solr_port_write']['config']['eval'] = '';
$GLOBALS['SiteConfiguration']['site']['columns']['solr_port_write']['displayCond'] = 'FIELD:solr_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['columns']['solr_host_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['solr_host_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['solr_host_write']['config']['eval'] = '';
$GLOBALS['SiteConfiguration']['site']['columns']['solr_host_write']['displayCond'] = 'FIELD:solr_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['columns']['solr_path_write'] = $GLOBALS['SiteConfiguration']['site']['columns']['solr_path_read'];
$GLOBALS['SiteConfiguration']['site']['columns']['solr_path_write']['config']['eval'] = '';
$GLOBALS['SiteConfiguration']['site']['columns']['solr_path_write']['displayCond'] = 'FIELD:solr_use_write_connection:=:1';


$GLOBALS['SiteConfiguration']['site']['palettes']['solr_read']['showitem'] = 'solr_scheme_read, solr_host_read, solr_port_read, solr_path_read';
$GLOBALS['SiteConfiguration']['site']['palettes']['solr_write']['showitem'] = 'solr_scheme_write, solr_host_write, solr_port_write, solr_path_write';

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] = str_replace(
    'base,',
    'base, --palette--;Solr Configuration;solr_read, solr_use_write_connection, --palette--;Solr Write Configuration;solr_write,',
    $GLOBALS['SiteConfiguration']['site']['types']['0']['showitem']
);


/**
 * Language specific core configuration
 */
$GLOBALS['SiteConfiguration']['site_language']['columns']['solr_core_read'] = [
    'label' => 'Corename',
    'config' => [
        'type' => 'input',
        'eval' => 'trim',
        'valuePicker' => [
            'items' => [
                [ 'Arabic', 'core_ar'],
                [ 'Armenian', 'core_hy'],
                [ 'Basque', 'core_eu'],
                [ 'Brazilian Portuguese', 'core_ptbr'],
                [ 'Bulgarian', 'core_bg'],
                [ 'Burmese', 'core_my'],
                [ 'Catalan', 'core_ca'],
                [ 'Chinese', 'core_zh'],
                [ 'Czech', 'core_cs'],
                [ 'Danish', 'core_da'],
                [ 'Dutch', 'core_nl'],
                [ 'English', 'core_en'],
                [ 'Finnish', 'core_fi'],
                [ 'French', 'core_fr'],
                [ 'Galician', 'core_gl'],
                [ 'German', 'core_de'],
                [ 'Greek', 'core_el'],
                [ 'Hinde', 'core_hi'],
                [ 'Hungarian', 'core_hu'],
                [ 'Indonesian', 'core_id'],
                [ 'Irish', 'core_ie'],
                [ 'Italian', 'core_it'],
                [ 'Japanese', 'core_ja'],
                [ 'Korean', 'core_km'],
                [ 'Lao', 'core_lo'],
                [ 'Latvia', 'core_lv'],
                [ 'Norwegian', 'core_no'],
                [ 'Persian', 'core_fa'],
                [ 'Polish', 'core_pl'],
                [ 'Portuguese', 'core_pt'],
                [ 'Romanian', 'core_ro'],
                [ 'Russian', 'core_ru'],
                [ 'Serbian', 'core_rs'],
                [ 'Spanish', 'core_es'],
                [ 'Swedish', 'core_sv'],
                [ 'Thai', 'core_th'],
                [ 'Turkish', 'core_tr'],
                [ 'Ukrainian', 'core_uk'],
            ],
        ],
        'placeholder' => 'core_*',
    ],
];

$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] = str_replace(
    'flag',
    'flag, solr_core_read, ',
    $GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem']
);