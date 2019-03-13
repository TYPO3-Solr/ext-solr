<?php

/**
 * Global Solr Connection Settings
 */
// Configure a new simple required input field to site
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
$GLOBALS['SiteConfiguration']['site']['columns']['solr_scheme_write']['config']['eval'] = 'optional';
$GLOBALS['SiteConfiguration']['site']['columns']['solr_scheme_write']['displayCond'] = 'FIELD:solr_use_write_connection:=:1';

$GLOBALS['SiteConfiguration']['site']['palettes']['solr_read']['showitem'] = 'solr_scheme_read';
$GLOBALS['SiteConfiguration']['site']['palettes']['solr_write']['showitem'] = 'solr_scheme_write';

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
        'eval' => 'optional',
    ],
];

$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] = str_replace(
    'flag',
    'flag, solr_core_read, ',
    $GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem']
);