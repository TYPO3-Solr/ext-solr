<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Utility\EidUtility;

# TSFE initialization

$pageId = filter_var(GeneralUtility::_GET('id'), FILTER_SANITIZE_NUMBER_INT);
$languageId = filter_var(
    GeneralUtility::_GET('L'),
    FILTER_VALIDATE_INT,
    array('options' => array('default' => 0, 'min_range' => 0))
);
$GLOBALS['TSFE'] = GeneralUtility::makeInstance(
    'TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController',
    $GLOBALS['TYPO3_CONF_VARS'],
    $pageId,
    0,
    true);
$GLOBALS['TSFE']->initFEuser();
$GLOBALS['TSFE']->initUserGroups();
// load TCA
EidUtility::initTCA();
$GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
$GLOBALS['TSFE']->rootLine = $GLOBALS['TSFE']->sys_page->getRootLine($pageId, '');
$GLOBALS['TSFE']->initTemplate();
$GLOBALS['TSFE']->getConfigArray();


$GLOBALS['TSFE']->sys_language_uid = $languageId;

$solrConfiguration = Util::getSolrConfiguration();

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

# Building Suggest Query
$q = trim(GeneralUtility::_GP('termLowercase'));

$isOpenSearchRequest = false;
if ('OpenSearch' == GeneralUtility::_GET('format')) {
    $isOpenSearchRequest = true;
    $q = GeneralUtility::_GET('q');
}
$allowedSitesConfig = $solrConfiguration->getObjectByPathOrDefault('plugin.tx_solr.search.query.', []);
$allowedSites = Util::resolveSiteHashAllowedSites(
    $pageId,
    $allowedSitesConfig['allowedSites']
);

$suggestQuery = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\SuggestQuery', $q);
$suggestQuery->setUserAccessGroups(explode(',', $GLOBALS['TSFE']->gr_list));
$suggestQuery->setSiteHashFilter($allowedSites);
$suggestQuery->setOmitHeader();

$additionalFilters = GeneralUtility::_GET('filters');
if (!empty($additionalFilters)) {
    $additionalFilters = json_decode($additionalFilters);
    foreach ($additionalFilters as $additionalFilter) {
        $suggestQuery->addFilter($additionalFilter);
    }
}

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

// Search
$solr = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager')->getConnectionByPageId(
    $pageId,
    $languageId
);
$search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search',
    $solr);

if ($search->ping()) {
    $results = json_decode($search->search($suggestQuery, 0, 0)->getRawResponse());
    $suggestConfig = $solrConfiguration->getObjectByPath('plugin.tx_solr.suggest.');
    $facetSuggestions = $results->facet_counts->facet_fields->{$suggestConfig['suggestField']};
    $facetSuggestions = get_object_vars($facetSuggestions);

    $suggestions = array();
    foreach ($facetSuggestions as $partialKeyword => $value) {
        $suggestionKey = trim($suggestQuery->getKeywords() . ' ' . $partialKeyword);
        $suggestions[$suggestionKey] = $facetSuggestions[$partialKeyword];
    }

    if ($isOpenSearchRequest) {
        $suggestions = array(
            $q,
            array_keys($suggestions)
        );
    }

    $ajaxReturnData = json_encode($suggestions);
} else {
    $ajaxReturnData = json_encode(array('status' => false));
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($ajaxReturnData));
header('Content-Type: application/json; charset=utf-8');
header('Content-Transfer-Encoding: 8bit');
echo $ajaxReturnData;
