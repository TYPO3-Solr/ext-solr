<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die('Access denied.');

// Add tt_content search group
$GLOBALS['TCA']['tt_content']['columns']['CType']['config']['itemGroups']['search'] = 'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:plugin_results';

// Register the plugins
$pluginSearchSignature = ExtensionUtility::registerPlugin(
    'solr',
    'pi_search',
    'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.CType_pi_search',
    'extensions-solr-plugin-contentelement',
    'search',
);
$GLOBALS['TCA']['tt_content']['types'][$pluginSearchSignature]['showitem'] = 'pi_flexform';
ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:solr/Configuration/FlexForms/Form.xml',
    $pluginSearchSignature,
);

$pluginFrequentlySearchedSignature = ExtensionUtility::registerPlugin(
    'solr',
    'pi_frequentlySearched',
    'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.CType_pi_frequentsearches',
    'extensions-solr-plugin-contentelement',
    'search',
);
$GLOBALS['TCA']['tt_content']['types'][$pluginFrequentlySearchedSignature]['showitem'] = '';

$pluginResultsSignature = ExtensionUtility::registerPlugin(
    'solr',
    'pi_results',
    'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.CType_pi_results',
    'extensions-solr-plugin-contentelement',
    'search',
);
$GLOBALS['TCA']['tt_content']['types'][$pluginResultsSignature]['showitem'] = 'pi_flexform';
ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:solr/Configuration/FlexForms/Results.xml',
    $pluginResultsSignature,
);
