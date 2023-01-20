<?php

defined('TYPO3') or die('Access denied.');

/**
 * No Search for sub entries of page tree.
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
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
                        0 => '',
                        1 => '',
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

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
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

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--palette--;;slimmed_miscellaneous',
    (string)\TYPO3\CMS\Core\Domain\Repository\PageRepository::DOKTYPE_SYSFOLDER,
    'after:module'
);
