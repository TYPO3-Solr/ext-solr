<?php

// search plugin
$pluginCode = 'solr_pi_results';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    array(
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_results',
        $pluginCode
    ),
    'list_type',
    'solr'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginCode] = 'layout,select_key,pages,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginCode] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    $pluginCode,
    'FILE:EXT:solr/Configuration/FlexForms/Results.xml');


// adding the Search Form plugin
$pluginCode = 'solr_pi_search';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    array(
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_search',
        $pluginCode
    ),
    'list_type',
    'solr'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginCode] = 'layout,select_key,pages,recursive';


// adding the Frequent Search plugin
$pluginCode = 'solr_pi_frequentsearches';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    array(
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_frequentsearches',
        $pluginCode
    ),
    'list_type',
    'solr'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginCode] = 'layout,select_key,pages,recursive';
