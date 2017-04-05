<?php

// search plugin
$pluginCode = 'solr_pi_results';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    [
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_results',
        $pluginCode
    ],
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
    [
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_search',
        $pluginCode
    ],
    'list_type',
    'solr'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginCode] = 'layout,select_key,pages,recursive';

// adding the Frequent Search plugin
$pluginCode = 'solr_pi_frequentsearches';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    [
        'LLL:EXT:solr/Resources/Private/Language/locallang.xlf:tt_content.list_type_pi_frequentsearches',
        $pluginCode
    ],
    'list_type',
    'solr'
);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginCode] = 'layout,select_key,pages,recursive';


# ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- # ----- #

// replace the built-in search content element
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/Results.xml',
    'search'
);

$GLOBALS['TCA']['tt_content']['types']['search']['showitem'] =
    '--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.general;general,
	--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.header;header,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.plugin,
		pi_flexform;;;;1-1-1,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.access,
		--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.visibility;visibility,
		--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.access;access,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.appearance,
		--palette--;LLL:EXT:cms/locallang_ttc.xml:palette.frames;frames,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.behaviour,
	--div--;LLL:EXT:cms/locallang_ttc.xml:tabs.extended';