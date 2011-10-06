<?php

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

# TSFE initialization

tslib_eidtools::connectDB();
$pageId     = filter_var(t3lib_div::_GET('id'), FILTER_SANITIZE_NUMBER_INT);
$languageId = filter_var(
	t3lib_div::_GET('L'),
	FILTER_VALIDATE_INT,
	array('options' => array('default' => 0, 'min_range' => 0))
);

$TSFE = t3lib_div::makeInstance('tslib_fe', $GLOBALS['TYPO3_CONF_VARS'], $pageId, 0, TRUE);
$TSFE->initFEuser();
$TSFE->initUserGroups();
$TSFE->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
$TSFE->rootLine = $TSFE->sys_page->getRootLine($pageId, '');
$TSFE->initTemplate();
$TSFE->getConfigArray();
$TSFE->includeTCA();
$TSFE->sys_language_uid = $languageId;

$solrConfiguration = tx_solr_Util::getSolrConfiguration();

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

# Building Suggest Query

$site = tx_solr_Site::getSiteByPageId($pageId);
$q    = trim(t3lib_div::_GP('termLowercase'));

$suggestQuery = t3lib_div::makeInstance('tx_solr_SuggestQuery', $q);
$suggestQuery->setUserAccessGroups(explode(',', $TSFE->gr_list));
$suggestQuery->setSiteHash($site->getSiteHash());

$language = 0;
if ($TSFE->sys_language_uid) {
	$language = $TSFE->sys_language_uid;
}
$suggestQuery->addFilter('language:' . $language);
$suggestQuery->setOmitHeader();

$additionalFilters = t3lib_div::_GET('filters');
if (!empty($additionalFilters)) {
	$additionalFilters = json_decode($additionalFilters);
	foreach ($additionalFilters as $additionalFilter) {
		$suggestQuery->addFilter($additionalFilter);
	}
}

#--- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- --- ---

	// Search
$solr   = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnectionByPageId(
	$pageId,
	$languageId
);
$search = t3lib_div::makeInstance('tx_solr_Search', $solr);

if ($search->ping()) {
	if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'])) {
		foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery'] as $classReference) {
			$queryModifier = t3lib_div::getUserObj($classReference);

			if ($queryModifier instanceof tx_solr_QueryModifier) {
				$suggestQuery = $queryModifier->modifyQuery($suggestQuery);
			}
		}
	}

	$results = json_decode($search->search($suggestQuery, 0, 0)->getRawResponse());
	$facetSuggestions = $results->facet_counts->facet_fields->{$solrConfiguration['suggest.']['suggestField']};
	$facetSuggestions = get_object_vars($facetSuggestions);

	$suggestions = array();
	foreach($facetSuggestions as $partialKeyword => $value){
		$suggestionKey = trim($suggestQuery->getKeywords() . ' ' . $partialKeyword);
		$suggestions[$suggestionKey] = $facetSuggestions[$partialKeyword];
	}

	$ajaxReturnData = json_encode($suggestions);
} else {
	$ajaxReturnData = json_encode(array('status' => FALSE));
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Length: ' . strlen($ajaxReturnData));
header('Content-Type: application/json; charset=' . $TSFE->renderCharset);
header('Content-Transfer-Encoding: 8bit');
echo $ajaxReturnData;

?>