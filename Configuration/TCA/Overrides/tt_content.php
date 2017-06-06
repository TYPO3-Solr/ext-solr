<?php

if (!defined ('TYPO3_MODE')) {
    die ('Access denied.');
}

// Register the plugins
$pluginSignature = 'solr_pi_form';
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'solr',
    'pi_search',
    'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_search'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature]
    = 'layout,select_key,pages,recursive';


$pluginSignature = 'solr_pi_frequentlysearched';
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'solr',
    'pi_frequentlySearched',
    'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_frequentsearches'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature]
    = 'layout,select_key,pages,recursive';


$pluginSignature = 'solr_pi_result';
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    'solr',
    'pi_result',
    'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_results'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature]
    = 'layout,select_key,pages,recursive';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature]
    = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    $pluginSignature,
    'FILE:EXT:solr/Configuration/FlexForms/Results.xml'
);

