<?php

use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die('Access denied.');

/**
 * No Search for sub entries of page tree.
 */
ExtensionManagementUtility::addTCAcolumns(
    'pages',
    [
        'no_search_sub_entries' => [
            'exclude' => true,
            'label' => 'LLL:EXT:solr/Resources/Private/Language/locallang_tca.xlf:pages.no_search_sub_entries',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        'label' => '',
                        'value' => '',
                        'invertStateDisplay' => true,
                    ],
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
    ]
);

ExtensionManagementUtility::addFieldsToPalette(
    'pages',
    'miscellaneous',
    'no_search_sub_entries',
    'after:no_search'
);

// Enable no_search_sub_entries for storage folders.
$GLOBALS['TCA']['pages']['palettes']['slimmed_miscellaneous'] = [
    'label' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_tca.xlf:pages.palettes.miscellaneous',
    'showitem' => 'no_search_sub_entries;LLL:EXT:solr/Resources/Private/Language/locallang_tca.xlf:pages.no_search_sub_entries',
];

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--palette--;;slimmed_miscellaneous',
    (string)PageRepository::DOKTYPE_SYSFOLDER,
    'after:module'
);
